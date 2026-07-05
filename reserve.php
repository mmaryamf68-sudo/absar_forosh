<?php
/**
 * صفحه انتخاب سانس و رزرو بلیت
 */

require_once 'includes/auth.php';
require_once 'db.php';

$user_id = require_login();

$ticket_count = isset($_GET['count']) ? intval($_GET['count']) : 1;
if ($ticket_count <= 0) { $ticket_count = 1; }
if ($ticket_count > MAX_TICKETS_PER_PURCHASE) { $ticket_count = MAX_TICKETS_PER_PURCHASE; }

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

$page_title = 'انتخاب سانس و رزرو بلیت';
$layout_mode = 'panel';
$active_page = 'reserve';
require_once 'includes/layout_start.php';
?>

<div class="page-hero">
    <h2>📅 سامانه رزرو سانس استخر آبسار</h2>
    <p>انتخاب تاریخ و سانس مورد نظر</p>
</div>

<div class="app-alert app-alert-info" style="margin-bottom:20px;">
    🛒 شما در حال خرید بلیت برای <strong><?php echo intval($ticket_count); ?> نفر</strong> هستید.
</div>

<div class="app-card">
    <h3>🔍 فیلتر تاریخ</h3>
    <form method="GET" action="reserve.php" style="display:flex; gap:12px; flex-wrap:wrap;">
        <input type="hidden" name="count" value="<?php echo intval($ticket_count); ?>">
        <input type="text" name="filter_date" class="app-input" 
               style="flex:1; min-width:150px;" 
               placeholder="تاریخ (مثال: 1405/03/22)" 
               value="<?php echo h($selected_date); ?>"
               pattern="\d{4}/\d{2}/\d{2}">
        <button type="submit" class="app-btn app-btn-primary">🔍 بررسی سانس‌ها</button>
    </form>
</div>

<div class="app-card">
    <h3>📋 سانس‌های موجود در <?php echo h($selected_date); ?></h3>

    <?php if (count($sessions) > 0): ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">
        <?php
        $session_ids = array_column($sessions, 'id');
        $min_id = count($session_ids) > 0 ? min($session_ids) : 0;

        foreach ($sessions as $session):
            $current_id = intval($session['id']);
            $start_time = $session['time_start'];
            $end_time   = $session['time_end'];
            $is_women   = false;

            // تنظیم زمان‌ها بر اساس روز هفته
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
            $gender_class = $is_women ? 'badge-female' : 'badge-male';
            $remaining_capacity = max(0, intval($session['max_capacity']) - intval($session['reserved_count']));
            $time_label = h($start_time . ' الی ' . $end_time);
            $can_reserve = $remaining_capacity >= $ticket_count;
        ?>
            <div style="background:white; border:1px solid var(--border-color); border-radius:8px; padding:16px; display:flex; flex-direction:column;">
                <h4 style="margin-bottom:12px; font-size:16px;">⏰ ساعت <?php echo $time_label; ?></h4>
                
                <div style="margin-bottom:12px;">
                    <span class="badge <?php echo $gender_class; ?>" style="margin-left:8px;"><?php echo $gender_text; ?></span>
                    <span class="badge badge-capacity">👥 باقی: <?php echo $remaining_capacity; ?> نفر</span>
                </div>

                <?php if ($can_reserve): ?>
                    <form action="submit_reserve.php" method="POST" style="flex:1; display:flex; flex-direction:column;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="session_id" value="<?php echo intval($session['id']); ?>">
                        <input type="hidden" name="ticket_count" value="<?php echo intval($ticket_count); ?>">
                        
                        <input type="text" name="discount_code" class="app-input" 
                               placeholder="کد تخفیف (اختیاری)" 
                               maxlength="50" 
                               style="margin-bottom:12px;">
                        
                        <button type="submit" class="app-btn app-btn-success" style="flex:1;">
                            🛒 رزرو و پرداخت
                        </button>
                    </form>
                <?php else: ?>
                    <button class="app-btn" style="background:#d1d5db; color:#666; cursor:not-allowed; flex:1;">
                        ❌ ظرفیت تکمیل
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div style="text-align:center; padding:40px; background:var(--bg-light); border-radius:8px;">
            <p style="font-size:16px; color:var(--text-muted); margin-bottom:12px;">
                ⚠️ هیچ سانسی برای تاریخ <?php echo h($selected_date); ?> تعریف نشده است.
            </p>
            <a href="reserve.php?count=<?php echo intval($ticket_count); ?>" class="app-btn app-btn-secondary">
                🔄 بازگشت
            </a>
        </div>
    <?php endif; ?>

    <div style="margin-top:20px; border-top:1px solid var(--border-color); padding-top:20px; text-align:center;">
        <a href="user_dashboard.php" class="app-btn app-btn-secondary">🔙 بازگشت به داشبورد</a>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
