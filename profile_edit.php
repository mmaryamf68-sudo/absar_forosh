<?php
require_once 'db.php'; // اتصال به فایل دیتابیس شما
session_start();

// بررسی لاگین بودن کاربر
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// اگر فرم ارسال شد
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $national_id = $_POST['national_id'];
    $phone = $_POST['phone'];
    $birth_date = $_POST['birth_date'];

    // بررسی وجود رکورد قبلی برای کاربر
    $check = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
    $check->execute([$user_id]);

    if ($check->rowCount() > 0) {
        // آپدیت کردن اطلاعات موجود
        $sql = "UPDATE user_profiles SET full_name = ?, national_id = ?, phone = ?, birth_date = ? WHERE user_id = ?";
        $pdo->prepare($sql)->execute([$full_name, $national_id, $phone, $birth_date, $user_id]);
    } else {
        // درج اطلاعات جدید
        $sql = "INSERT INTO user_profiles (user_id, full_name, national_id, phone, birth_date) VALUES (?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$user_id, $full_name, $national_id, $phone, $birth_date]);
    }
    
    header("Location: profile.php");
    exit;
}

// دریافت اطلاعات فعلی برای نمایش در کادرها
$query = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$query->execute([$user_id]);
$profile = $query->fetch();
?>

<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <style>
        .profile-form { max-width: 400px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; font-family: tahoma; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>

<div class="profile-form">
    <h2>ویرایش/تکمیل پروفایل</h2>
    <form method="POST">
        <input type="text" name="full_name" placeholder="نام و نام خانوادگی" value="<?php echo $profile['full_name'] ?? ''; ?>" required>
        <input type="text" name="national_id" placeholder="کد ملی" value="<?php echo $profile['national_id'] ?? ''; ?>" required>
        <input type="text" name="phone" placeholder="شماره موبایل" value="<?php echo $profile['phone'] ?? ''; ?>" required>
        <input type="text" name="birth_date" placeholder="تاریخ تولد (مثال: 1370/01/01)" value="<?php echo $profile['birth_date'] ?? ''; ?>">
        <button type="submit">ذخیره اطلاعات</button>
    </form>
</div>

</body>
</html>