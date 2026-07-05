<?php
/**
 * فایل ایندکس - صفحه اول
 */

require_once 'includes/auth.php';

init_secure_session();
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['user_name'] : '';

$page_title = 'مجموعه فرهنگی ورزشی استخر آبسار';
$layout_mode = 'public';
$active_page = 'home';
$show_footer = true;
require_once 'includes/layout_start.php';
?>

<!-- تیزر ویدیو -->
<div id="splash-screen" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:black; z-index:9999; text-align:center;">
    <video autoplay muted playsinline id="splash-video" style="width:100%; height:100%; object-fit:cover;">
        <source src="video/pool7.mp4" type="video/mp4">
    </video>
    <div class="skip-btn" onclick="hideSplash()" 
         style="position:absolute; bottom:20px; left:50%; transform:translateX(-50%); background:rgba(255,255,255,0.8); padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600;">
        رد کردن تیزر ⏩
    </div>
</div>

<!-- بخش Hero -->
<section class="public-hero">
    <h1>🏊 مجموعه فرهنگی ورزشی استخر آبسار</h1>
    <p>محیطی مجهز و بهداشتی برای خانواده‌ها<br>
    عضویت در باشگاه مشتریان، کیف پول هوشمند و خرید آنلاین بلیت با تخفیف‌های ویژه</p>
    
    <?php if ($is_logged_in): ?>
        <a href="user_dashboard.php" class="btn-cta">🎟️ خرید بلیت و رزرو سانس</a>
    <?php else: ?>
        <a href="register.php" class="btn-cta">🚀 ثبت‌نام و خرید بلیت</a>
    <?php endif; ?>
</section>

<!-- بخش امکانات -->
<section class="section-block">
    <div class="section-title">
        <h2>🏊 امکانات و خدمات</h2>
        <p style="color:var(--text-muted);">مدرن‌ترین تجهیزات آبی تحت نظارت کادر مجرب</p>
    </div>
    
    <div class="grid-3">
        <div class="feature-card">
            <div class="icon">🏊‍♂️</div>
            <h3>استخر بزرگسالان</h3>
            <p>سیستم تصفیه مکانیزه هیدروژنی بدون حساسیت چشمی و پوستی</p>
        </div>
        <div class="feature-card">
            <div class="icon">👶</div>
            <h3>استخر کودکان</h3>
            <p>استخر مخصوص کودکان با نظارت کادر آموزشی حرفه‌ای</p>
        </div>
        <div class="feature-card">
            <div class="icon">🔥</div>
            <h3>سونا و جکوزی</h3>
            <p>اتاق‌های سونای مجزا با دمای استاندارد برای استراحت و آرام‌سازی</p>
        </div>
        <div class="feature-card">
            <div class="icon">☕</div>
            <h3>کافی شاپ</h3>
            <p>کافی شاپ مجموعه در تمام ساعات کاری آماده خدمات است</p>
        </div>
        <div class="feature-card">
            <div class="icon">🍽️</div>
            <h3>رستوران و بوفه</h3>
            <p>ارائه انواع غذاهای سالم و لوازم شنای با کیفیت</p>
        </div>
        <div class="feature-card">
            <div class="icon">💆</div>
            <h3>ماساژ و درمان</h3>
            <p>ارائه خدمات ماساژ ریلکسی و درمانی با کادر حرفه‌ای</p>
        </div>
    </div>
</section>

<!-- بخش درباره -->
<section class="section-block about-box">
    <div class="section-title">
        <h2>📖 درباره مجموعه</h2>
    </div>
    <p style="text-align:justify; color:#475569; line-height:2;">
        مجموعه آبی آبسار در زمینی به مساحت 4000 متر مربع و با زیربنایی بالغ بر 2600 متر مربع و فضای پارکینگ اختصاصی برای 150 دستگاه خودرو 
        احداث شده است. این مجموعه دارای استخرهای مختلفی برای بزرگسالان و کودکان، سونا، جکوزی، سالن ورزشی، کافی شاپ و رستوران است. 
        کادر مجموعه شامل مربیان ورزشی توانمند و کادر پرستاری است که همواره برای راحتی و ایمنی بازدید‌کنندگان تلاش می‌کند.
    </p>
</section>

