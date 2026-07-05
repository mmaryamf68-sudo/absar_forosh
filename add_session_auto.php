<?php
require_once 'includes/auth.php';
require_once 'db.php';

require_admin();

set_time_limit(0);

// تابع کمکی استاندارد و کالیبره‌شده برای تشخیص دقیق روز هفته در تاریخ شمسی
function getJalaliDayOfWeek($jy, $jm, $jd) {
    // تبدیل دقیق تاریخ جلالی به روزهای سپری شده از مبدا زمان برای کالیبراسیون ۱۴۰۰ به بالا
    $jy_calculated = $jy - 979;
    $jm_calculated = $jm - 1;
    
    $jalali_days = 365 * $jy_calculated + intval($jy_calculated / 33) * 8 + intval(($jy_calculated % 33 + 3) / 4);
    
    for ($i = 0; $i < $jm_calculated; ++$i) {
        $jalali_days += ($i < 6) ? 31 : 30;
    }
    
    $jalali_days += $jd - 1;
    
    // کالیبره کردن نهایی روز هفته (0 = شنبه، 1 = یکشنبه، ...، 6 = جمعه)
    $wday = ($jalali_days + 2) % 7; 
    
    // تبدیل خروجی به فرمت کد شما: (6 = شنبه، 5 = جمعه، 0 = یکشنبه، 1 = دوشنبه، 2 = سه‌شنبه، 3 = چهارشنبه، 4 = پنجشنبه)
    $conversion_map = [6, 0, 1, 2, 3, 4, 5];
    return $conversion_map[$wday];
}

$current_year = isset($_GET['year']) ? intval($_GET['year']) : 1405;

echo "<div style='direction:rtl; font-family:tahoma; padding:20px; line-height:30px; text-align:right;'>";

try {
    if ($current_year == 1405 && !isset($_GET['step'])) {
        $pdo->query("TRUNCATE TABLE sessionsone");
        echo "<b style='color:blue;'>✔️ دیتابیس تخلیه شد. شروع فرآیند ساخت سانس‌های هوشمند تایم آزاد...</b><br>";
        header("Refresh: 2; url=add_session_auto.php?year=1405&step=run");
        echo "⏳ لطفا منتظر بمانید...";
        exit;
    }

    if ($current_year <= 1405) { // برای اینکه تا سال ۱۴۱۰ بیخود طول نکشه روی همین امسال قفلش کردم
        echo "<h3>⏳ در حال تنظیم سانس‌های سال <span style='color:orange;'>{$current_year}</span> ...</h3>";

        $stmt = $pdo->prepare("INSERT INTO sessionsone (date_shamsi, time_start, time_end, gender_type, max_capacity, reserved_count) VALUES (:date, :start, :end, :gender, 100, 0)");

        for ($month = 1; $month <= 12; $month++) {
            // ماه ۱۲ در سال ۱۴۰۵ کبیسه نیست و ۲۹ روزه است
            $max_days = ($month <= 6) ? 31 : (($month <= 11) ? 30 : 29);
            
            for ($day = 1; $day <= $max_days; $day++) {
                $m_str = sprintf("%02d", $month);
                $d_str = sprintf("%02d", $day);
                $date_shamsi = "{$current_year}/{$m_str}/{$d_str}";

                // تشخیص روز هفته: 6 = شنبه، 5 = جمعه
                $day_of_week = getJalaliDayOfWeek($current_year, $month, $day);

                if ($day_of_week == 6) { 
                    // 🚨 روز شنبه: بانوان کلاً تعطیل است -> فقط سانس آقایان ثبت می‌شود
                    $stmt->execute(['date' => $date_shamsi, 'start' => '18:00', 'end' => '23:30', 'gender' => 'male']);
                } 
                elseif ($day_of_week == 5) { 
                    // 🔄 روز جمعه: تایم‌ها برعکس می‌شود
                    // آقایان صبح تا ظهر
                    $stmt->execute(['date' => $date_shamsi, 'start' => '09:00', 'end' => '13:00', 'gender' => 'male']);
                    // بانوان عصر تا شب
                    $stmt->execute(['date' => $date_shamsi, 'start' => '14:00', 'end' => '22:00', 'gender' => 'female']);
                } 
                else { 
                    // 📅 روزهای عادی هفته (یکشنبه تا پنجشنبه)
                    // بانوان صبح تا عصر
                    $stmt->execute(['date' => $date_shamsi, 'start' => '09:00', 'end' => '17:00', 'gender' => 'female']);
                    // آقایان عصر تا شب
                    $stmt->execute(['date' => $date_shamsi, 'start' => '18:00', 'end' => '23:30', 'gender' => 'male']);
                }
            }
        }

        $next_year = $current_year + 1;
        echo "<b style='color:green;'>✔️ سانس‌های سال {$current_year} با موفقیت و تقویم دقیق اعمال شدند.</b><br>";
        header("Refresh: 1; url=add_session_auto.php?year={$next_year}&step=run");
        exit;
    } else {
        echo "<h2 style='color:green; text-align:center;'>🎉 عالی شد! کل دیتابیس با قانون ج
        دید و تقویم دقیق شمسی تنظیم شد.</h2>";
        echo "<center><a href='reserve.php' style='padding:12px 25px; background:#10b981; color:white; border-radius:6px; text-decoration:none; font-weight:bold;'>🏃‍♂️ ورود به صفحه خرید بلیت</a></center>";
    }

} catch (Exception $e) {
    error_log('add_session_auto error: ' . $e->getMessage());
    echo '<h3 style="color:red;">❌ خطا:</h3> ' . h(APP_DEBUG ? $e->getMessage() : 'عملیات با خطا مواجه شد.');
}

echo "</div>";
?>