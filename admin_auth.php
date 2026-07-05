<?php
require_once 'includes/auth.php';
require_once 'db.php';

init_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_login.php');
    exit;
}

csrf_require();

$remaining = login_lockout_remaining('admin_login');
if ($remaining > 0) {
    $minutes = (int) ceil($remaining / 60);
    echo h("تعداد تلاش‌های ناموفق زیاد است. لطفاً {$minutes} دقیقه دیگر تلاش کنید.") . " <a href='admin_login.php'>بازگشت</a>";
    exit;
}

if (!check_login_rate_limit('admin_login')) {
    echo h('تعداد تلاش‌های ناموفق زیاد است.') . " <a href='admin_login.php'>بازگشت</a>";
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ?');
$stmt->execute([$email]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin && password_verify($password, $admin['password'])) {
    session_regenerate_id(true);
    clear_login_attempts('admin_login');
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    header('Location: admin_dashboard.php');
    exit;
}

record_failed_login('admin_login');
echo h('ایمیل یا رمز عبور اشتباه است.') . " <a href='admin_login.php'>بازگشت به صفحه ورود</a>";
exit;