<!-- گالری تصاویر -->
<section class="section-block">
    <div class="section-title">
        <h2>📸 گالری تصاویر</h2>
    </div>
    
    <div class="gallery-grid">
        <div class="gallery-item">
            <img src="image/pool2.jpg" alt="نمای داخلی استخر" onclick="openModal(this.src)">
        </div>   
        <div class="gallery-item">
            <img src="image/sonajakozi.jpg" alt="سونا و جکوزی" onclick="openModal(this.src)">
        </div>  
        <div class="gallery-item">
            <img src="image/rpool1.jpg" alt="رختکن" onclick="openModal(this.src)">
        </div>          
        <div class="gallery-item">
            <img src="image/اموزش.jpg" alt="آموزش شنا" onclick="openModal(this.src)">
        </div>  
        <div class="gallery-item">
            <img src="image/rezerve.jpg" alt="پذیرش" onclick="openModal(this.src)">
        </div> 
        <div class="gallery-item">
            <img src="image/mpool1.jpg" alt="استخر موج" onclick="openModal(this.src)">
        </div>   
        <div class="gallery-item">
            <img src="image/mpool2.jpg" alt="دیگر نمای استخر" onclick="openModal(this.src)">
        </div>  
    </div>

    <!-- مودال نمایش تصاویر -->
    <div id="imageModal" class="modal" onclick="closeModal()">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="fullImage">
    </div>
</section>

<!-- بخش نرخ‌ها -->
<section class="section-block">
    <div class="section-title">
        <h2>💰 نرخ‌های بلیت</h2>
    </div>
    
    <div class="grid-3">
        <div class="feature-card">
            <h3>بلیت تک‌نفره</h3>
            <p style="font-size:24px; font-weight:bold; color:var(--primary-color); margin:12px 0;">
                ۴۵۰,۰۰۰ تومان
            </p>
            <a href="<?php echo $is_logged_in ? 'reserve.php?count=1' : 'register.php'; ?>" class="app-btn app-btn-primary" style="width:100%; text-align:center;">خرید</a>
        </div>
        
        <div class="feature-card">
            <h3>پکیج ۲ نفره</h3>
            <p style="font-size:24px; font-weight:bold; color:var(--success-color); margin:12px 0;">
                ۸۵۵,۰۰۰ تومان
            </p>
            <p style="font-size:12px; color:var(--text-muted);">تخفیف ۵٪</p>
            <a href="<?php echo $is_logged_in ? 'reserve.php?count=2' : 'register.php'; ?>" class="app-btn app-btn-success" style="width:100%; text-align:center;">خرید</a>
        </div>
        
        <div class="feature-card">
            <h3>پکیج ۵+ نفره</h3>
            <p style="font-size:24px; font-weight:bold; color:var(--warning-color); margin:12px 0;">
                ۱,۸۰۰,۰۰۰ تومان
            </p>
            <p style="font-size:12px; color:var(--text-muted);">تخفیف ۲۰٪</p>
            <a href="<?php echo $is_logged_in ? 'reserve.php?count=5' : 'register.php'; ?>" class="app-btn app-btn-success" style="width:100%; text-align:center;">خرید</a>
        </div>
    </div>
</section>

<script>
function hideSplash() {
    document.getElementById('splash-screen').style.display = 'none';
    const video = document.getElementById('splash-video');
    if (video) video.pause();
}

function openModal(src) {
    document.getElementById('fullImage').src = src;
    document.getElementById('imageModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('imageModal').style.display = 'none';
}

// نمای�� تیزر برای بازدید‌کنندگان
window.addEventListener('load', function() {
    const visited = localStorage.getItem('absar_visited');
    if (!visited) {
        document.getElementById('splash-screen').style.display = 'block';
        localStorage.setItem('absar_visited', 'true');
        
        const video = document.getElementById('splash-video');
        video.addEventListener('ended', hideSplash);
        setTimeout(hideSplash, 25000); // 25 ثانیه
    }
});

// بستن مودال با کلیک روی تصویر
document.addEventListener('click', function(e) {
    const modal = document.getElementById('imageModal');
    if (e.target === modal) {
        closeModal();
    }
});
</script>

<?php require_once 'includes/layout_end.php'; ?>
