<?php
require_once 'includes/auth.php';
require_once 'db.php';

require_admin();

if (isset($_POST['reply_ticket'])) {
    csrf_require();
    $stmt = $pdo->prepare("UPDATE support_tickets SET reply = :reply, status = 'answered' WHERE id = :id");
    $stmt->execute(['reply' => trim($_POST['reply_text'] ?? ''), 'id' => intval($_POST['ticket_id'])]);
    header('Location: ?tab=tickets');
    exit;
}

if (isset($_POST['action_check'])) {
    csrf_require();
    $pdo->prepare("UPDATE ticket_serials SET status = 'used', used_at = NOW() WHERE id = :id")->execute(['id' => intval($_POST['ticket_id'])]);
    $message = '✅ بلیت باطل شد.';
}

$tab = $_GET['tab'] ?? 'dashboard';
$message = $message ?? '';
$voucher_data = null;
$tickets_res = [];
$tickets_list = [];
$all_vouchers = [];

if ($tab == 'check_voucher' && !empty($_POST['search_code'])) {
    csrf_require();
    $stmt = $pdo->prepare('SELECT v.*, s.date_shamsi, u.full_name as user_name FROM vouchers v JOIN sessionsone s ON v.session_id = s.id LEFT JOIN pool_users u ON v.user_id = u.id WHERE v.voucher_code = :v_code');
    $stmt->execute(['v_code' => trim($_POST['search_code'])]);
    $voucher_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($voucher_data) {
        $stmt_t = $pdo->prepare('SELECT * FROM ticket_serials WHERE voucher_id = :v_id');
        $stmt_t->execute(['v_id' => $voucher_data['id']]);
        $tickets_res = $stmt_t->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif ($tab == 'all_reserves') {
    $all_vouchers = $pdo->query('SELECT v.*, s.date_shamsi, u.full_name as user_name, (SELECT GROUP_CONCAT(serial_code SEPARATOR \', \') FROM ticket_serials WHERE voucher_id = v.id) as serials FROM vouchers v JOIN sessionsone s ON v.session_id = s.id LEFT JOIN pool_users u ON v.user_id = u.id ORDER BY v.id DESC')->fetchAll(PDO::FETCH_ASSOC);
} elseif ($tab == 'tickets') {
    $tickets_list = $pdo->query('SELECT * FROM support_tickets ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

$live_data = [];
$date_q = '';
if ($tab == 'dashboard') {
    $date_q = $pdo->query('SELECT date_shamsi FROM sessionsone ORDER BY id DESC LIMIT 1')->fetchColumn();
    $stmt = $pdo->prepare('SELECT *, (max_capacity - reserved_count) as remaining FROM sessionsone WHERE date_shamsi = :d ORDER BY time_start ASC');
    $stmt->execute(['d' => $date_q]);
    $live_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stats = [
    'today' => $pdo->query('SELECT COUNT(*) FROM vouchers WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
    'total' => $pdo->query('SELECT COUNT(*) FROM vouchers')->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>پنل مدیریت</title>
    <style>
        body { font-family: Tahoma; margin: 0; display: flex; background: #f4f6f9; }
        .sidebar { width: 240px; background: #2c3e50; color: #fff; height: 100vh; position: fixed; padding-top: 20px; }
        .sidebar a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; }
        .sidebar a:hover, .sidebar a.active { background: #34495e; color: #fff; }
        .content { margin-right: 240px; width: calc(100% - 240px); padding: 30px; }
        .card { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: inline-block; width: 200px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 15px; border-bottom: 1px solid #eee; text-align: center; }
        .btn { padding: 8px 15px; border-radius: 4px; border: none; cursor: pointer; background: #3498db; color: #fff; }
    </style>
</head>
<body>
<div class="sidebar">
    <h2>مدیریت استخر</h2>
    <a href="?tab=dashboard" class="<?php echo $tab == 'dashboard' ? 'active' : ''; ?>">📊 داشبورد</a>
    <a href="?tab=check_voucher" class="<?php echo $tab == 'check_voucher' ? 'active' : ''; ?>">🛂 چک ووچر</a>
    <a href="?tab=all_reserves" class="<?php echo $tab == 'all_reserves' ? 'active' : ''; ?>">📋 گزارش رزروها</a>
    <a href="?tab=tickets" class="<?php echo $tab == 'tickets' ? 'active' : ''; ?>">📩 تیکت‌ها</a>
    <a href="admin_logout.php" style="color: #e74c3c; margin-top: 50px;">🚪 خروج</a>
</div>

<div class="content">
    <?php if (!empty($message)): ?>
        <div style="background:#d4edda;padding:10px;margin-bottom:15px;border-radius:4px;"><?php echo h($message); ?></div>
    <?php endif; ?>

    <?php if ($tab == 'dashboard'): ?>
        <h2>📊 آمار زنده (<?php echo h($date_q); ?>)</h2>
        <?php foreach ($live_data as $s): ?>
            <div class="card" style="border-right: 5px solid <?php echo $s['remaining'] > 0 ? '#27ae60' : '#e74c3c'; ?>;">
                <h3><?php echo h($s['time_start']); ?> - <?php echo h($s['time_end']); ?></h3>
                <p>باقی‌مانده: <?php echo intval($s['remaining']); ?> نفر</p>
            </div>
        <?php endforeach; ?>
        <hr>
        <div class="card"><h3>بلیت امروز</h3><p><?php echo intval($stats['today']); ?></p></div>
        <div class="card"><h3>کل بلیت‌ها</h3><p><?php echo intval($stats['total']); ?></p></div>

    <?php elseif ($tab == 'check_voucher'): ?>
        <h2>🛂 استعلام ووچر</h2>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="text" name="search_code" placeholder="کد ووچر..." required>
            <button type="submit" class="btn">جستجو</button>
        </form>
        <?php if ($voucher_data): ?>
            <p>خریدار: <?php echo h($voucher_data['user_name']); ?></p>
            <table><tr><th>سریال</th><th>وضعیت</th><th>عملیات</th></tr>
            <?php foreach ($tickets_res as $t): ?>
            <tr>
                <td><?php echo h($t['serial_code']); ?></td>
                <td><?php echo h($t['status']); ?></td>
                <td>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="ticket_id" value="<?php echo intval($t['id']); ?>">
                        <button name="action_check" class="btn" style="background:#e74a3b;">ابطال</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?></table>
        <?php endif; ?>

    <?php elseif ($tab == 'all_reserves'): ?>
        <h2>📋 گزارش رزروها</h2>
        <table><tr><th>ووچر</th><th>نام خریدار</th><th>تعداد</th><th>تاریخ</th><th>سریال‌ها</th></tr>
        <?php foreach ($all_vouchers as $row): ?>
        <tr>
            <td><?php echo h($row['voucher_code']); ?></td>
            <td><?php echo h($row['user_name']); ?></td>
            <td><?php echo intval($row['ticket_count']); ?></td>
            <td><?php echo h($row['date_shamsi']); ?></td>
            <td><?php echo h($row['serials']); ?></td>
        </tr>
        <?php endforeach; ?></table>

    <?php elseif ($tab == 'tickets'): ?>
        <h2>📩 مدیریت تیکت‌ها</h2>
        <table><tr><th>کاربر</th><th>موضوع</th><th>پیام</th><th>وضعیت</th><th>پاسخ</th></tr>
        <?php foreach ($tickets_list as $row): ?>
        <tr>
            <td><?php echo intval($row['user_id']); ?></td>
            <td><?php echo h($row['subject']); ?></td>
            <td><?php echo h($row['message']); ?></td>
            <td><?php echo h($row['status']); ?></td>
            <td>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo intval($row['id']); ?>">
                    <input type="text" name="reply_text" value="<?php echo h($row['reply'] ?? ''); ?>">
                    <button name="reply_ticket" class="btn">ارسال</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?></table>
    <?php endif; ?>
</div>
</body>
</html>
