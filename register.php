<?php
/**
 * صفحه ثبت‌نام کاربر جدید
 */

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

    // اعتبارسنجی‌ها
    if (empty($full_name) || empty($phone) || empty($password)) {
        $message = '❌ لطفاً همه فیلدها را پر کنید.';
        $message_type = 'error';
    } elseif (!validate_full_name($full_name)) {
        $message = '❌ نام باید بین 3 تا 100 کاراکتر باشد.';
        $message_type = 'error';
    } elseif (!validate_iranian_phone($phone)) {
        $message = '❌ فرمت شماره موبایل نامعتبر است. مثال: 09123456789';
        $message_type = 'error';
    } elseif (!validate_password($password)) {
        $message = '❌ رمز عبور باید حداقل 8 کاراکتر باشد.';
        $message_type = 'error';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $referred_by_id = null;

            // بررسی کد معرف اگر وجود دارد
            if (!empty($posted_ref)) {
                $stmt_find_ref = $pdo->prepare('SELECT id FROM pool_users WHERE referral_code = :ref_code');
                $stmt_find_ref->execute(['ref_code' => $posted_ref]);
                $referrer = $stmt_find_ref->fetch(PDO::FETCH_ASSOC);
                if ($referrer) {
                    $referred_by_id = intval($referrer['id']);
                }
            }

            // تولید کد معرف برای کاربر جدید
            $my_new_ref_code = generate_referral_code();

            // ثبت کاربر جدید
            $stmt = $pdo->prepare('INSERT INTO pool_users (full_name, phone, password, referral_code, referred_by) VALUES (:name, :phone, :pass, :my_ref, :ref_by)');
            $stmt->execute([
                'name'   => $full_name,
                'phone'  => $phone,
                'pass'   => $hashed_password,
                'my_ref' => $my_new_ref_code,
                'ref_by' => $referred_by_id,
            ]);

            $message = '✅ ثبت‌نام با موفقیت انجام شد! اکنون می‌توانید وارد شوید.';
            $message_type = 'success';
            $ref_from_url = '';

            // پاک کردن فرم
            $_POST = [];

        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $message = '❌ این شماره موبایل قبلاً ثبت شده است.';
            } else {
                $message = '❌ خطا در ثبت‌نام: ' . $e->getMessage();
            }
            $message_type = 'error';
        }
    }
}

$page_title = 'ثبت‌نام کاربر جدید';
$layout_mode = 'auth';
$active_page = 'register';
require_once 'includes/layout_start.php';
?>

<div class="auth-box">
    <h2>📝 ثبت‌نام کاربر جدید</h2>
    <p class="auth-sub">عضویت در باشگاه مشتریان استخر آبسار</p>

    <?php if (!empty($message)): ?>
        <div class="app-alert app-alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
            <?php echo h($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($ref_from_url)): ?>
        <div class="app-alert app-alert-info">
            🎉 شما توسط یکی از اعضای آبسار دعوت شدید!
        </div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>
        
        <input type="text" name="full_name" class="app-input" 
               placeholder="نام و نام خانوادگی" 
               required maxlength="100" minlength="3"
               value="<?php echo h($_POST['full_name'] ?? ''); ?>">
        
        <input type="text" name="phone" class="app-input" 
               placeholder="شماره موبایل (09123456789)" 
               required pattern="09\d{9}" title="فرمت: 09123456789"
               value="<?php echo h($_POST['phone'] ?? ''); ?>">
        
        <input type="password" name="password" class="app-input" 
               placeholder="رمز عبور (حداقل 8 کاراکتر)" 
               required minlength="8">
        
        <input type="text" name="submitted_ref_code" class="app-input" 
               placeholder="کد معرف (اختیاری)" 
               maxlength="20"
               value="<?php echo h($ref_from_url); ?>">
        
        <button type="submit" class="app-btn app-btn-success app-btn-block app-btn-lg">
            ثبت‌نام
        </button>
    </form>

    <hr style="margin: 20px 0; border: none; border-top: 1px solid var(--border-color);">

    <a href="login.php" class="auth-link">قبلاً ثبت‌نام کرده‌اید؟ وارد شوید</a>
    <a href="index.php" class="auth-link">🏠 بازگشت به صفحه اصلی</a>
</div>

<?php require_once 'includes/layout_end.php'; ?>
