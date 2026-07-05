<?php
require_once 'includes/auth.php';
require_once 'db.php';

$user_id = require_login();

$message = '';
$message_type = 'warning';
$date_now = get_today_shamsi();

try {
    $stmt_user = $pdo->prepare('SELECT full_name, points, wallet_balance FROM pool_users WHERE id = :id');
    $stmt_user->execute(['id' => $user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('کاربر یافت نشد.');
    }

    $user_name = $user['full_name'];
    $current_points = intval($user['points']);
    $current_wallet = intval($user['wallet_balance']);

} catch (Exception $e) {
    app_error('خطا در بارگذاری اطلاعات: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_points'])) {
    csrf_require();

    if ($current_points < 300) {
        $message = '❌ امتیاز شما کافی نیست. برای تبدیل به شارژ، حداقل باید 300 امتیاز داشته باشید.';
        $message_type = 'error';
    } else {
        $bundles = floor($current_points / 300);
        $points_to_deduct = $bundles * 300;
        $cash_reward = $bundles * 50000;

        try {
            $pdo->beginTransaction();

            $stmt_deduct = $pdo->prepare('UPDATE pool_users SET points = points - :pts WHERE id = :id AND points >= :pts');
            $stmt_deduct->execute([
                'pts' => $points_to_deduct,
                'id'  => $user_id,
            ]);

            if ($stmt_deduct->rowCount() === 0) {
                throw new Exception('امتیاز کافی برای تبدیل وجود ندارد.');
            }

            $stmt_log_pts = $pdo->prepare("INSERT INTO points_transactions (user_id, points, type, description, created_at_shamsi) VALUES (:uid, :pts, 'spend', :descr, :c_shamsi)");
            $stmt_log_pts->execute([
                'uid'      => $user_id,
                'pts'      => $points_to_deduct,
                'descr'    => "🎁 تبدیل اتوماتیک {$points_to_deduct} امتیاز باشگاه مشتریان به اعتبار حساب",
                'c_shamsi' => $date_now,
            ]);

            $stmt_wallet = $pdo->prepare('UPDATE pool_users SET wallet_balance = wallet_balance + :cash WHERE id = :id');
            $stmt_wallet->execute([
                'cash' => $cash_reward,
                'id'   => $user_id,
            ]);

            $stmt_tx = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at_shamsi) VALUES (:uid, :cash, 'deposit', :descr, :c_shamsi)");
            $stmt_tx->execute([
                'uid'      => $user_id,
                'cash'     => $cash_reward,
                'descr'    => "🎁 پاداش تبدیل سیستمی {$points_to_deduct} امتیاز",
                'c_shamsi' => $date_now,
            ]);

            $pdo->commit();

            $current_points -= $points_to_deduct;
            $message = '🚀 تبدیل آنی موفقیت‌آمیز بود! تعداد ' . number_format($points_to_deduct) . ' امتیاز کسر شد و مبلغ ' . number_format($cash_reward) . ' تومان به کیف پول شما واریز گردید.';
            $message_type = 'success';

        } catch (Exception $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Points redeem error: ' . $ex->getMessage());
            $message = '❌ خطایی در تبدیل امتیاز رخ داد. لطفاً دوباره تلاش کنید.';
            $message_type = 'error';
        }
    }
}

$page_title = 'تبدیل امتیاز باشگاه مشتریان';
$layout_mode = 'panel';
$active_page = 'points';
require_once 'includes/layout_start.php';
?>

<div class="app-wrapper-narrow" style="margin:0 auto;">

<div class="page-hero page-hero-purple page-hero-flex">
    <div>
        <h2>🎁 تبدیل امتیاز باشگاه مشتریان</h2>
        <p>امتیازهای خود را به شارژ کیف پول تبدیل کنید</p>
    </div>
    <div class="hero-badge">
        <span class="num"><?php echo number_format($current_points); ?></span>
        <span class="lbl">امتیاز شما</span>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="app-alert app-alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div>
<?php endif; ?>

<div class="app-card" style="text-align:center;">
    <div style="font-size:52px;margin-bottom:12px;">💰</div>
    <h3 style="justify-content:center;">تبدیل امتیاز به موجودی کیف پول</h3>
    <p style="color:var(--text-muted);margin-bottom:20px;">سیستم به‌صورت خودکار امتیازهای شما را محاسبه و به حساب اضافه می‌کند.</p>

    <div class="app-alert app-alert-info" style="text-align:right;">
        📊 <b>فرمول:</b> هر <b>۳۰۰ امتیاز</b> = <b>۵۰,۰۰۰ تومان</b><br>
        <?php
        if ($current_points >= 300) {
            $possible_bundles = floor($current_points / 300);
            $possible_cash = $possible_bundles * 50000;
            echo '✨ می‌توانید <b>' . ($possible_bundles * 300) . ' امتیاز</b> را به <b>' . number_format($possible_cash) . ' تومان</b> تبدیل کنید.';
        } else {
            echo '❌ حداقل ۳۰۰ امتیاز برای تبدیل لازم است.';
        }
        ?>
    </div>

    <div class="app-alert app-alert-success">
        🤖 فرآیند کسر امتیاز و شارژ کیف پول <b>فوری و اتوماتیک</b> انجام می‌شود.
    </div>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="convert_points" value="1">
        <button type="submit" class="app-btn app-btn-success app-btn-lg" <?php echo ($current_points < 300) ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''; ?>>
            🔄 تبدیل و شارژ آنی کیف پول
        </button>
    </form>

    <div class="nav-footer-buttons">
        <a href="user_dashboard.php" class="app-btn app-btn-secondary">🔙 بازگشت به داشبورد</a>
    </div>
</div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
