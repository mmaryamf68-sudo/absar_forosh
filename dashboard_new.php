<?php
  // کدهای قبلی شما (اتصال به دیتابیس و ...)
  require_once 'header_new.php'; // ظاهر جدید را اینجا لود می‌کنیم
require_once 'includes/auth.php';
require_once 'db.php';

$user_id = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_ticket'])) {
    csrf_require();

    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject !== '' && $message !== '' && mb_strlen($subject) <= 200 && mb_strlen($message) <= 2000) {
        $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $subject, $message]);
    }
}

try {
    $stmt_user = $pdo->prepare('SELECT full_name, wallet_balance, points, referral_code, referred_by FROM pool_users WHERE id = :id');
    $stmt_user->execute(['id' => $user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('کاربر مورد نظر در سیستم یافت نشد.');
    }

    if (empty($user['referral_code'])) {
        $generated_ref = generate_referral_code();
        $update_ref = $pdo->prepare('UPDATE pool_users SET referral_code = :ref WHERE id = :id');
        $update_ref->execute(['ref' => $generated_ref, 'id' => $user_id]);
        $user['referral_code'] = $generated_ref;
    }

    $user_name = $user['full_name'];
    $wallet_balance = intval($user['wallet_balance']);
    $club_points = intval($user['points']);
    $referral_code = $user['referral_code'];
    $referred_by_id = $user['referred_by'];

    $referrer_name = null;
    if (!empty($referred_by_id)) {
        $stmt_ref_name = $pdo->prepare('SELECT full_name FROM pool_users WHERE id = :rid');
        $stmt_ref_name->execute(['rid' => $referred_by_id]);
        $referrer_name = $stmt_ref_name->fetchColumn();
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $referral_link = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/register.php?ref=' . urlencode($referral_code);

    $stmt_tickets = $pdo->prepare("
        SELECT
            v.id AS voucher_id,
            v.voucher_code,
            v.ticket_count,
            v.session_id,
            s.date_shamsi,
            s.time_start,
            s.time_end,
            s.gender_type,
            COUNT(CASE WHEN ts.status = 'valid' THEN 1 END) AS active_serials_count
        FROM vouchers v
        JOIN sessionsone s ON v.session_id = s.id
        LEFT JOIN ticket_serials ts ON v.id = ts.voucher_id
        WHERE v.user_id = :u_id
        GROUP BY v.id
        HAVING active_serials_count > 0
        ORDER BY v.id DESC
    ");
    $stmt_tickets->execute(['u_id' => $user_id]);
    $active_vouchers = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);

    $stmt_sub_users = $pdo->prepare('
        SELECT id, full_name, phone,
        (SELECT COUNT(*) FROM vouchers WHERE user_id = pool_users.id) as total_buys
        FROM pool_users
        WHERE referred_by = :my_id
        ORDER BY id DESC
    ');
    $stmt_sub_users->execute(['my_id' => $user_id]);
    $my_referrals = $stmt_sub_users->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    app_error('خطای سیستم: ' . $e->getMessage());
}

$page_title = 'پنل کاربری مجموعه استخر آبسار';
$layout_mode = 'panel';
$active_page = 'dashboard';
require_once 'includes/layout_start.php';
?>

    <div class="page-hero">
        <h2>👋 خوش آمدید، <?php echo h($user_name); ?></h2>
        <p>مجموعه ورزشی استخر آبسار • مدیریت هوشمند بلیت‌ها و موجودی حساب شما</p>
    </div>

    <?php if (!empty($referrer_name)): ?>
        <div class="app-alert app-alert-success">
            🤝 <strong>حساب کاربری فعال:</strong> شما توسط دوست گرامی‌تان <b>«<?php echo h($referrer_name); ?>»</b> به مجموعه آبسار دعوت شده‌اید.
        </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon emerald">💼</div>
                <div>
                    <p class="stat-label">موجودی کیف پول هوشمند</p>
                    <div class="wallet-flex">
                        <div id="wallet_balance_display" class="stat-value" data-raw-balance="<?php echo number_format($wallet_balance); ?> تومان">
                            <?php echo number_format($wallet_balance); ?> تومان
                        </div>
                        <button type="button" onclick="toggleWalletBalance()" class="eye-toggle" id="balance_eye_icon">👁️</button>
                    </div>
                </div>
            </div>
            <a href="wallet_history.php" class="stat-action emerald">📊 مشاهده تراکنش‌ها و شارژ</a>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon purple">🏆</div>
                <div>
                    <p class="stat-label">باشگاه مشتریان آبسار</p>
                    <div class="stat-value" style="color:var(--accent-purple);"><?php echo number_format($club_points); ?> <span style="font-size:14px;font-weight:normal;">امتیاز</span></div>
                </div>
            </div>
           <a href="points_history.php" class="stat-action purple">📜 تاریخچه امتیازها</a>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-top">
                    <div class="stat-icon amber">🎁</div>
                    <div>
                        <p class="stat-label">کد معرف اختصاصی</p>
                        <div class="stat-value" style="color:var(--accent-amber);font-family:monospace;letter-spacing:1px;"><?php echo h($referral_code); ?></div>
                    </div>
                </div>
                <div class="input-inline-group">
                    <input type="text" class="clean-input" id="referral_link_field" value="<?php echo h($referral_link); ?>" readonly>
                    <button type="button" class="btn-inner-copy" onclick="copyReferralLink()">کپی لینک</button>
                </div>
                <div class="referral-qr-wrap">
                    <div class="referral-qr-box">
                        <div id="referral_qrcode"></div>
                    </div>
                    <div class="referral-qr-info">
                        <strong>📱 QR کد دعوت</strong>
                        دوستانتان با اسکن این کد مستقیم به صفحه ثبت‌نام با کد معرف شما می‌روند.
                        <button type="button" class="btn-qr-download" onclick="downloadReferralQr()">⬇️ دانلود QR</button>
                    </div>
                </div>
            </div>
            <a href="https://t.me/share/url?url=<?php echo urlencode($referral_link); ?>&text=<?php echo urlencode('سلام! با ثبت‌نام از طریق لینک من در باشگاه استخر آبسار، هدیه ویژه بگیر:'); ?>" target="_blank" class="stat-action telegram">
                ✈️ اشتراک‌گذاری در تلگرام
            </a>
        </div>
    </div>

    <div class="app-card">
        <h3>🎫 خرید آنلاین بلیت (تایم آزاد)</h3>
        <div class="buy-zone">
            <div class="buy-row">
                <div class="buy-info">👤 بلیت انفرادی تک‌نفره:</div>
                <a href="reserve.php?count=1" class="btn-pill active">خرید بلیت تک‌نفره</a>
            </div>
            <div class="buy-row">
                <div class="buy-info">👥 پکیج‌های گروهی همراه با تخفیف پلکانی:</div>
                <div class="btn-group-links">
                    <a href="reserve.php?count=2" class="btn-pill">پکیج ۲ نفره</a>
                    <a href="reserve.php?count=3" class="btn-pill">پکیج ۳ نفره</a>
                    <a href="reserve.php?count=4" class="btn-pill">پکیج ۴ نفره</a>
                    <a href="reserve.php?count=5" class="btn-pill">پکیج ۵ نفره</a>
                </div>
            </div>
        </div>
    </div>

    <div class="app-card">
        <h3>📋 بلیت‌های معتبر و فعال شما</h3>
        <?php if (count($active_vouchers) > 0): ?>
            <div class="table-wrap">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>کد ووچر</th>
                            <th>تعداد نفرات</th>
                            <th>تاریخ سانس</th>
                            <th>ساعت سانس</th>
                            <th>نوع سانس</th>
                            <th>کدهای ورود به گیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_vouchers as $ticket): ?>
                            <tr>
                                <td style="font-weight: bold;">
                                    <a href="print_ticket.php?voucher_id=<?php echo intval($ticket['voucher_id']); ?>" target="_blank" style="text-decoration: none; color: var(--accent-blue);">
                                        🖨️ <?php echo h($ticket['voucher_code']); ?>
                                    </a>
                                </td>
                                <td><strong><?php echo intval($ticket['ticket_count']); ?> نفر</strong></td>
                                <td><?php echo h($ticket['date_shamsi']); ?></td>
                                <td><?php echo h($ticket['time_start'] . ' تا ' . $ticket['time_end']); ?></td>
                                <td>
                                    <?php
                                    $gender_db = isset($ticket['gender_type']) ? strtolower(trim($ticket['gender_type'])) : '';
                                    $time_end_db = isset($ticket['time_end']) ? trim($ticket['time_end']) : '';
                                    if ($time_end_db === '17:00' || $time_end_db === '20:00' || $gender_db === 'female') {
                                        echo '<span class="badge badge-female">بانوان 👩</span>';
                                    } else {
                                        echo '<span class="badge badge-male">آقایان 👨</span>';
                                    }
                                    ?>
                                </td><td>
                                    <div class="serial-tag-container">
                                        <?php
                                        $stmt_serials = $pdo->prepare('SELECT serial_code, status FROM ticket_serials WHERE voucher_id = :v_id');
                                        $stmt_serials->execute(['v_id' => $ticket['voucher_id']]);
                                        while ($s = $stmt_serials->fetch(PDO::FETCH_ASSOC)) {
                                            $isValid = ($s['status'] == 'valid');
                                            $dotClass = $isValid ? 'dot-valid' : 'dot-used';
                                            $statusText = $isValid ? 'معتبر' : 'باطل شد';
                                            echo "<div class='serial-badge'>"
                                                . '<code>' . h($s['serial_code']) . '</code>'
                                                . "<span><span class='status-dot {$dotClass}'></span>" . h($statusText) . '</span>'
                                                . '</div>';
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); font-size: 14px;">شما در حال حاضر هیچ بلیت فعال و معتبری ندارید.</p>
        <?php endif; ?>
    </div>

    <div class="app-card" style="border-top:4px solid var(--accent-amber);">
        <h3>👥 لیست کاربران دعوت‌شده (زیرمجموعه‌ها)</h3>
        <?php if (count($my_referrals) > 0): ?>
            <div class="table-wrap">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>ردیف</th>
                            <th>نام و نام خانوادگی</th>
                            <th>شماره موبایل</th>
                            <th>تعداد خرید بلیت</th>
                            <th>وضعیت هدیه معرف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_idx = 1; foreach ($my_referrals as $sub_user): ?>
                            <tr>
                                <td><?php echo $row_idx++; ?></td>
                                <td><strong><?php echo h($sub_user['full_name']); ?></strong></td>
                                <td style="font-family: monospace;"><?php echo h($sub_user['phone']); ?></td>
                                <td><?php echo intval($sub_user['total_buys']); ?> خرید</td>
                                <td>
                                    <?php if (intval($sub_user['total_buys']) > 0): ?>
                                        <span class="badge badge-success">✅ واریز شد (+۵۰ امتیاز)</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">⏳ در انتظار اولین خرید</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-muted); font-size: 14px; padding: 15px 0;">
                هنوز هیچ‌کس با کد معرف شما ثبت‌نام نکرده است. لینک اختصاصی خود را کپی کنید و برای دوستانتان بفرستید! 🎁
            </p>
        <?php endif; ?>
    </div>
   <div class="app-card" style="border-top:4px solid var(--accent-purple);">
    <h3>📩 پشتیبانی و تیکت‌ها</h3>
    <form method="POST" style="margin-bottom:20px;">
        <?php echo csrf_field(); ?>
        <input type="text" name="subject" class="app-input" placeholder="موضوع تیکت" required maxlength="200" style="margin-bottom:10px;">
        <textarea name="message" class="app-textarea" placeholder="متن پیام..." required maxlength="2000"></textarea>
        <button type="submit" name="send_ticket" class="app-btn app-btn-primary" style="margin-top:10px;">ارسال تیکت</button>
    </form>

    <div class="table-wrap">
        <table class="app-table">
            <thead><tr><th>موضوع</th><th>پیام شما</th><th>پاسخ ادمین</th><th>وضعیت</th></tr></thead>
            <tbody>
                <?php
                $stmt_my_tickets = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id = ? ORDER BY id DESC');
                $stmt_my_tickets->execute([$user_id]);
                while ($t = $stmt_my_tickets->fetch(PDO::FETCH_ASSOC)):
                ?>
                <tr>
                    <td><strong><?php echo h($t['subject']); ?></strong></td>
                    <td><?php echo h($t['message']); ?></td>
                    <td style="color: var(--accent-blue);"><?php echo $t['reply'] ? h($t['reply']) : 'در انتظار پاسخ...'; ?></td>
                    <td><?php echo $t['status'] == 'answered' ? '✅ پاسخ داده شد' : '⏳ در حال بررسی'; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- هدر اصلی -->
<header class="flex justify-between items-center p-4 bg-white shadow-sm">
    <!-- دکمه سه خط برای باز کردن منو -->
    <button onclick="toggleSidebar()" class="p-2">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
    </button>
    <h1 class="font-bold text-lg">استخر آبی</h1>
    <img src="user_avatar.jpg" class="w-10 h-10 rounded-full">
</header>

<!-- منوی مخفی (Sidebar) -->
<div id="sidebar" class="fixed inset-y-0 right-0 w-80 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out p-6">
    <button onclick="toggleSidebar()" class="mb-6 text-2xl">✕</button>
    
    <!-- محتوای داخل منو (مانند 1000087533.png) -->
    <div class="text-right">
        <div class="flex items-center gap-4 mb-8">
            <img src="user_avatar.jpg" class="w-16 h-16 rounded-full">
            <div>
                <h3 class="font-bold">امیر محمدی</h3>
                <p class="text-sm text-gray-500">0912 345 6789</p>
            </div>
        </div>
        
        <!-- کارت‌های امتیاز و کیف پول -->
        <div class="grid grid-cols-2 gap-2 mb-8">
            <div class="bg-amber-50 p-4 rounded-2xl text-center">امتیاز: 2,450</div>
            <div class="bg-green-50 p-4 rounded-2xl text-center">موجودی: 1.2M</div>
        </div>

        <nav class="space-y-4">
            <a href="#" class="block p-4 bg-gray-50 rounded-xl">تنظیمات</a>
            <a href="#" class="block p-4 bg-gray-50 rounded-xl">پشتیبانی</a>
            <a href="#" class="block p-4 bg-gray-50 rounded-xl">سابقه تراکنش‌ها</a>
        </nav>
    </div>
</div>

<!-- اسکریپت ساده برای مدیریت نمایش منو -->
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('translate-x-full'); // باز و بسته شدن
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
