<?php
date_default_timezone_set('Asia/Tehran');

if (!function_exists('gregorian_to_jalali')) {
    function gregorian_to_jalali($gy, $gm, $gd)
    {
        $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

        $gy = (int) $gy - 1600;
        $gm = (int) $gm - 1;
        $gd = (int) $gd - 1;

        $g_day_no = 365 * $gy + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + (int)(($gy + 399) / 400);

        for ($i = 0; $i < $gm; ++$i) {
            $g_day_no += $g_days_in_month[$i];
        }

        if ($gm > 1 && (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0))) {
            $g_day_no++;
        }

        $g_day_no += $gd;

        $j_day_no = $g_day_no - 79;
        $j_np = (int)($j_day_no / 12053);
        $j_day_no %= 12053;

        $jy = 979 + 33 * $j_np + 4 * (int)($j_day_no / 1461);
        $j_day_no %= 1461;

        if ($j_day_no >= 366) {
            $jy += (int)(($j_day_no - 1) / 365);
            $j_day_no = ($j_day_no - 1) % 365;
        }

        for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i) {
            $j_day_no -= $j_days_in_month[$i];
        }

        return [$jy, $i + 1, $j_day_no + 1];
    }
}

if (!function_exists('jalali_to_gregorian')) {
    function jalali_to_gregorian($jy, $jm, $jd)
    {
        $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

        $jy = (int) $jy - 979;
        $jm = (int) $jm - 1;
        $jd = (int) $jd - 1;

        $j_day_no = 365 * $jy + (int)($jy / 33) * 8 + (int)((($jy % 33) + 3) / 4);

        for ($i = 0; $i < $jm; ++$i) {
            $j_day_no += $j_days_in_month[$i];
        }

        $j_day_no += $jd;

        $g_day_no = $j_day_no + 79;
        $gy = 1600 + 400 * (int)($g_day_no / 146097);
        $g_day_no %= 146097;

        $leap = true;
        if ($g_day_no >= 36525) {
            $g_day_no--;
            $gy += 100 * (int)($g_day_no / 36524);
            $g_day_no %= 36524;

            if ($g_day_no >= 365) {
                $g_day_no++;
            } else {
                $leap = false;
            }
        }

        $gy += 4 * (int)($g_day_no / 1461);
        $g_day_no %= 1461;

        if ($g_day_no >= 366) {
            $leap = false;
            $g_day_no--;
            $gy += (int)($g_day_no / 365);
            $g_day_no %= 365;
        }

        for ($i = 0; $i < 11; ++$i) {
            $month_days = $g_days_in_month[$i] + ($i === 1 && $leap ? 1 : 0);
            if ($g_day_no < $month_days) {
                break;
            }
            $g_day_no -= $month_days;
        }

        return [$gy, $i + 1, $g_day_no + 1];
    }
}

if (!function_exists('format_shamsi_date')) {
    function format_shamsi_date($jy, $jm, $jd)
    {
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }
}

if (!function_exists('get_today_shamsi')) {
    function get_today_shamsi()
    {
        list($jy, $jm, $jd) = gregorian_to_jalali(
            (int) date('Y'),
            (int) date('m'),
            (int) date('d')
        );

        return format_shamsi_date($jy, $jm, $jd);
    }
}

if (!function_exists('getCurrentShamsiDate')) {
    function getCurrentShamsiDate()
    {
        return get_today_shamsi();
    }
}

if (!function_exists('to_shamsi')) {
    function to_shamsi($gregorian_date)
    {
        if (empty($gregorian_date)) {
            return '';
        }

        if (strpos($gregorian_date, '/') !== false) {
            return $gregorian_date;
        }

        $timestamp = strtotime($gregorian_date);
        if ($timestamp === false) {
            return $gregorian_date;
        }

        list($jy, $jm, $jd) = gregorian_to_jalali(
            (int) date('Y', $timestamp),
            (int) date('m', $timestamp),
            (int) date('d', $timestamp)
        );

        return format_shamsi_date($jy, $jm, $jd);
    }
}

if (!function_exists('get_weekday_from_shamsi')) {
    function get_weekday_from_shamsi($shamsi_date)
    {
        $parts = explode('/', trim($shamsi_date));
        if (count($parts) !== 3) {
            return null;
        }

        list($gy, $gm, $gd) = jalali_to_gregorian($parts[0], $parts[1], $parts[2]);
        return (int) date('w', mktime(0, 0, 0, $gm, $gd, $gy));
    }
}

if (!function_exists('is_shamsi_friday')) {
    function is_shamsi_friday($shamsi_date)
    {
        return get_weekday_from_shamsi($shamsi_date) === 5;
    }
}

if (!function_exists('is_shamsi_saturday')) {
    function is_shamsi_saturday($shamsi_date)
    {
        return get_weekday_from_shamsi($shamsi_date) === 6;
    }
}
