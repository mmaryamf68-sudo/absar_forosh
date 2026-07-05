<style>
    .dashboard-container { max-width: 600px; margin: auto; padding: 20px; font-family: tahoma; }
    
    /* کارت پروفایل و کیف پول */
    .user-info-card { background: #f8f9fa; padding: 20px; border-radius: 15px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .wallet-box { font-weight: bold; color: #28a745; }
    
    /* شبکه دکمه‌های عملیاتی */
    .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .action-btn { background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 12px; text-align: center; text-decoration: none; color: #333; transition: 0.3s; }
    .action-btn:hover { background: #e9ecef; }
</style>

<div class="dashboard-container">
    <!-- بخش اطلاعات کاربر -->
    <!-- بخش لینک به پروفایل -->
    <a href="profile.php" class="profile-link">
        <img src="user_avatar.jpg" class="profile-img"> <!-- عکس خود را اینجا بگذارید -->
        <div>پروفایل من</div>
    </a>
        </div>
        <div class="wallet-box">موجودی: <?php echo $wallet_balance; ?> تومان</div>
    </div>

    <!-- دکمه‌های اصلی -->
    <div class="action-grid">
        <a href="profile.php" class="action-btn">👤 پروفایل</a>
        <a href="wallet.php" class="action-btn">💳 کیف پول</a>
        <a href="booking.php" class="action-btn">🎫 رزرو بلیط</a>
        <a href="share.php" class="action-btn">🔗 اشتراک‌گذاری</a>
        <a href="support.php" class="action-btn" style="grid-column: span 2;">💬 پشتیبانی</a>
    </div>
</div>