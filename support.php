<?php
require_once 'includes/auth.php';
require_once 'db.php';

$user_id = require_login();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    csrf_require();

    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!empty($subject) && !empty($message) && mb_strlen($subject) <= 200 && mb_strlen($message) <= 2000) {
        try {
            $stmt = $pdo->prepare('INSERT INTO support_tickets (user_id, subject, message, date_shamsi) VALUES (:uid, :sub, :msg, :dt)');
            $stmt->execute(['uid' => $user_id, 'sub' => $subject, 'msg' => $message, 'dt' => get_today_shamsi()]);
            $msg = "<div class='app-alert app-alert-success'>✅ تیکت شما با موفقیت ثبت شد.</div>";
        } catch (Exception $e) {
            error_log('Support ticket error: ' . $e->getMessage());
            $msg = "<div class='app-alert app-alert-error'>خطا در ثبت تیکت. لطفاً دوباره تلاش کنید.</div>";
        }
    } else {
        $msg = "<div class='app-alert app-alert-error'>❌ لطفاً تمام فیلدها را پر کنید (حداکثر ۲۰۰ کاراکتر موضوع و ۲۰۰۰ کاراکتر پیام).</div>";
    }
}

$tickets = [];
try {
    $stmt_list = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id = :uid ORDER BY id DESC');
    $stmt_list->execute(['uid' => $user_id]);
    $tickets = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    app_error('خطا در دریافت تیکت‌ها.');
}

$page_title = 'پشتیبانی و تیکت';
$layout_mode = 'panel';
$active_page = 'support';
require_once 'includes/layout_start.php';
?>

<div class="page-hero">
    <h2>📩 مرکز پشتیبانی آبسار</h2>
    <p>ارسال تیکت و پیگیری درخواست‌های شما</p>
</div>

<?php echo $msg; ?>

<div class="app-card">
    <h3>💬 ارسال تیکت جدید</h3>
    <form method="POST" action="support.php">
        <?php echo csrf_field(); ?>
        <div class="app-form-group">
            <label for="subject">موضوع:</label>
            <input type="text" name="subject" id="subject" class="app-input" placeholder="مثلاً: مشکل در شارژ کیف پول" required maxlength="200">
        </div>
        <div class="app-form-group">
            <label for="message">متن پیام:</label>
            <textarea name="message" id="message" class="app-textarea" placeholder="جزئیات مشکل خود را بنویسید..." required maxlength="2000"></textarea>
        </div>
        <button type="submit" name="submit_ticket" class="app-btn app-btn-amber">🚀 ثبت و ارسال</button>
    </form>
</div>

<div class="app-card">
    <h3>📜 تیکت‌های قبلی</h3>
    <?php if (count($tickets) > 0): ?>
        <?php foreach ($tickets as $t): ?>
            <div class="ticket-card-item">
                <span class="badge <?php echo ($t['status'] === 'pending') ? 'badge-pending' : 'badge-success'; ?> status-float">
                    <?php echo ($t['status'] === 'pending') ? '🔄 در انتظار' : '✅ پاسخ داده شد'; ?>
                </span>
                <h4 style="margin:0 0 8px;">📌 <?php echo h($t['subject']); ?>
                    <small style="color:var(--text-muted);font-weight:normal;">(<?php echo h($t['date_shamsi'] ?? ''); ?>)</small>
                </h4>
                <p style="color:#4b5563;margin:0;">💬 <?php echo nl2br(h($t['message'])); ?></p>
                <?php if (!empty($t['reply'])): ?>
                    <div class="app-alert app-alert-info" style="margin-top:12px;margin-bottom:0;">
                        <strong>✍️ پاسخ پشتیبانی:</strong><br>
                        <?php echo nl2br(h($t['reply'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align:center;color:var(--text-muted);">هنوز تیکتی ارسال نکرده‌اید.</p>
    <?php endif; ?>

    <div class="nav-footer-buttons">
        <a href="user_dashboard.php" class="app-btn app-btn-secondary">🔙 بازگشت به داشبورد</a>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
