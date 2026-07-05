<?php
require_once 'includes/auth.php';
require_once 'db.php';

$user_id = require_login();

if (!isset($_GET['voucher_id'])) {
    app_error('❌ کد بلیت مشخص نشده است.', 400);
}

$voucher_id = intval($_GET['voucher_id']);

try {
    $stmt_v = $pdo->prepare('
        SELECT v.voucher_code, v.ticket_count, s.date_shamsi, s.time_start, s.time_end, s.gender_type
        FROM vouchers v
        JOIN sessionsone s ON v.session_id = s.id
        WHERE v.id = :v_id AND v.user_id = :u_id
    ');
    $stmt_v->execute(['v_id' => $voucher_id, 'u_id' => $user_id]);
    $ticket_info = $stmt_v->fetch(PDO::FETCH_ASSOC);

    if (!$ticket_info) {
        throw new Exception('❌ بلیت مورد نظر یافت نشد یا دسترسی به آن مجاز نیست.');
    }

    $stmt_s = $pdo->prepare('SELECT serial_code FROM ticket_serials WHERE voucher_id = :v_id');
    $stmt_s->execute(['v_id' => $voucher_id]);
    $serials = $stmt_s->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    app_error($e->getMessage(), 403);
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$admin_check_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/admin_check.php?voucher_code=' . urlencode($ticket_info['voucher_code']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>چاپ بلیت ورود - مجموعه استخر آبسار</title>
    <style>
        body { font-family: tahoma, arial; background: #fff; margin: 0; padding: 20px; direction: rtl; color: #333; }
        .ticket-print-container { max-width: 650px; margin: 0 auto; border: 2px dashed #1e3a8a; padding: 25px; border-radius: 10px; background: #fff; position: relative; }
        .ticket-header { border-bottom: 2px solid #1e3a8a; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .ticket-header h2 { margin: 0; color: #1e3a8a; font-size: 20px; }
        .ticket-header .brand { font-weight: bold; font-size: 14px; color: #555; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; background: #f8fafc; padding: 15px; border-radius: 6px; }
        .info-item { font-size: 14px; line-height: 24px; }
        .info-item strong { color: #1e3a8a; }
        .voucher-center { text-align: center; background: #e7f1ff; border: 1px solid #b3d7ff; padding: 15px; border-radius: 6px; margin-bottom: 25px; }
        .voucher-title { font-size: 14px; margin-bottom: 5px; color: #0056b3; }
        .voucher-code { font-size: 24px; font-weight: bold; font-family: monospace; letter-spacing: 2px; color: #111; }
        .voucher-qr-wrap { display: flex; flex-direction: column; align-items: center; margin-top: 12px; gap: 6px; }
        .voucher-qr-box { background: #fff; padding: 8px; border-radius: 8px; border: 1px solid #b3d7ff; }
        .voucher-qr-label { font-size: 11px; color: #555; }
        .serials-section { margin-bottom: 25px; }
        .serials-section h4 { margin: 0 0 10px 0; font-size: 14px; color: #555; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .serials-grid { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
        .serial-card { display: flex; flex-direction: column; align-items: center; background: #f8fafc; border: 1px dashed #ccc; padding: 10px; border-radius: 8px; min-width: 130px; }
        .serial-qr-box { background: #fff; padding: 6px; border-radius: 6px; margin-bottom: 8px; }
        .serial-qr-box canvas, .serial-qr-box img { display: block; }
        .serial-badge { font-family: monospace; font-size: 12px; font-weight: bold; color: #dc3545; text-align: center; word-break: break-all; }
        .ticket-footer { text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eee; padding-top: 15px; line-height: 20px; }
        .no-print-zone { max-width: 650px; margin: 0 auto 20px auto; display: flex; justify-content: space-between; }
        .btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: bold; cursor: pointer; border: none; }
        .btn-print { background: #10b981; color: white; }
        .btn-back { background: #6b7280; color: white; }
        @media print {
            body { padding: 0; background: white; }
            .no-print-zone { display: none; }
            .ticket-print-container { border: 2px solid #000; box-shadow: none; }
            .voucher-center { background: #f0f0f0 !important; }
            .info-grid { background: #f0f0f0 !important; }
        }
    </style>
</head>
<body>

    <div class="no-print-zone">
        <button onclick="window.print();" class="btn btn-print">🖨️ چاپ بلیت / ذخیره PDF</button>
        <a href="user_dashboard.php" class="btn btn-back">⬅️ برگشت به داشبورد</a>
    </div>

    <div class="ticket-print-container">

        <div class="ticket-header">
            <h2>🎫 بلیت الکترونیک ورود به مجموعه</h2>
            <div class="brand">مجموعه پارک آبی و استخر آبسار</div>
        </div>

        <div class="info-grid">
            <div class="info-item">📅 تاریخ سانس: <strong><?php echo h($ticket_info['date_shamsi']); ?></strong></div>
            <div class="info-item">👥 تعداد نفرات: <strong><?php echo intval($ticket_info['ticket_count']); ?> نفر</strong></div>
            <div class="info-item">⏰ ساعت سانس: <strong><?php echo h($ticket_info['time_start'] . ' تا ' . $ticket_info['time_end']); ?></strong></div>
            <div class="info-item">🏊‍♂️ نوع سانس: <strong><?php echo ($ticket_info['gender_type'] == 'women') ? 'بانوان' : 'آقایان'; ?></strong></div>
        </div>

        <div class="voucher-center">
            <div class="voucher-title">کد ووچر اصلی (جهت ارائه به پذیرش):</div>
            <div class="voucher-code"><?php echo h($ticket_info['voucher_code']); ?></div>
            <div class="voucher-qr-wrap">
                <div class="voucher-qr-box" id="voucher_qrcode"></div>
                <div class="voucher-qr-label">اسکن QR → باز شدن مستقیم صفحه استعلام پذیرش</div>
            </div>
        </div>

        <div class="serials-section">
            <h4>🎫 کدهای سریال اختصاصی جهت عبور از گیت هوشمند:</h4>
            <div class="serials-grid">
            <?php foreach ($serials as $i => $serial): ?>
                <div class="serial-card">
                    <div class="serial-qr-box" id="serial_qr_<?php echo $i; ?>"></div>
                    <span class="serial-badge"><?php echo h($serial); ?></span>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <div class="ticket-footer">
            <p>لطفاً این رسید یا بارکد آن را در گوشی خود همراه داشته باشید یا پرینت آن را به پذیرش تحویل دهید.<br>
            گیت ورود الکترونیک با هر کد سریال فقط یک‌بار باز خواهد شد.</p>
        </div>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    const voucherCheckUrl = <?php echo json_encode($admin_check_url, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const serialCodes = <?php echo json_encode(array_values($serials), JSON_UNESCAPED_UNICODE); ?>;

    function buildQr(el, text, size) {
        if (!el || typeof QRCode === 'undefined') return;
        new QRCode(el, {
            text: text,
            width: size,
            height: size,
            colorDark: '#111827',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    window.onload = function() {
        buildQr(document.getElementById('voucher_qrcode'), voucherCheckUrl, 120);
        serialCodes.forEach(function(code, i) {
            buildQr(document.getElementById('serial_qr_' + i), code, 100);
        });
        setTimeout(function() { window.print(); }, 600);
    };
    </script>

</body>
</html>
