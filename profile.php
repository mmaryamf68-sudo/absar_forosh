<?php

require_once 'db.php';

session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$query = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$query->execute([$user_id]);
$profile = $query->fetch();
?>

<style>
    .profile-card { max-width: 400px; margin: 20px auto; padding: 20px; background: #f9f9f9; border-radius: 10px; font-family: tahoma; }
    .item { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
    .label { font-weight: bold; color: #555; }
</style>

<div class="profile-card">
    <h2>اطلاعات کاربری</h2>
    <?php if ($profile): ?>
        <div class="item"><span class="label">نام:</span> <?php echo $profile['full_name']; ?></div>
        <div class="item"><span class="label">کد ملی:</span> <?php echo $profile['national_id']; ?></div>
        <div class="item"><span class="label">موبایل:</span> <?php echo $profile['phone']; ?></div>
        <div class="item"><span class="label">تاریخ تولد:</span> <?php echo $profile['birth_date']; ?></div>
        <a href="profile_edit.php" style="color:blue;">ویرایش اطلاعات</a>
    <?php else: ?>
        <p>اطلاعاتی یافت نشد.</p>
        <a href="profile_edit.php">تکمیل پروفایل</a>
    <?php endif; ?>
</div>