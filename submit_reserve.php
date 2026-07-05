<?php
/**
 * صفحه تصحیح submit_reserve.php
 * پردازش خرید بلیت و ثبت تراکنش
 */

require_once 'includes/auth.php';
require_once 'db.php';

$user_id = require_login();

try {
    $stmt_uname = $pdo->prepare('SELECT full_name FROM pool_users WHERE id = :id');
    $stmt_uname->execute(['id' => $user_id]);
    $fetched_user = $stmt_uname->fetch(PDO::FETCH_ASSOC);
    $user_name = $fetched_user ? $fetched_user['full_name'] : 'کاربر گرامی';
} catch (Exception $e) {
    $user_name = 'کاربر گرامی';
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_id'])) {
        csrf_require();

        $session_id = intval($_POST['session_id']);
        $ticket_count = isset($_POST['ticket_count']) ? intval($_POST['ticket_count']) : 1;
        $discount_code = isset($_POST['discount_code']) ? trim($_POST['discount_code']) : '';

        if ($session_id <= 0 || $ticket_count <= 0 || $ticket_count > MAX_TICKETS_PER_PURCHASE) {
            throw new Exception('❌ اطلاعات وارد شده برای تعداد بلیت یا سانس معتبر نیست.');
        }

        $pdo->beginTransaction();

        // قفل بر روی سانس برای جلوگیری از Race Condition
        $stmt = $pdo->prepare('SELECT * FROM sessionsone WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            throw new Exception('❌ سانس مورد نظر در سیستم پیدا نشد.');
        }

        if ((intval($session['reserved_count']) + $ticket_count) > intval($session['max_capacity'])) {
            throw new Exception('❌ تعداد درخواست بیشتر از ظرفیت باقی‌مانده است!');
        }

        // محاسبه قیمت پایه
        $base_ticket_price = BASE_TICKET_PRICE;
        $total_raw_price = $ticket_count * $base_ticket_price;

        // تخفیف تیرهای پکیجی
        $base_discount_percent = 0;
        if ($ticket_count == 2) { $base_discount_percent = 5; }
        elseif ($ticket_count == 3) { $base_discount_percent = 10; }
        elseif ($ticket_count == 4) { $base_discount_percent = 15; }
        elseif ($ticket_count >= 5) { $base_discount_percent = 20; }

        // بررسی کد تخفیف
        $coupon_discount_percent = 0;
        $has_valid_coupon = false;

        if (!empty($discount_code)) {
            $stmt_coupon = $pdo->prepare('SELECT * FROM discount_codes WHERE code = :code AND used_count < max_usage');
            $stmt_coupon->execute(['code' => $discount_code]);
            $coupon = $stmt_coupon->fetch(PDO::FETCH_ASSOC);

            if ($coupon) {
                $coupon_discount_percent = intval($coupon['percent']);
                $has_valid_coupon = true;
            }
        }

        $total_discount_percent = $base_discount_percent + $coupon_discount_percent;
        if ($total_discount_percent > 100) { $total_discount_percent = 100; }

        $discount_amount = ($total_raw_price * $total_discount_percent) / 100;
        $payable_amount = $total_raw_price - $discount_amount;

        // بررسی موجودی کیف پول
        $stmt_check_wallet = $pdo->prepare('SELECT wallet_balance FROM pool_users WHERE id = :id');
        $stmt_check_wallet->execute(['id' => $user_id]);
        $user_wallet = $stmt_check_wallet->fetch(PDO::FETCH_ASSOC);
        $current_wallet_balance = $user_wallet ? intval($user_wallet['wallet_balance']) : 0;

        if ($current_wallet_balance < $payable_amount) {
            throw new Exception('⚠️ موجودی کیف پول شما کافی نیست! موجودی: ' . number_format($current_wallet_balance) . ' تومان');
        }

        // کاهش موجودی کیف پول
        $stmt_deduct = $pdo->prepare('UPDATE pool_users SET wallet_balance = wallet_balance - :payable WHERE id = :id');
        $stmt_deduct->execute([
            'payable' => $payable_amount,
            'id' => $user_id,
        ]);

        // بروزرسانی تعداد استفاده کد تخفیف
        if ($has_valid_coupon) {
            $stmt_update_coupon = $pdo->prepare('UPDATE discount_codes SET used_count = used_count + 1 WHERE code = :code');
            $stmt_update_coupon->execute(['code' => $discount_code]);
        }

        // تولید کد ووچر
        $voucher_code = generate_voucher_code();

        // ثبت ووچر
        $stmt_v = $pdo->prepare('INSERT INTO vouchers (voucher_code, session_id, user_id, user_name, ticket_count) VALUES (:voucher_code, :session_id, :user_id, :user_name, :ticket_count)');
        $stmt_v->execute([
            'voucher_code' => $voucher_code,
            'session_id'   => $session_id,
            'user_id'      => $user_id,
            'user_name'    => $user_name,
            'ticket_count' => $ticket_count,
        ]);

        $voucher_id = $pdo->lastInsertId();

        // تولید کدهای سریال
        $generated_serials = [];
        for ($i = 1; $i <= $ticket_count; $i++) {
            $serial_code = generate_serial_code($i);
            $generated_serials[] = $serial_code;

            $stmt_s = $pdo->prepare("INSERT INTO ticket_serials (voucher_id, serial_code, status) VALUES (:voucher_id, :serial_code, 'valid')");
            $stmt_s->execute([
                'voucher_id'  => $voucher_id,
                'serial_code' => $serial_code,
            ]);
        }

        // بروزرسانی تعداد رزرو سانس
        $stmt_u = $pdo->prepare('UPDATE sessionsone SET reserved_count = reserved_count + :ticket_count WHERE id = :id');
        $stmt_u->execute([
            'ticket_count' => $ticket_count,
            'id'           => $session_id,
        ]);

        // ثبت امتیازات
        $points_per_ticket = POINTS_PER_TICKET;
        $total_earned_points = $ticket_count * $points_per_ticket;
        $update_points_stmt = $pdo->prepare('UPDATE pool_users SET points = points + :earned WHERE id = :user_id');
        $update_points_stmt->execute([
            'earned'  => $total_earned_points,
            'user_id' => $user_id,
        ]);

        $date_now = get_today_shamsi();
        try {
            $stmt_log_user_pts = $pdo->prepare("INSERT INTO points_transactions (user_id, points, type, description, created_at_shamsi) VALUES (:uid, :pts, 'earn', :descr, :c_shamsi)");
            $stmt_log_user_pts->execute([
                'uid'      => $user_id,
                'pts'      => $total_earned_points,
                'descr'    => "🎫 کسب امتیاز بابت خرید آنلاین بلیت به تعداد {$ticket_count} نفر",
                'c_shamsi' => $date_now,
            ]);
        } catch (Exception $e_log) {}

        // ثبت تراکنش کیف پول
        try {
            $wallet_desc = "خرید بلیت به تعداد {$ticket_count} نفر";
            if ($has_valid_coupon) {
                $wallet_desc .= " (کد تخفیف: {$discount_code})";
            }

            $stmt_tx = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at_shamsi) VALUES (:user_id, :amount, 'withdraw', :description, :created_at_shamsi)");
            $stmt_tx->execute([
                'user_id'           => $user_id,
                'amount'            => $payable_amount,
                'description'       => $wallet_desc,
                'created_at_shamsi' => $date_now,
            ]);
        } catch (Exception $tx_ex) {}

        $pdo->commit();

        // بررسی معرف‌گر و اعطای هدیه
        try {
            $stmt_check_inv = $pdo->prepare('SELECT referred_by FROM pool_users WHERE id = :id');
            $stmt_check_inv->execute(['id' => $user_id]);
            $inv_data = $stmt_check_inv->fetch(PDO::FETCH_ASSOC);

            if ($inv_data && !empty($inv_data['referred_by'])) {
                $referrer_id = intval($inv_data['referred_by']);

                $stmt_count_v = $pdo->prepare('SELECT COUNT(*) FROM vouchers WHERE user_id = :id');
                $stmt_count_v->execute(['id' => $user_id]);
                $user_vouchers_count = intval($stmt_count_v->fetchColumn());

                if ($user_vouchers_count === 1) {
                    $reward_points_gift = REFERRAL_BONUS_POINTS;

                    $stmt_reward_user = $pdo->prepare('UPDATE pool_users SET points = points + :reward WHERE id = :ref_id');
                    $stmt_reward_user->execute(['reward' => $reward_points_gift, 'ref_id' => $referrer_id]);

                    $stmt_log_ref_pts = $pdo->prepare("INSERT INTO points_transactions (user_id, points, type, description, created_at_shamsi) VALUES (:uid, :pts, 'earn', :descr, :c_shamsi)");
                    $stmt_log_ref_pts->execute([
                        'uid'      => $referrer_id,
                        'pts'      => $reward_points_gift,
                        'descr'    => "🎁 هدیه دعوت از دوست بابت اولین خرید موفق کاربر ({$user_name})",
                        'c_shamsi' => $date_now,
                    ]);

                    $stmt_tx_reward = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at_shamsi) VALUES (:ref_id, :amount, 'deposit', :descr, :created_at)");
                    $stmt_tx_reward->execute([
                        'ref_id'     => $referrer_id,
                        'amount'     => 0,
                        'descr'      => '🎁 هدیه باشگاه مشتریان بابت دعوت از دوست (اولین خرید موفق کاربر جدید)',
                        'created_at' => $date_now,
                    ]);
                }
            }
        } catch (Exception $ref_exception) {}

        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $admin_check_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/admin_check.php?voucher_code=' . urlencode($voucher_code);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>✅ خرید موفقیت‌آمیز</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Tahoma, sans-serif;
            background: #f3f4f6;
            text-align: center;
            padding: 40px 20px;
            direction: rtl;
            color: #333;
        }
        .success-box {
            background: white;
            max-width: 700px;
            margin: 0 auto;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-top: 6px solid #10b981;
        }
        h2 {
            color: #10b981;
            font-size: 28px;
            margin-bottom: 16px;
        }
        .voucher-title {
            font-size: 16px;
            color: #0056b3;
            background: #e7f1ff;
            padding: 12px;
            border-radius: 6px;
            display: block;
            margin: 20px 0;
            font-weight: 600;
        }
        .voucher-code {
            font-size: 28px;
            font-weight: bold;
            font-family: monospace;
            letter-spacing: 2px;
            color: #111;
            background: #f9fafb;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            word-break: break-all;
        }
        .qr-section {
            display: inline-block;
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: 2px solid #b3d7ff;
            margin: 20px 0;
        }
        .invoice-details {
            background: #fef3c7;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #fcd34d;
            margin: 20px 0;
            font-size: 14px;
            line-height: 24px;
            text-align: right;
        }
        .serial-item {
            background: #f8fafc;
            border: 1px dashed #ccc;
            padding: 12px;
            margin: 12px 0;
            border-radius: 6px;
            font-weight: bold;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .serial-code {
            font-family: monospace;
            color: #dc3545;
            font-size: 14px;
        }
        .serial-qr {
            background: white;
            padding: 4px;
            border-radius: 4px;
        }
        .btn {
            display: inline-block;
            padding: 12px 20px;
            margin: 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        @media print {
            body { padding: 0; background: white; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="success-box">
        <h2>🎉 رزرو شما با موفقیت ثبت شد!</h2>
        <p>مشتری گرامی <strong><?php echo h($user_name); ?></strong></p>
        
        <div class="voucher-title">کد ووچر اصلی:</div>
        <div class="voucher-code"><?php echo h($voucher_code); ?></div>
        
        <div class="qr-section" id="voucher_qr"></div>
        
        <div class="invoice-details">
            📋 <strong>جزئیات رزرو:</strong><br>
            تعداد بلیت: <strong><?php echo intval($ticket_count); ?> نفر</strong><br>
            مبلغ اصلی: <strong><?php echo number_format($total_raw_price); ?></strong> تومان<br>
            تخفیف: <strong><?php echo $total_discount_percent; ?>%</strong> (-<?php echo number_format($discount_amount); ?> تومان)<br>
            <span style="border-top: 1px solid #d97706; display: block; padding-top: 8px; margin-top: 8px;">
            💵 مبلغ پرداختی: <span style="color: #10b981; font-size: 18px; font-weight: bold;"><?php echo number_format($payable_amount); ?></span> تومان
            </span>
            🏆 امتیاز کسب شده: <strong style="color: #7c3aed;">+<?php echo intval($total_earned_points); ?> امتیاز</strong>
        </div>
        
        <h3 style="margin: 30px 0 20px; font-size: 18px;">🎫 کدهای سریال برای ورود:</h3>
        <div id="serials_container"></div>
        
        <div style="margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
            <button class="btn btn-success" onclick="window.print()">🖨️ چاپ / ذخیره PDF</button>
            <a href="user_dashboard.php" class="btn btn-secondary">🏠 بازگشت به داشبورد</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        const voucherUrl = <?php echo json_encode($admin_check_url, JSON_UNESCAPED_UNICODE); ?>;
        const serials = <?php echo json_encode(array_values($generated_serials), JSON_UNESCAPED_UNICODE); ?>;
        
        function generateQR(elementId, text, size = 110) {
            const el = document.getElementById(elementId);
            if (el && typeof QRCode !== 'undefined') {
                new QRCode(el, {
                    text: text,
                    width: size,
                    height: size,
                    colorDark: '#111827',
                    colorLight: '#fff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // QR ووچر اصلی
            generateQR('voucher_qr', voucherUrl, 120);
            
            // QR کدهای سریال
            const container = document.getElementById('serials_container');
            serials.forEach((code, i) => {
                const div = document.createElement('div');
                div.className = 'serial-item';
                div.innerHTML = `
                    <div>👤 بلیت ${i + 1} <code class="serial-code">${code}</code></div>
                    <div class="serial-qr" id="serial_qr_${i}"></div>
                `;
                container.appendChild(div);
                generateQR(`serial_qr_${i}`, code, 100);
            });
        });
    </script>
</body>
</html>
<?php
    } else {
        throw new Exception('❌ درخواست غیرمجاز است.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo "<div style='text-align:center; margin-top:50px; font-family:tahoma; font-weight:bold; direction:rtl; color:red;'>";
    echo "<h2>❌ خطا</h2>";
    echo "<p style='font-size:16px;'>" . h($e->getMessage()) . "</p>";
    echo "<a href='user_dashboard.php' style='color:blue; text-decoration:underline; font-size:14px;'>بازگشت به پنل</a>";
    echo '</div>';
}
