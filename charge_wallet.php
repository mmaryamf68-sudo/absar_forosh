<?php
require_once 'includes/auth.php';
require_once 'db.php';

$user_id = require_login();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    csrf_require();

    if (!ALLOW_SIMULATED_WALLET_CHARGE) {
        $message = '⚠️ شارژ مستقیم کیف پول غیرفعال است. لطفاً از درگاه پرداخت رسمی استفاده کنید.';
        $message_type = 'error';
    } else {
        $amount = intval($_POST['amount']);

        if ($amount < WALLET_CHARGE_MIN_AMOUNT) {
            $message = '⚠️ حداقل مبلغ شارژ ' . number_format(WALLET_CHARGE_MIN_AMOUNT) . ' تومان است.';
            $message_type = 'error';
        } elseif ($amount > WALLET_CHARGE_MAX_AMOUNT) {
            $message = '⚠️ حداکثر مبلغ شارژ در هر تراکنش ' . number_format(WALLET_CHARGE_MAX_AMOUNT) . ' تومان است.';
            $message_type = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt_charge = $pdo->prepare('UPDATE pool_users SET wallet_balance = wallet_balance + :amount WHERE id = :id');
                $stmt_charge->execute(['amount' => $amount, 'id' => $user_id]);

                $wallet_desc = 'شارژ آنلاین حساب از طریق درگاه شبیه‌سازی شده';
                $date_now = get_today_shamsi();

                $stmt_tx = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at_shamsi) VALUES (:u_id, :amt, 'deposit', :dsc, :dt)");
                $stmt_tx->execute([
                    'u_id' => $user_id,
                    'amt' => $amount,
                    'dsc' => $wallet_desc,
                    'dt' => $date_now,
                ]);

                $pdo->commit();
                $message = '🎉 حساب شما با موفقیت به مبلغ ' . number_format($amount) . ' تومان شارژ شد.';
                $message_type = 'success';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('Wallet charge error: ' . $e->getMessage());
                $message = '❌ خطایی در فرآیند شارژ رخ داد. لطفاً دوباره تلاش کنید.';
                $message_type = 'error';
            }
        }
    }
}

$stmt_balance = $pdo->prepare('SELECT wallet_balance, full_name FROM pool_users WHERE id = :id');
$stmt_balance->execute(['id' => $user_id]);
$user_data = $stmt_balance->fetch(PDO::FETCH_ASSOC);

$current_balance = $user_data ? intval($user_data['wallet_balance']) : 0;
$user_name = $user_data ? $user_data['full_name'] : 'کاربر گرامی';

$page_title = 'افزایش موجودی کیف پول';
$layout_mode = 'panel';
$active_page = 'wallet';
require_once 'includes/layout_start.php';
?>

<div class="app-wrapper-narrow" style="margin:0 auto;">

    <div class="page-hero page-hero-emerald page-hero-flex">
        <div>
            <h2>💳 افزایش موجودی کیف پول</h2>
            <p>شارژ حساب برای رزرو سریع‌تر بلیت</p>
        </div>
        <div class="hero-badge">
            <span class="num"><?php echo number_format($current_balance); ?></span>
            <span class="lbl">تومان موجودی</span>
        </div>
    </div>

    <div class="app-card">
        <?php if (!empty($message)): ?>
            <div class="app-alert app-alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div>
        <?php endif; ?>

        <div class="app-alert app-alert-info">
            👤 حساب: <strong><?php echo h($user_name); ?></strong>
        </div>

        <?php if (ALLOW_SIMULATED_WALLET_CHARGE): ?>
        <form action="charge_wallet.php" method="POST">
            <?php echo csrf_field(); ?>
            <div class="app-form-group">
                <label for="amount">مبلغ مورد نظر (تومان):</label>
                <input type="number" id="amount" name="amount" class="app-input app-input-mono" placeholder="مثال: 100000" required min="<?php echo WALLET_CHARGE_MIN_AMOUNT; ?>" max="<?php echo WALLET_CHARGE_MAX_AMOUNT; ?>">
            </div>
            <button type="submit" class="app-btn app-btn-primary app-btn-block app-btn-lg">اتصال به درگاه و شارژ حساب</button>
        </form>
        <?php else: ?>
        <div class="app-alert app-alert-warning">
            شارژ مستقیم کیف پول در حال حاضر غیرفعال است. برای افزایش موجودی از درگاه پرداخت رسمی استفاده کنید.
        </div>
        <?php endif; ?>

        <div class="nav-footer-buttons">
            <a href="wallet_history.php" class="app-btn app-btn-secondary">📊 تاریخچه تراکنش‌ها</a>
            <a href="user_dashboard.php" class="app-btn app-btn-outline">🔙 بازگشت به داشبورد</a>
        </div>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
