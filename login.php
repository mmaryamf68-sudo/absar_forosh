<?php
require_once 'includes/auth.php';
require_once 'db.php';

init_secure_session();
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $remaining = login_lockout_remaining('user_login');
    if ($remaining > 0) {
        $minutes = (int) ceil($remaining / 60);
        $message = "❌ تعداد تلاش‌های ناموفق زیاد است. لطفاً {$minutes} دقیقه دیگر تلاش کنید.";
    } elseif (!check_login_rate_limit('user_login')) {
        $message = '❌ تعداد تلاش‌های ناموفق زیاد است. لطفاً بعداً تلاش کنید.';
    } else {
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^09\d{9}$/', $phone)) {
            $message = '❌ فرمت شماره موبایل نامعتبر است.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM pool_users WHERE phone = :phone');
            $stmt->execute(['phone' => $phone]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                clear_login_attempts('user_login');
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_type'] = $user['user_type'];
                header('Location: user_dashboard.php');
                exit;
            }

            record_failed_login('user_login');
            $message = '❌ شماره موبایل یا رمز عبور اشتباه است.';
        }
    }
}

$page_title = 'ورود به پنل کاربری';
$layout_mode = 'auth';
$active_page = 'login';
require_once 'includes/layout_start.php';
?>

<div class="auth-box">
    <h2>🔑 ورود به پنل استخر</h2>
    <p class="auth-sub">با شماره موبایل و رمز عبور وارد حساب خود شوید</p>

    <?php if (!empty($message)): ?>
        <div class="app-alert app-alert-error"><?php echo h($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="text" name="phone" class="app-input" placeholder="شماره موبایل" required autocomplete="username">
        <input type="password" name="password" class="app-input" placeholder="رمز عبور" required autocomplete="current-password">
        <button type="submit" class="app-btn app-btn-primary app-btn-block app-btn-lg">ورود به حساب</button>
    </form>
    <a href="register.php" class="auth-link">حساب کاربری ندارید؟ ثبت‌نام کنید</a>
    <a href="index.php" class="auth-link">🏠 بازگشت به صفحه اصلی</a>
</div>

<?php require_once 'includes/layout_end.php'; ?>
