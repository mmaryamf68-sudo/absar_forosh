<?php
require_once 'includes/auth.php';
require_once 'db.php';

$user_id = require_login();

$ticket_count = isset($_GET['count']) ? intval($_GET['count']) : 1;
if ($ticket_count <= 0) { $ticket_count = 1; }
if ($ticket_count > 20) { $ticket_count = 20; }

$selected_date = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : get_today_shamsi();
if (!is_valid_shamsi_date($selected_date)) {
    $selected_date = get_today_shamsi();
}

$is_friday   = is_shamsi_friday($selected_date);
$is_saturday = is_shamsi_saturday($selected_date);

try {
    $stmt_sessions = $pdo->prepare('
        SELECT id, date_shamsi, time_start, time_end, gender_type, max_capacity, reserved_count
        FROM sessionsone
        WHERE date_shamsi = :sel_date
        ORDER BY time_start ASC
    ');
    $stmt_sessions->execute(['sel_date' => $selected_date]);
    $sessions = $stmt_sessions->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    app_error('خطا در دریافت سانس‌ها.');
}

$page_title = 'انتخاب سانس | استخر آبسار';
$layout_mode = 'panel';
$active_page = 'reserve';
require_once 'includes/layout_start.php';
?>

<div class="app-wrapper-narrow" style="margin:0 auto;">

<div class="page-hero">
    <h2>📅 سامانه رزرو سانس استخر آبسار</h2>
    <p>انتخاب تاریخ و سانس مورد نظر</p>
</div>

<div class="info-bar">
    🛒 شما در حال خرید بلیت برای <strong><?php echo intval($ticket_count); ?> نفر</strong> هستید.
</div>

<div class="filter-box">
    <form method="GET" action="reserve.php">
        <input type="hidden" name="count" value="<?php echo intval($ticket_count); ?>">
        <label>📅 انتخاب تاریخ:</label>
        <input type="text" name="filter_date" class="app-input app-input-mono" style="width:180px;display:inline-block;margin:0 8px;" value="<?php echo h($selected_date); ?>" placeholder="1405/03/22" required autocomplete="off" pattern="\d{4}/\d{2}/\d{2}">
        <button type="submit" class="app-btn app-btn-primary">🔍 بررسی و فیلتر</button>
    </form>
</div>

<div class="app-card">
    <h3>📋 سانس‌های <?php echo h($selected_date); ?></h3>

    <?php if (count($sessions) > 0): ?>
        <?php
        $session_ids = array_column($sessions, 'id');
        $min_id = min($session_ids);

        foreach ($sessions as $session):
            $current_id = intval($session['id']);
            $start_time = $session['time_start'];
            $end_time = $session['time_end'];
            $is_women = false;

            if ($is_friday) {
                if ($current_id === $min_id) {
                    $start_time = '09:00';
                    $end_time   = '13:20';
                    $is_women   = false;
                } else {
                    $start_time = '16:00';
                    $end_time   = '20:00';
                    $is_women   = true;
                }
            } elseif ($is_saturday) {
                $is_women = false;
            } else {
                $is_women = ($current_id === $min_id);
            }

            $gender_text = $is_women ? 'سانس بانوان 👩' : 'سانس آقایان 👨';
            $gender_class = $is_women ? 'badge-women' : 'badge-men';
            $remaining_capacity = max(0, intval($session['max_capacity']) - intval($session['reserved_count']));
            $time_label = h($start_time) . ' الی ' . h($end_time);
        ?>
            <div class="session-card">
                <div class="session-info">
                    <h4>⏰ ساعت <?php echo $time_label; ?></h4>
                    <div>
                        <span class="badge <?php echo $gender_class; ?>"><?php echo h($gender_text); ?></span>
                        <span class="badge badge-capacity">👥 باقی‌مانده: <?php echo $remaining_capacity; ?> نفر</span>
                    </div>
                </div>
                <div>
                    <?php if ($remaining_capacity >= $ticket_count): ?>
                        <form action="submit_reserve.php" method="POST" class="purchase-form"
                              data-time="<?php echo h($start_time . ' الی ' . $end_time); ?>"
                              data-gender="<?php echo h($gender_text); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="session_id" value="<?php echo intval($session['id']); ?>">
                            <input type="hidden" name="ticket_count" value="<?php echo intval($ticket_count); ?>">
                            <input type="text" name="discount_code" class="input-coupon" placeholder="کد تخفیف" autocomplete="off" maxlength="50">
                            <button type="submit" class="app-btn app-btn-success">🛒 رزرو و پرداخت</button>
                        </form>
                    <?php else: ?>
                        <button class="app-btn btn-disabled" disabled>❌ ظرفیت تکمیل</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-data-box">
            ⚠️ هیچ سانسی برای تاریخ <?php echo h($selected_date); ?> تعریف نشده است.
        </div>
    <?php endif; ?>

    <div class="nav-footer-buttons">
        <a href="user_dashboard.php" class="app-btn app-btn-secondary">🔙 بازگشت به داشبورد</a>
    </div>
</div>
</div>

<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <div class="modal-title">🛒 تأیید نهایی رزرو</div>
        <div class="modal-details">
            <div>📅 تاریخ: <strong id="m-date"><?php echo h($selected_date); ?></strong></div>
            <div>⏰ زمان: <strong id="m-time"></strong></div>
            <div>🚻 نوع: <strong id="m-gender"></strong></div>
            <div>👥 تعداد: <strong><?php echo intval($ticket_count); ?> نفر</strong></div>
            <div>🎟️ کد تخفیف: <strong id="m-coupon">ندارد</strong></div>
        </div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px;">آیا اطلاعات تأیید است؟</p>
        <div class="modal-buttons">
            <button class="app-btn app-btn-success" id="modalConfirmBtn">✅ بله، پرداخت</button>
            <button class="app-btn app-btn-secondary" onclick="closeConfirmModal()">❌ انصراف</button>
        </div>
    </div>
</div>

<script>
let currentFormToSubmit = null;

document.querySelectorAll('.purchase-form').forEach(function(form) {
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        currentFormToSubmit = form;
        document.getElementById('m-time').innerText = form.dataset.time || '';
        document.getElementById('m-gender').innerText = form.dataset.gender || '';
        const couponInput = form.querySelector('input[name="discount_code"]').value.trim();
        document.getElementById('m-coupon').innerText = couponInput ? couponInput : 'وارد نشده';
        document.getElementById('confirmModal').classList.add('active');
    });
});

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
    currentFormToSubmit = null;
}

document.getElementById('modalConfirmBtn').addEventListener('click', function() {
    if (currentFormToSubmit) currentFormToSubmit.submit();
});
</script>

<?php require_once 'includes/layout_end.php'; ?>
