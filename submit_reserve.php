<?php
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

        if ($session_id <= 0 || $ticket_count <= 0 || $ticket_count > 20) {
            throw new Exception('❌ اطلاعات وارد شده برای تعداد بلیت یا سانس معتبر نیست.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM sessionsone WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            throw new Exception('❌ سانس مورد نظر در سیستم پیدا نشد.');
        }

        if (($session['reserved_count'] + $ticket_count) > $session['max_capacity']) {
            throw new Exception('❌ تعداد درخواست بیشتر از ظرفیت باقی‌مانده است!');
        }

        $base_ticket_price = 450000;
        $total_raw_price = $ticket_count * $base_ticket_price;

        $base_discount_percent = 0;
        if ($ticket_count == 2) { $base_discount_percent = 5; }
        elseif ($ticket_count == 3) { $base_discount_percent = 10; }
        elseif ($ticket_count == 4) { $base_discount_percent = 15; }
        elseif ($ticket_count >= 5) { $base_discount_percent = 20; }

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

        $stmt_check_wallet = $pdo->prepare('SELECT wallet_balance FROM pool_users WHERE id = :id');
        $stmt_check_wallet->execute(['id' => $user_id]);
        $user_wallet = $stmt_check_wallet->fetch(PDO::FETCH_ASSOC);
        $current_wallet_balance = $user_wallet ? intval($user_wallet['wallet_balance']) : 0;

        if ($current_wallet_balance < $payable_amount) {
            throw new Exception('⚠️ موجودی کیف پول شما کافی نیست!');
        }

        $stmt_deduct = $pdo->prepare('UPDATE pool_users SET wallet_balance = wallet_balance - :payable WHERE id = :id');
        $stmt_deduct->execute([
            'payable' => $payable_amount,
            'id' => $user_id,
        ]);

        if ($has_valid_coupon) {
            $stmt_update_coupon = $pdo->prepare('UPDATE discount_codes SET used_count = used_count + 1 WHERE code = :code');
            $stmt_update_coupon->execute(['code' => $discount_code]);
        }

        $voucher_code = generate_voucher_code();

        $stmt_v = $pdo->prepare('INSERT INTO vouchers (voucher_code, session_id, user_id, user_name, ticket_count) VALUES (:voucher_code, :session_id, :user_id, :user_name, :ticket_count)');
        $stmt_v->execute([
            'voucher_code' => $voucher_code,
            'session_id'   => $session_id,
            'user_id'      => $user_id,
            'user_name'    => $user_name,
            'ticket_count' => $ticket_count,
        ]);

        $voucher_id = $pdo->lastInsertId();

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

        $stmt_u = $pdo->prepare('UPDATE sessionsone SET reserved_count = reserved_count + :ticket_count WHERE id = :id');
        $stmt_u->execute([
            'ticket_count' => $ticket_count,
            'id'           => $session_id,
        ]);

        $points_per_ticket = 10;
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
                    $reward_points_gift = 50;

                    $stmt_reward_user = $pdo->prepare('UPDATE pool_users SET points = points + :reward WHERE id = :ref_id');
                    $stmt_reward_user->execute(['reward' => $reward_points_gift, 'ref_id' => $referrer_id]);

                    $stmt_log_ref_pts = $pdo->prepare("INSERT INTO points_transactions (user_id, points, type, description, created_at_shamsi) VALUES (:uid, :pts, 'earn', :descr, :c_shamsi)");
                    $stmt_log_ref_pts->execute([
                        'uid'      => $referrer_id,
                        'pts'      => $reward_points_gift,
                        'descr'    => "🎁 هدیه دعوت از دوست بابت اولین خرید موفق کاربر ({$user_name})",
                        'c_shamsi' => $date_now,
                    ]);

                    $stmt_tx_reward = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at_shamsi) VALUES (:ref_id, 0, 'deposit', :descr, :created_at)");
                    $stmt_tx_reward->execute([
                        'ref_id'     => $referrer_id,
                        'descr'      => '🎁 هدیه باشگاه مشتریان بابت دعوت از دوست (اولین خرید موفق کاربر جدید)',
                        'created_at' => $date_now,
                    ]);
                }
            }
        } catch (Exception $ref_exception) {}

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $admin_check_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/admin_check.php?voucher_code=' . urlencode($voucher_code);
?>
        <!DOCTYPE html>
        <html lang="fa" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>خرید موفقیت‌آمیز بلیت</title>
            <style>
                body { font-family: tahoma; background: #f4f7f6; text-align: center; padding: 40px; direction: rtl; }
                .success-box { background: white; max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 5px solid #28a745; text-align: right; }
                h2 { color: #28a745; text-align: center; }
                .voucher-title { font-size: 22px; color: #007bff; background: #e7f1ff; padding: 10px; border-radius: 5px; display: block; text-align: center; margin: 15px 0; font-family: monospace; }
                .serial-item { background: #f8f9fa; border: 1px dashed #ccc; padding: 10px; margin: 10px 0; border-radius: 4px; font-weight: bold; font-size: 14px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
                .serial-qr { background: #fff; padding: 6px; border-radius: 6px; flex-shrink: 0; }
                .serial-qr canvas, .serial-qr img { display: block; }
                .voucher-qr-wrap { display: flex; flex-direction: column; align-items: center; margin: 10px 0 15px; gap: 6px; }
                .voucher-qr { background: #fff; padding: 8px; border-radius: 8px; border: 1px solid #b3d7ff; }
                .invoice-details { background: #fff3cd; padding: 15px; border-radius: 6px; border: 1px solid #ffeeba; margin: 20px 0; font-size: 14px; line-height: 24px; }
                .btn-dashboard { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: block; text-align: center; margin-top: 20px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="success-box">
                <h2>🎉 رزرو شما با موفقیت ثبت شد!</h2>
                <p style="text-align:center;">مشتری گرامی <strong><?php echo h($user_name); ?></strong>، رسید خرید شما صادر شد.</p>
                <div class="voucher-title"><?php echo h($voucher_code); ?></div>
                <div class="voucher-qr-wrap">
                    <div class="voucher-qr" id="success_voucher_qr"></div>
                    <small style="color:#666;">QR ووچر — اسکن توسط پذیرش برای استعلام خودکار</small>
                </div>

                <div class="invoice-details">
                    تعداد بلیت رزرو شده: <strong><?php echo intval($ticket_count); ?> نفر</strong><br>
                    🎁 امتیاز کسب شده باشگاه مشتریان: <strong style="color:#7c3aed;">+<?php echo intval($total_earned_points); ?> امتیاز (ثبت در تاریخچه)</strong><br>
                    💵 <strong>مبلغ کسر شده از کیف پول: <span style="color:#10b981;"><?php echo number_format($payable_amount); ?> تومان</span></strong>
                </div>

                <h4 style="color:#555;">🎫 کدهای سریال ورود به گیت:</h4>
                <?php foreach ($generated_serials as $i => $serial): ?>
                    <div class="serial-item">
                        <div class="serial-qr" id="success_serial_qr_<?php echo $i; ?>"></div>
                        <span>👤 بلیت همراه:</span>
                        <code style="color: #dc3545; font-size:15px;"><?php echo h($serial); ?></code>
                    </div>
                <?php endforeach; ?>

                <a href="print_ticket.php?voucher_id=<?php echo intval($voucher_id); ?>" target="_blank" class="btn-dashboard" style="background:#10b981; margin-top:10px;">🖨️ چاپ بلیت با QR</a>
                <a href="user_dashboard.php" class="btn-dashboard">🗂️ بازگشت به پنل کاربری</a>
            </div>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <script>
            (function() {
                var voucherCheckUrl = <?php echo json_encode($admin_check_url, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                var serials = <?php echo json_encode(array_values($generated_serials), JSON_UNESCAPED_UNICODE); ?>;
                function mkQr(id, text, size) {
                    var el = document.getElementById(id);
                    if (el && typeof QRCode !== 'undefined') {
                        new QRCode(el, { text: text, width: size, height: size, colorDark: '#111827', colorLight: '#fff', correctLevel: QRCode.CorrectLevel.M });
                    }
                }
                document.addEventListener('DOMContentLoaded', function() {
                    mkQr('success_voucher_qr', voucherCheckUrl, 110);
                    serials.forEach(function(code, i) { mkQr('success_serial_qr_' + i, code, 90); });
                });
            })();
            </script>
        </body>
        </html>
<?php
    } else {
        throw new Exception('❌ درخواست غیرمجاز است.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo "<div style='color:red; text-align:center; margin-top:50px; font-family:tahoma; font-weight:bold; direction:rtl;'>";
    echo h($e->getMessage());
    echo '</div>';
    echo "<br><center><a href='user_dashboard.php' style='font-family:tahoma;'>بازگشت به پنل کاربری</a></center>";
}
