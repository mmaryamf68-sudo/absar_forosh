<?php
require_once 'includes/auth.php';
require_once 'db.php';

init_secure_session();

// AJAX — آمار زنده ظرفیت (فقط ادمین)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'live_stats') {
    require_admin(true);
    header('Content-Type: application/json; charset=utf-8');
    $stats_date = isset($_GET['stats_date']) ? trim($_GET['stats_date']) : '';
    if ($stats_date === '' || !is_valid_shamsi_date($stats_date)) {
        $stats_date = $pdo->query("SELECT date_shamsi FROM sessionsone ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '';
    }
    $stmt = $pdo->prepare("
        SELECT s.id, s.date_shamsi, s.time_start, s.time_end, s.gender_type,
               s.max_capacity, s.reserved_count,
               (s.max_capacity - s.reserved_count) AS remaining,
               (SELECT COUNT(*) FROM ticket_serials ts
                JOIN vouchers v ON ts.voucher_id = v.id
                WHERE v.session_id = s.id AND ts.status = 'used') AS entered_count
        FROM sessionsone s
        WHERE s.date_shamsi = :d
        ORDER BY s.time_start ASC
    ");
    $stmt->execute(['d' => $stats_date]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totals = ['max' => 0, 'reserved' => 0, 'remaining' => 0, 'entered' => 0];
    foreach ($sessions as $row) {
        $totals['max'] += intval($row['max_capacity']);
        $totals['reserved'] += intval($row['reserved_count']);
        $totals['remaining'] += max(0, intval($row['remaining']));
        $totals['entered'] += intval($row['entered_count']);
    }
    echo json_encode([
        'date' => $stats_date,
        'sessions' => $sessions,
        'totals' => $totals,
        'updated_at' => date('H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_admin();

// تعیین تب فعال (بخش پیش‌فرض: بررسی ووچر)
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'check_voucher';

$message = "";
$voucher_data = null;
$tickets = [];
$search_code = "";
$highlight_serial = "";

// ۱. پردازش ابطال بلیت (مربوط به تب بررسی ووچر)
if (isset($_POST['action_check'])) {
    csrf_require();
    $tab = 'check_voucher';
    $ticket_id = intval($_POST['ticket_id']);
    $search_code = trim($_POST['search_code'] ?? '');
    $highlight_serial = trim($_POST['highlight_serial'] ?? '');
    if ($ticket_id > 0) {
        $stmt = $pdo->prepare("UPDATE ticket_serials SET status = 'used', used_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $ticket_id]);
        $message = "🔹 بلیت با موفقیت باطل شد و ورود ثبت گردید.";
    }
}

function adminCheckLookup($pdo, $code) {
    $code = trim($code);
    if ($code === '') {
        return ['voucher' => null, 'tickets' => [], 'highlight_serial' => '', 'error' => ''];
    }

    $stmt = $pdo->prepare("
        SELECT v.*, s.date_shamsi, s.time_start, s.time_end, s.gender_type, s.max_capacity, s.reserved_count
        FROM vouchers v
        JOIN sessionsone s ON v.session_id = s.id
        WHERE v.voucher_code = :v_code
    ");
    $stmt->execute(['v_code' => $code]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        $stmt_s = $pdo->prepare("
            SELECT v.*, s.date_shamsi, s.time_start, s.time_end, s.gender_type, s.max_capacity, s.reserved_count, ts.serial_code
            FROM ticket_serials ts
            JOIN vouchers v ON ts.voucher_id = v.id
            JOIN sessionsone s ON v.session_id = s.id
            WHERE ts.serial_code = :s_code
        ");
        $stmt_s->execute(['s_code' => $code]);
        $row = $stmt_s->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $highlight = $row['serial_code'];
            unset($row['serial_code']);
            $voucher = $row;
            $stmt_t = $pdo->prepare("SELECT * FROM ticket_serials WHERE voucher_id = :v_id");
            $stmt_t->execute(['v_id' => $voucher['id']]);
            return [
                'voucher' => $voucher,
                'tickets' => $stmt_t->fetchAll(PDO::FETCH_ASSOC),
                'highlight_serial' => $highlight,
                'error' => ''
            ];
        }
    }

    if (!$voucher) {
        return ['voucher' => null, 'tickets' => [], 'highlight_serial' => '', 'error' => '❌ کد وارد شده اشتباه است یا در سیستم وجود ندارد.'];
    }

    $stmt_t = $pdo->prepare("SELECT * FROM ticket_serials WHERE voucher_id = :v_id");
    $stmt_t->execute(['v_id' => $voucher['id']]);
    return [
        'voucher' => $voucher,
        'tickets' => $stmt_t->fetchAll(PDO::FETCH_ASSOC),
        'highlight_serial' => '',
        'error' => ''
    ];
}

// ۲. پردازش استعلام ووچر / سریال (دستی، لینک QR یا GET)
if ($tab == 'check_voucher') {
    $lookup_code = '';
    if (isset($_GET['voucher_code'])) {
        $lookup_code = trim($_GET['voucher_code']);
    } elseif (isset($_GET['serial_code'])) {
        $lookup_code = trim($_GET['serial_code']);
    } elseif (!empty($search_code)) {
        $lookup_code = $search_code;
    }

    if ($lookup_code !== '') {
        $search_code = $lookup_code;
        $saved_highlight = $highlight_serial;
        $result = adminCheckLookup($pdo, $lookup_code);
        $voucher_data = $result['voucher'];
        $tickets = $result['tickets'];
        $highlight_serial = $result['highlight_serial'] !== '' ? $result['highlight_serial'] : $saved_highlight;
        if (!$voucher_data && $result['error'] !== '') {
            $message = $result['error'];
        }
    }
}

// ۳. واکشی اطلاعات برای تب‌های دیگر
$all_vouchers = [];
$filter_date = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';
if ($filter_date !== '' && !is_valid_shamsi_date($filter_date)) {
    $filter_date = '';
}

if ($tab == 'all_reserves') {
    // لیست تمام رزروها به همراه مشخصات سانس
    $query = "SELECT v.*, s.date_shamsi, s.time_start, s.time_end, s.gender_type 
              FROM vouchers v 
              JOIN sessionsone s ON v.session_id = s.id";
    
    // اگر بر اساس تاریخ هم فیلتر شده بود
    if (!empty($filter_date)) {
        $query .= " WHERE s.date_shamsi = :f_date";
    }
    $query .= " ORDER BY v.id DESC";
    
    $stmt = $pdo->prepare($query);
    if (!empty($filter_date)) {
        $stmt->execute(['f_date' => $filter_date]);
    } else {
        $stmt->execute();
    }
    $all_vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ۴. بخش پرداخت‌ها
$payments = [];
if ($tab == 'payments') {
    $stmt = $pdo->query("SELECT v.voucher_code, v.user_name, v.ticket_count, v.created_at, (v.ticket_count * 150000) as total_price 
                         FROM vouchers v ORDER BY v.id DESC");
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ۵. آمار زنده ظرفیت
$stats_date = isset($_GET['stats_date']) ? trim($_GET['stats_date']) : '';
if ($stats_date === '' || !is_valid_shamsi_date($stats_date)) {
    $stats_date = $pdo->query("SELECT date_shamsi FROM sessionsone ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '';
}
$live_stats = [];
$live_totals = ['max' => 0, 'reserved' => 0, 'remaining' => 0, 'entered' => 0];
$stats_dates = $pdo->query("SELECT DISTINCT date_shamsi FROM sessionsone ORDER BY date_shamsi DESC")->fetchAll(PDO::FETCH_COLUMN);

if ($stats_date !== '') {
    $stmt_live = $pdo->prepare("
        SELECT s.id, s.date_shamsi, s.time_start, s.time_end, s.gender_type,
               s.max_capacity, s.reserved_count,
               (s.max_capacity - s.reserved_count) AS remaining,
               (SELECT COUNT(*) FROM ticket_serials ts
                JOIN vouchers v ON ts.voucher_id = v.id
                WHERE v.session_id = s.id AND ts.status = 'used') AS entered_count
        FROM sessionsone s
        WHERE s.date_shamsi = :d
        ORDER BY s.time_start ASC
    ");
    $stmt_live->execute(['d' => $stats_date]);
    $live_stats = $stmt_live->fetchAll(PDO::FETCH_ASSOC);
    foreach ($live_stats as $row) {
        $live_totals['max'] += intval($row['max_capacity']);
        $live_totals['reserved'] += intval($row['reserved_count']);
        $live_totals['remaining'] += max(0, intval($row['remaining']));
        $live_totals['entered'] += intval($row['entered_count']);
    }
}

$today_vouchers = $pdo->query("SELECT COUNT(*) FROM vouchers WHERE DATE(created_at) = CURDATE()")->fetchColumn();

$page_titles = [
    'check_voucher' => 'چک ووچر و گیت ورود',
    'all_reserves'  => 'لیست رزروها',
    'payments'      => 'گزارش پرداخت‌ها',
];
$page_title = $page_titles[$tab] ?? 'پنل مدیریت';
$alert_type = (!empty($message) && strpos($message, '❌') !== false) ? 'error' : 'success';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — مدیریت آبسار</title>
    <link rel="stylesheet" href="assets/css/admin_theme.css">
</head>
<body class="admin-app">

<aside class="admin-sidebar">
    <div class="admin-brand">
        <div class="admin-brand-logo">🏊</div>
        <div>
            <h1>پارک آبی آبسار</h1>
            <p>پنل مدیریت و پذیرش</p>
        </div>
    </div>
    <nav class="admin-nav">
        <div class="admin-nav-label">عملیات</div>
        <a href="?tab=check_voucher" class="<?php echo $tab == 'check_voucher' ? 'active' : ''; ?>">
            <span class="nav-icon">🛂</span> چک ووچر و گیت
        </a>
        <a href="?tab=all_reserves" class="<?php echo $tab == 'all_reserves' ? 'active' : ''; ?>">
            <span class="nav-icon">📋</span> لیست رزروها
        </a>
        <a href="?tab=payments" class="<?php echo $tab == 'payments' ? 'active' : ''; ?>">
            <span class="nav-icon">💰</span> گزارش پرداخت‌ها
        </a>
        <div class="admin-nav-label">تنظیمات</div>
        <a href="add_session_auto.php">
            <span class="nav-icon">⚙️</span> تولید انبوه سانس
        </a>
        <a href="admin_dashboard.php">
            <span class="nav-icon">📊</span> داشبورد اصلی
        </a>
    </nav>
    <div class="admin-sidebar-footer">
        <a href="admin_logout.php">🚪 خروج از پنل</a>
    </div>
</aside>

<div class="admin-main">
    <header class="admin-topbar">
        <h2><?php echo htmlspecialchars($page_title); ?></h2>
        <div class="admin-topbar-meta">
            <span class="live-pulse">آمار زنده فعال</span>
            <span style="font-size:12px;color:var(--admin-muted);"><?php echo date('Y/m/d H:i'); ?></span>
        </div>
    </header>

    <main class="admin-content">
        <?php if (!empty($message)): ?>
            <div class="admin-alert admin-alert-<?php echo $alert_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

    <div class="live-panel" id="live_stats_panel">
        <div class="live-panel-head">
            <div>
                <h3>📊 آمار زنده ظرفیت</h3>
             
            </div>
            <span class="live-meta">🔄 <span id="live_updated_at"><?php echo date('H:i:s'); ?></span></span>
        </div>
        <form method="GET" class="stats-date-form" style="margin-bottom:16px;">
            <?php if ($tab !== 'check_voucher'): ?><input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>"><?php endif; ?>
            <?php if ($tab === 'check_voucher' && !empty($search_code)): ?><input type="hidden" name="voucher_code" value="<?php echo htmlspecialchars($search_code); ?>"><?php endif; ?>
            <?php if ($tab === 'all_reserves' && !empty($filter_date)): ?><input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>"><?php endif; ?>
            
            <select name="stats_date" onchange="this.form.submit()">
                <?php foreach ($stats_dates as $d): ?>
                    <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $d === $stats_date ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="live-summary" id="live_summary">
            <div class="live-sum-card"><span>ظرفیت کل</span><strong id="sum_max"><?php echo number_format($live_totals['max']); ?></strong></div>
            <div class="live-sum-card"><span>رزرو شده</span><strong id="sum_reserved"><?php echo number_format($live_totals['reserved']); ?></strong></div>
            <div class="live-sum-card"><span>باقی‌مانده</span><strong id="sum_remaining"><?php echo number_format($live_totals['remaining']); ?></strong></div>
            <div class="live-sum-card"><span>ورود ثبت‌شده</span><strong id="sum_entered"><?php echo number_format($live_totals['entered']); ?></strong></div>
            <div class="live-sum-card"><span>خرید امروز</span><strong id="sum_today"><?php echo number_format($today_vouchers); ?></strong></div>
        </div>
        <div class="live-sessions" id="live_sessions">
            <?php if (empty($live_stats)): ?>
                <p class="live-empty">سانسی برای این تاریخ ثبت نشده است.</p>
            <?php else: ?>
                <?php foreach ($live_stats as $s):
                    $rem = max(0, intval($s['remaining']));
                    $gender = in_array(strtolower(trim($s['gender_type'])), ['female', 'women', 'woman'], true) ? 'بانوان' : 'آقایان';
                    $timeLabel = ($s['time_start'] === 'سانس آزاد') ? 'سانس آزاد' : $s['time_start'] . ' تا ' . $s['time_end'];
                ?>
                <div class="live-session <?php echo $rem <= 0 ? 'full' : ''; ?>">
                    <h4><?php echo htmlspecialchars($timeLabel); ?> — <?php echo $gender; ?></h4>
                    <div class="nums"><span>ظرفیت: <b><?php echo intval($s['max_capacity']); ?></b></span><span>رزرو: <b><?php echo intval($s['reserved_count']); ?></b></span></div>
                    <div class="nums"><span>باقی: <b><?php echo $rem; ?></b></span><span>ورود: <b><?php echo intval($s['entered_count']); ?></b></span></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($tab == 'check_voucher') { ?>
    <div class="admin-card">
        <div class="qr-panel">
            <button type="button" class="btn btn-qr" id="btn_toggle_qr">📷 اسکن QR بلیت</button>
            <p>QR ووچر یا سریال را با دوربین بخوانید — استعلام خودکار انجام می‌شود</p>
            <div id="qr-reader"></div>
        </div>

        <form method="GET" action="">
            <input type="hidden" name="tab" value="check_voucher">
            <div class="search-toolbar">
                <input type="text" name="voucher_code" id="search_code_input" class="form-control" placeholder="کد ووچر (VCH-...) یا سریال (SRL-...)" value="<?php echo htmlspecialchars($search_code); ?>">
                <button type="submit" class="btn btn-primary">🔍 استعلام</button>
            </div>
        </form>

        <?php if ($voucher_data) {
            $rem_session = max(0, intval($voucher_data['max_capacity']) - intval($voucher_data['reserved_count']));
            $gender_label = in_array(strtolower(trim($voucher_data['gender_type'])), ['female', 'women'], true) ? 'بانوان' : 'آقایان';
            $time_label = $voucher_data['time_start'] . ($voucher_data['time_end'] ? ' تا ' . $voucher_data['time_end'] : '');
        ?>
            <div class="voucher-info-grid">
                <div class="info-chip"><label>خریدار</label><span><?php echo htmlspecialchars($voucher_data['user_name']); ?></span></div>
                <div class="info-chip"><label>کد ووچر</label><code><?php echo htmlspecialchars($voucher_data['voucher_code']); ?></code></div>
                <div class="info-chip"><label>تاریخ سانس</label><span><?php echo htmlspecialchars($voucher_data['date_shamsi']); ?></span></div>
                <div class="info-chip"><label>ساعت</label><span><?php echo htmlspecialchars($time_label); ?></span></div>
                <div class="info-chip"><label>نوع سانس</label><span><?php echo $gender_label; ?></span></div>
                <div class="info-chip"><label>ظرفیت باقی</label><span><?php echo $rem_session; ?> نفر</span></div>
            </div>

            <div class="ticket-list">
                <h4>🎫 سریال‌های بلیط</h4>
                <?php foreach ($tickets as $t) {
                    $isHighlight = ($highlight_serial !== '' && $t['serial_code'] === $highlight_serial);
                ?>
                    <div class="ticket-row <?php echo $isHighlight ? 'serial-highlight' : ''; ?>">
                        <span>🔑 <code><?php echo htmlspecialchars($t['serial_code']); ?></code><?php echo $isHighlight ? ' <strong style="color:#b45309;">(اسکن‌شده)</strong>' : ''; ?></span>
                        <div class="ticket-actions">
                            <?php if ($t['status'] == 'valid') { ?>
                                <span class="status-valid">🟢 معتبر</span>
                                <form method="POST" action="" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                    <input type="hidden" name="search_code" value="<?php echo htmlspecialchars($search_code); ?>">
                                    <input type="hidden" name="highlight_serial" value="<?php echo htmlspecialchars($highlight_serial); ?>">
                                    <button type="submit" name="action_check" class="btn btn-success btn-sm">👉 ورود و ابطال</button>
                                </form>
                            <?php } else { ?>
                                <span class="status-used">🔴 باطل — <?php echo date('H:i', strtotime($t['used_at'])); ?></span>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php } ?>

    <?php if ($tab == 'all_reserves') { ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>📋 لیست تمام رزروها</h3>
            <span class="admin-card-sub"><?php echo count($all_vouchers); ?> رزرو</span>
        </div>
        <form method="GET" action="" class="filter-bar">
            <input type="hidden" name="tab" value="all_reserves">
            <input type="text" name="filter_date" class="form-control" placeholder="فیلتر تاریخ (1405/03/22)" value="<?php echo htmlspecialchars($filter_date); ?>">
            <button type="submit" class="btn btn-primary">🔍 فیلتر</button>
            <?php if (!empty($filter_date)): ?>
                <a href="?tab=all_reserves" class="filter-clear">❌ حذف فیلتر</a>
            <?php endif; ?>
        </form>

        <?php if (!empty($all_vouchers)) { ?>
            <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>کد ووچر</th>
                        <th>نام خریدار</th>
                        <th>تاریخ سانس</th>
                        <th>ساعت</th>
                        <th>جنسیت</th>
                        <th>تعداد بلیط</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_vouchers as $v) { ?>
                        <tr>
                            <td><span class="voucher-link"><?php echo htmlspecialchars($v['voucher_code']); ?></span></td>
                            <td><?php echo htmlspecialchars($v['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($v['date_shamsi']); ?></td>
                            <td><?php echo ($v['time_start'] == 'سانس آزاد') ? 'سانس آزاد' : htmlspecialchars($v['time_start'] . ' تا ' . $v['time_end']); ?></td>
                            <td>
                                <span class="badge <?php echo in_array(strtolower(trim($v['gender_type'])), ['male', 'men'], true) ? 'bg-male' : 'bg-female'; ?>">
                                    <?php echo in_array(strtolower(trim($v['gender_type'])), ['male', 'men'], true) ? 'آقایان' : 'بانوان'; ?>
                                </span>
                            </td>
                            <td><strong><?php echo intval($v['ticket_count']); ?></strong> نفر</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        <?php } else { ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>هیچ رزروی یافت نشد.</p>
            </div>
        <?php } ?>
    </div>
    <?php } ?>

    <?php if ($tab == 'payments') { ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>💰 گزارش مالی</h3>
            <span class="admin-card-sub">مبلغ پایه هر بلیط: 450,000 تومان</span>
        </div>
        <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>کد ووچر مربوطه</th>
                    <th>پرداخت کننده</th>
                    <th>تعداد بلیت</th>
                    <th>مبلغ پرداختی</th>
                    <th>تاریخ و ساعت خرید</th>
                    <th>وضعیت تراکنش</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grand_total = 0;
                foreach ($payments as $p) { 
                    $grand_total += $p['total_price'];
                ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($p['voucher_code']); ?></code></td>
                        <td><?php echo htmlspecialchars($p['user_name']); ?></td>
                        <td><?php echo intval($p['ticket_count']); ?> نفر</td>
                        <td><strong><?php echo number_format($p['total_price']); ?> تومان</strong></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($p['created_at'])); ?></td>
                        <td><span class="status-valid">✅ موفق</span></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">💵 جمع کل درآمد</td>
                    <td style="color:var(--admin-success);"><?php echo number_format($grand_total); ?> تومان</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    <?php } ?>

    </main>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function() {
    const statsDate = <?php echo json_encode($stats_date, JSON_UNESCAPED_UNICODE); ?>;
    const currentTab = <?php echo json_encode($tab, JSON_UNESCAPED_UNICODE); ?>;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    }

    function genderLabel(type) {
        const t = String(type || '').toLowerCase();
        return (t === 'female' || t === 'women' || t === 'woman') ? 'بانوان' : 'آقایان';
    }

    function refreshLiveStats() {
        const url = 'admin_check.php?ajax=live_stats&stats_date=' + encodeURIComponent(statsDate);
        fetch(url)
            .then(r => r.json())
            .then(data => {
                document.getElementById('live_updated_at').textContent = data.updated_at || '';
                document.getElementById('sum_max').textContent = Number(data.totals.max || 0).toLocaleString('fa-IR');
                document.getElementById('sum_reserved').textContent = Number(data.totals.reserved || 0).toLocaleString('fa-IR');
                document.getElementById('sum_remaining').textContent = Number(data.totals.remaining || 0).toLocaleString('fa-IR');
                document.getElementById('sum_entered').textContent = Number(data.totals.entered || 0).toLocaleString('fa-IR');

                const box = document.getElementById('live_sessions');
                if (!data.sessions || !data.sessions.length) {
                    box.innerHTML = '<p class="live-empty">سانسی برای این تاریخ ثبت نشده است.</p>';
                    return;
                }
                box.innerHTML = data.sessions.map(s => {
                    const rem = Math.max(0, parseInt(s.remaining, 10) || 0);
                    const timeLabel = s.time_start === 'سانس آزاد' ? 'سانس آزاد' : escapeHtml(s.time_start) + ' تا ' + escapeHtml(s.time_end);
                    return '<div class="live-session ' + (rem <= 0 ? 'full' : '') + '">' +
                        '<h4>' + timeLabel + ' — ' + escapeHtml(genderLabel(s.gender_type)) + '</h4>' +
                        '<div class="nums"><span>ظرفیت: <b>' + escapeHtml(s.max_capacity) + '</b></span><span>رزرو: <b>' + escapeHtml(s.reserved_count) + '</b></span></div>' +
                        '<div class="nums"><span>باقی: <b>' + rem + '</b></span><span>ورود: <b>' + escapeHtml(s.entered_count) + '</b></span></div>' +
                        '</div>';
                }).join('');
            })
            .catch(() => {});
    }

    setInterval(refreshLiveStats, 15000);

    if (currentTab !== 'check_voucher') return;

    let qrScanner = null;
    let qrActive = false;

    function navigateFromQr(decodedText) {
        const text = (decodedText || '').trim();
        if (!text) return;

        try {
            const url = new URL(text);
            const vc = url.searchParams.get('voucher_code');
            if (vc) {
                window.location.href = 'admin_check.php?voucher_code=' + encodeURIComponent(vc);
                return;
            }
        } catch (e) {}

        if (/^SRL-/i.test(text)) {
            window.location.href = 'admin_check.php?serial_code=' + encodeURIComponent(text);
        } else {
            window.location.href = 'admin_check.php?voucher_code=' + encodeURIComponent(text);
        }
    }

    const btnQr = document.getElementById('btn_toggle_qr');
    const qrReader = document.getElementById('qr-reader');

    if (btnQr && qrReader && typeof Html5Qrcode !== 'undefined') {
        btnQr.addEventListener('click', function() {
            if (qrActive) {
                if (qrScanner) {
                    qrScanner.stop().then(() => {
                        qrScanner.clear();
                        qrScanner = null;
                    }).catch(() => {});
                }
                qrReader.classList.remove('active');
                btnQr.textContent = '📷 اسکن QR بلیت';
                qrActive = false;
                return;
            }

            qrReader.classList.add('active');
            btnQr.textContent = '⏹️ توقف اسکن';
            qrActive = true;
            qrScanner = new Html5Qrcode('qr-reader');
            qrScanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                function(decoded) {
                    if (qrScanner) {
                        qrScanner.stop().then(() => qrScanner.clear()).catch(() => {});
                        qrScanner = null;
                    }
                    qrActive = false;
                    navigateFromQr(decoded);
                },
                function() {}
            ).catch(function() {
                alert('دسترسی به دوربین ممکن نیست. کد را دستی وارد کنید.');
                qrReader.classList.remove('active');
                btnQr.textContent = '📷 اسکن QR بلیت';
                qrActive = false;
            });
        });
    }
})();
</script>

</body>
</html>