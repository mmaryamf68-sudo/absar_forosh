<?php
require_once 'includes/auth.php';
require_once 'db.php';

$user_id = require_login();

try {
    $stmt_user = $pdo->prepare('SELECT full_name, wallet_balance FROM pool_users WHERE id = :id');
    $stmt_user->execute(['id' => $user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    $stmt_tx = $pdo->prepare('SELECT id, amount, type, description, created_at_shamsi FROM wallet_transactions WHERE user_id = :uid ORDER BY id DESC');
    $stmt_tx->execute(['uid' => $user_id]);
    $transactions = $stmt_tx->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    app_error('خطا در دریافت اطلاعات.');
}

$page_title = 'تاریخچه کیف پول';
$layout_mode = 'panel';
$active_page = 'wallet';
require_once 'includes/layout_start.php';
?>

<div class="page-hero page-hero-flex">
    <div>
        <h2>💼 گزارش تراکنش‌های کیف پول</h2>
        <p>ریز تراکنش‌های شارژ و خرید حساب شما</p>
    </div>
    <div class="hero-badge">
        <span class="num"><?php echo number_format($user ? $user['wallet_balance'] : 0); ?></span>
        <span class="lbl">تومان موجودی</span>
    </div>
</div>

<div class="action-bar">
    <p>برای رزرو سریع‌تر بلیت، موجودی خود را افزایش دهید.</p>
    <a href="charge_wallet.php" class="app-btn app-btn-success">➕ افزایش موجودی</a>
</div>

<div class="app-card">
    <h3>📜 لیست تراکنش‌های اخیر</h3>

    <?php if (count($transactions) > 0): ?>
        <div class="table-wrap">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>مبلغ</th>
                        <th>توضیحات</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($transactions as $tx):
                        $tx_amount = intval($tx['amount']);
                        $is_charge = (trim($tx['type']) === 'deposit');
                    ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td class="<?php echo $is_charge ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $is_charge ? '+ ' : '- '; echo number_format($tx_amount); ?> تومان
                            </td>
                            <td><?php echo h($tx['description']); ?></td>
                            <td><?php echo h($tx['created_at_shamsi']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="text-align:center;color:var(--text-muted);padding:20px;">هیچ تراکنشی ثبت نشده است.</p>
    <?php endif; ?>

    <div class="nav-footer-buttons">
        <a href="user_dashboard.php" class="app-btn app-btn-secondary">🔙 بازگشت به داشبورد</a>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
