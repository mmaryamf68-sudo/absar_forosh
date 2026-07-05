<style>
    .header-container { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        background: #f4f4f4; 
        padding: 10px 20px; 
        border-radius: 10px; 
        margin-bottom: 20px; 
    }
    .profile-link { text-decoration: none; color: #333; text-align: center; }
    .profile-img { width: 50px; height: 50px; border-radius: 50%; border: 2px solid #ccc; cursor: pointer; }
</style>

<div class="header-container">
    <!-- بخش اطلاعات اشتراک و کیف پول -->
    <div class="stats">
       <span>کیف پول: 830,000 تومان</span>
    </div>

    <!-- بخش لینک به پروفایل -->
    <a href="profile.php" class="profile-link">
        <img src="user_avatar.jpg" class="profile-img"> <!-- عکس خود را اینجا بگذارید -->
        <div>پروفایل من</div>
    </a>
</div>