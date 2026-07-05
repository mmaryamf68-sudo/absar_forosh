<?php
require_once 'includes/auth.php';
require_once 'db.php';

init_secure_session();
$message = '';
$message_type = '';

$ref_from_url = isset($_GET['ref']) ? trim($_GET['ref']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $posted_ref = isset($_POST['submitted_ref_code']) ? trim($_POST['submitted_ref_code']) : '';

    if (empty($full_name) || empty($phone) || empty($password)) {
        $message = 'لطفاً همه فیلدها را پر کنید.';
        $message_type = 'error';
    } elseif (mb_strlen($full_name) > 100) {
        $message = '❌ نام و نام خانوادگی بیش از حد مجاز است.';
        $message_type = 'error';
    } elseif (!preg_match('/^09\d{9}$/', $phone)) {
        $message = '❌ فرمت شماره موبایل نامعتبر است (مثال: 09123456789).';
        $message_type = 'error';
    } elseif (strlen($password) < 8) {
        $message = '❌ رمز عبور باید حداقل ۸ کاراکتر باشد.';
        $message_type = 'error';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $referred_by_id = null;

            if (!empty($posted_ref)) {
                $stmt_find_ref = $pdo->prepare('SELECT id FROM pool_users WHERE referral_code = :ref_code');
                $stmt_find_ref->execute(['ref_code' => $posted_ref]);
                $referrer = $stmt_find_ref->fetch(PDO::FETCH_ASSOC);
                if ($referrer) {
                    $referred_by_id = intval($referrer['id']);
                }
            }

            $my_new_ref_code = generate_referral_code();

            $stmt = $pdo->prepare('INSERT INTO pool_users (full_name, phone, password, referral_code, referred_by) VALUES (:name, :phone, :pass, :my_ref, :ref_by)');
            $stmt->execute([
                'name'   => $full_name,
                'phone'  => $phone,
                'pass'   => $hashed_password,
                'my_ref' => $my_new_ref_code,
                'ref_by' => $referred_by_id,
            ]);

            $message = '✅ ثبت‌نام با موفقیت انجام شد. حالا می‌توانید وارد شوید.';
            $message_type = 'success';
            $ref_from_url = '';

        } catch (PDOException $e) {
            $message = '❌ این شماره موبایل قبلاً ثبت شده است.';
            $message_type = 'error';
        }
    }
}

$page_title = 'عضویت در سیستم استخر';
$layout_mode = 'auth';
$active_page = 'register';
require_once 'includes/layout_start.php';
?>

<div class="auth-box">
    <h2>📝 عضویت کاربر جدید</h2>
    <p class="auth-sub">عضویت در باشگاه مشتریان آبسار</p>

    <?php if (!empty($message)): ?>
        <div class="app-alert app-alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>"><?php echo h($message); ?></div>
    <?php endif; ?>

    <?php if (!empty($ref_from_url)): ?>
        <div class="ref-alert">🎉 شما توسط یکی از اعضای آبسار دعوت شده‌اید!</div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="text" name="full_name" class="app-input" placeholder="نام و نام خانوادگی" required maxlength="100">
        <input type="text" name="phone" class="app-input" placeholder="شماره موبایل (مثال: 09123456789)" required pattern="09[0-9]{9}">
        <input type="password" name="password" class="app-input" placeholder="رمز عبور (حداقل ۸ کاراکتر)" required minlength="8">
        <input type="text" name="submitted_ref_code" class="app-input ref-input" placeholder="کد معرف (اختیاری)" value="<?php echo h($ref_from_url); ?>" maxlength="20">
        <button type="submit" class="app-btn app-btn-success app-btn-block app-btn-lg">ثبت‌نام</button>
    </form>
    <a href="login.php" class="auth-link">قبلاً ثبت‌نام کرده‌اید؟ وارد شوید</a>
    <a href="index.php" class="auth-link">🏠 بازگشت به صفحه اصلی</a>
</div>

<?php require_once 'includes/layout_end.php'; ?>
