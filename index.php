<?php
session_start();

$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['user_name'] : '';

$page_title = 'مجموعه فرهنگی ورزشی استخر آبسار';
$layout_mode = 'public';
$active_page = 'home';
$show_footer = true;
require_once 'includes/layout_start.php';
?>
<div id="splash-screen">
    <video autoplay muted playsinline id="splash-video">
        <source src="video/pool7.mp4" type="video/mp4">
    </video>
    <div class="skip-btn" onclick="hideSplash()">رد کردن تیزر</div>
</div>

<script>
    const video = document.getElementById('splash-video');
    const splashScreen = document.getElementById('splash-screen');

    // این دستور برای زمانی است که ویدیو تمام می‌شود
  video.addEventListener('timeupdate', function() {
    if (this.currentTime >= 21) { // اگر زمان ویدیو به 21 ثانیه رسید
        hideSplash();
    }
});

    function hideSplash() {
        splashScreen.style.display = 'none';
        // اختیاری: برای اینکه ویدیو در پس‌زمینه مصرف باتری نداشته باشد
        video.pause(); 
    }
    
</script>

<section class="public-hero">
    <h1>به مجموعه فرهنگی ورزشی استخر آبسار خوش آمدید</h1>
    <p>محیطی مجهز و بهداشتی برای خانواده‌ها. عضویت در باشگاه مشتریان، کیف پول هوشمند و خرید آنلاین بلیت با تخفیف‌های گروهی.</p>
    <?php if ($is_logged_in): ?>
        <a href="user_dashboard.php" class="btn-cta">🎟️ خرید آنلاین بلیت و رزرو سانس</a>
    <?php else: ?>
        <a href="register.php" class="btn-cta">🚀 عضویت و خرید بلیت</a>
    <?php endif; ?>
</section>

<section class="section-block" id="facilities">
    <div class="section-title">
        <h2>امکانات و خدمات مجموعه آبسار</h2>
        <p style="color:var(--text-muted);">مدرن‌ترین تجهیزات آبی تحت نظارت کادر مجرب</p>
    </div>
    <div class="grid-3">
        <div class="feature-card">
            <div class="icon">🏊‍♂️</div>
            <h3>استخر بزرگسالان</h3>
            <p>سیستم تصفیه مکانیزه هیدروژنی بدون حساسیت چشمی و پوستی.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🔥</div>
            <h3>سونا و جکوزی</h3>
            <p>اتاق‌های سونای مجزا با دمای استاندارد برای ریلکسیشن.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🛍️</div>
            <h3>کافی شاپ</h3>
            <p>کافی شاپ مجموعه در کل ساعات اماده پذیرایی از مهمانان ارجمند میباشد.ارایه انواع فست فود ونوشیدنی گرم و سرد </p>
        </div>
        <div class="feature-card">
            <div class="icon">🛍️</div>
            <h3>بوفه</h3>
            <p>بوفه مجموعه ارایه کننده انواع لوازم شنا مبتدی تا حرفه ای ومحصولات خوراکی .</p>
        </div>
          <div class="feature-card">
            <div class="icon">🛍️</div>
            <h3>اتاق ماساژ</h3>
            <p>💆💆‍♂️ ارایه ماساژ ریلکسی و درمانی با کادر حرفه ای.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🛍️</div>
            <h3>باشگاه مشتریان</h3>
            <p>شارژ حساب، پکیج‌های گروهی و تخفیف پلکانی ۵ تا ۲۰ درصدی.</p>
        </div>
    </div>
</section>

<section class="section-block about-box" id="about">
    <div class="section-title"><h2>درباره مجموعه</h2></div>
    <p style="text-align:justify;color:#475569;line-height:2;">
     مجموعه ابی ابسار در زمینی به مساحت 4000 متر مربع و با زیربنایی بالغ بر 2600 متر مربع و فضای پارکینگ اختصاصی برابر 1000 متر مربع هم اکنون بزرگ ترین و مجهز ترین مجموعه ابی استان گیلان با مجوز رسمی از اداره کل ورزش و جوانان گیلان بشماره 220/17/19325 میباشد.
     
    </p>
</section>
...
   <script>
       function openModal(src) {
           document.getElementById("fullImage").src = src;
           document.getElementById("imageModal").style.display = "block";
       }

       function closeModal() {
           document.getElementById("imageModal").style.display = "none";
       }
   </script>

<section class="section-block" id="gallery">
    <div class="section-title"><h2>گالری تصاویر</h2></div>
    <div class="gallery-grid">
        <div class="gallery-item">
            <img src="image/pool2.jpg" alt="نمای داخلی استخر" onclick="openModal(this.src)"/>
        </div>   
        <div class="gallery-item">
            <img src="image/sonajakozi.jpg" alt="سونا " onclick="openModal(this.src)" />
        </div>  
        <div class="gallery-item">
            <img src="image/rpool1.jpg" alt= " رختکن" onclick="openModal(this.src)"/>
        </div>          
        <div class="gallery-item">
            <img src="image/اموزش.jpg" alt="اموزش" onclick="openModal(this.src)"/>
        </div>  
        <div class="gallery-item">
            <img src="image/rezerve.jpg" alt="ساعت پذیرش" onclick="openModal(this.src)"/>
        </div> 
           <div class="gallery-item">
            <img src="image/mpool.jpg" alt="موج" onclick="openModal(this.src)"/>
        </div>   
         <div class="gallery-item">
            <img src="image/mpool2.jpg" alt="موجk" onclick="openModal(this.src)"/>
        </div>  
    </div>   
    <!-- پنجره بزرگ‌نمایی -->
<div id="imageModal" class="modal" onclick="closeModal()">
    <span class="close">&times;</span>
    <img class="modal-content" id="fullImage">
</div>
</section>

<?php require_once 'includes/layout_end.php'; ?>

