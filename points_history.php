<?php
require_once 'includes/auth.php';
require_once 'db.php';

$user_id = require_login();

try {
    $stmt_user = $pdo->prepare('SELECT full_name, points FROM pool_users WHERE id = :id');
    $stmt_user->execute(['id' => $user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    $user_name = $user ? $user['full_name'] : '';
    $current_points = $user ? intval($user['points']) : 0;

    $stmt_tx = $pdo->prepare('SELECT * FROM points_transactions WHERE user_id = :id ORDER BY id DESC');
    $stmt_tx->execute(['id' => $user_id]);
    $transactions = $stmt_tx->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    app_error('خطا در سیستم.');
}

$page_title = 'تاریخچه امتیازات';
$layout_mode = 'panel';
$active_page = 'points';
require_once 'includes/layout_start.php';
?>

<div class="page-hero page-hero-purple page-hero-flex">
    <div>
        <h2>🏆 تاریخچه امتیازات باشگاه مشتریان</h2>
        <p>کاربر گرامی <b><?php echo h($user_name); ?></b>، ریز تراکنش‌های امتیاز شما</p>
    </div>
    <div class="hero-badge">
        <span class="num"><?php echo $current_points; ?></span>
        <span class="lbl">امتیاز کل</span>
    </div>
</div>

<div class="app-alert app-alert-success">
    🤖 <b>راهنما:</b> با رسیدن به <b>۳۰۰ امتیاز</b> می‌توانید آن را به <b>۵۰,۰۰۰ تومان</b> شارژ کیف پول تبدیل کنید.
    <a href="redeem_points.php" style="font-weight:700;">تبدیل امتیاز ←</a>
</div>

<div class="app-card">
    <h3>📋 لیست تغییرات امتیاز</h3>

    <?php if (empty($transactions)): ?>
        <p style="text-align:center;color:var(--text-muted);padding:30px;">هنوز تراکنشی ثبت نشده است.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>شرح</th>
                        <th>امتیاز</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><?php echo h($tx['description']); ?></td>
                            <td class="<?php echo (isset($tx['type']) && $tx['type'] === 'spend') ? 'text-danger' : 'text-success'; ?>">
                                <?php echo (isset($tx['type']) && $tx['type'] === 'spend') ? '- ' : '+ '; echo number_format($tx['points']); ?>
                            </td>
                            <td style="color:var(--text-muted);"><?php echo h($tx['created_at_shamsi']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="nav-footer-buttons">
        <a href="user_dashboard.php" class="app-btn app-btn-secondary">🔙 بازگشت به داشبورد</a>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
