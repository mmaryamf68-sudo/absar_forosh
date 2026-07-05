<?php
require_once 'includes/auth.php';

init_secure_session();
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>ورود مدیریت استخر</title>
</head>
<body>
    <h2>پنل مدیریت - ورود</h2>
    <form action="admin_auth.php" method="POST">
        <?php echo csrf_field(); ?>
        <input type="email" name="email" placeholder="ایمیل ادمین" required autocomplete="username">
        <input type="password" name="password" placeholder="رمز عبور" required autocomplete="current-password">
        <button type="submit">ورود</button>
    </form>
</body>
</html>
