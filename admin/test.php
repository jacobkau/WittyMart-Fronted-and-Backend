<header class="nh-admin-header">
    <div class="nh-header-left">
        <h1 class="nh-page-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
    </div>

    <div class="nh-header-right">
        <!-- Notifications Dropdown -->
        <div class="nh-notif-dropdown">
            <button class="nh-notif-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell nh-notif-icon"></i>
                <?php if ($notifData['total'] > 0): ?>
                    <span class="nh-notif-badge"><?= $notifData['total'] ?></span>
                <?php endif; ?>
            </button>
            
            <ul class="dropdown-menu nh-notif-menu dropdown-menu-end">
                <li class="nh-notif-header">
                    <span class="nh-notif-title">Notifications</span>
                    <a href="notifications.php" class="nh-notif-view-all">View All</a>
                </li>
                
                <li><hr class="dropdown-divider"></li>
                
                <?php foreach ($notifData['items'] as $key => $item): ?>
                    <?php if ($item['count'] > 0): ?>
                        <li>
                            <a class="dropdown-item nh-notif-item" href="notifications.php?type=<?= $key ?>">
                                <div class="nh-notif-icon-wrapper bg-<?= $item['color'] ?>">
                                    <i class="fas <?= $item['icon'] ?>"></i>
                                </div>
                                <div class="nh-notif-content">
                                    <span class="nh-notif-label"><?= $item['label'] ?></span>
                                    <span class="nh-notif-count"><?= $item['count'] ?> new</span>
                                </div>
                                <span class="nh-notif-arrow">
                                    <i class="fas fa-chevron-right"></i>
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if ($notifData['total'] == 0): ?>
                    <li class="nh-notif-empty">
                        <i class="fas fa-check-circle"></i>
                        <span>All caught up!</span>
                        <small>No new notifications</small>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Admin User Profile -->
        <a href="profile.php" class="nh-user-profile">
            <div class="nh-user-avatar">
                <img src="assets/images/avatars/<?= $_SESSION['user_avatar'] ?? 'default.jpg' ?>" 
                     alt="Profile" 
                     onerror="this.src='assets/images/avatars/default.jpg'">
                <span class="nh-user-status online"></span>
            </div>
            <div class="nh-user-info">
                <span class="nh-user-name">
                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?>
                </span>
                <span class="nh-user-role">Administrator</span>
            </div>
            <i class="fas fa-chevron-down nh-user-chevron"></i>
        </a>
    </div>
</header>

<style>
/* ===== NH ADMIN HEADER ===== */
.nh-admin-header {
    background: #ffffff;
    padding: 12px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e9ecef;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    position: sticky;
    top: 0;
    z-index: 999;
    min-height: 72px;
}

/* Left Side */
.nh-header-left {
    display: flex;
    align-items: center;
}

.nh-page-title {
    font-size: 22px;
    font-weight: 700;
    color: #1a2332;
    margin: 0;
    letter-spacing: -0.3px;
}

/* Right Side */
.nh-header-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* ===== NOTIFICATIONS ===== */
.nh-notif-dropdown {
    position: relative;
}

.nh-notif-toggle {
    background: #f8f9fa;
    border: none;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
    cursor: pointer;
}

.nh-notif-toggle:hover {
    background: #e9ecef;
    transform: scale(1.05);
}

.nh-notif-icon {
    font-size: 20px;
    color: #495057;
}

.nh-notif-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #dc3545;
    color: white;
    font-size: 10px;
    font-weight: 700;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
    animation: nh-pulse 2s infinite;
}

@keyframes nh-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* Notification Menu */
.nh-notif-menu {
    min-width: 360px;
    max-width: 400px;
    padding: 0;
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
    margin-top: 12px;
    overflow: hidden;
}

.nh-notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: #f8f9fa;
}

.nh-notif-title {
    font-weight: 700;
    font-size: 15px;
    color: #1a2332;
}

.nh-notif-view-all {
    font-size: 13px;
    color: #0d6efd;
    text-decoration: none;
    font-weight: 600;
}

.nh-notif-view-all:hover {
    text-decoration: underline;
}

.nh-notif-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 20px;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.nh-notif-item:hover {
    background: #f8f9fa;
    border-left-color: #0d6efd;
}

.nh-notif-icon-wrapper {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.nh-notif-icon-wrapper i {
    font-size: 16px;
    color: white;
}

.bg-primary { background: #0d6efd; }
.bg-info { background: #0dcaf0; }
.bg-success { background: #198754; }
.bg-warning { background: #ffc107; }
.bg-danger { background: #dc3545; }

.nh-notif-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.nh-notif-label {
    font-size: 14px;
    font-weight: 500;
    color: #1a2332;
}

.nh-notif-count {
    font-size: 12px;
    color: #6c757d;
    margin-top: 2px;
}

.nh-notif-arrow {
    color: #adb5bd;
    font-size: 12px;
    opacity: 0;
    transition: all 0.3s ease;
}

.nh-notif-item:hover .nh-notif-arrow {
    opacity: 1;
    transform: translateX(4px);
}

.nh-notif-empty {
    text-align: center;
    padding: 30px 20px;
}

.nh-notif-empty i {
    font-size: 40px;
    color: #198754;
    display: block;
    margin-bottom: 10px;
}

.nh-notif-empty span {
    display: block;
    font-weight: 600;
    font-size: 16px;
    color: #1a2332;
}

.nh-notif-empty small {
    color: #6c757d;
    font-size: 13px;
}

/* ===== USER PROFILE ===== */
.nh-user-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 16px 6px 6px;
    background: #f8f9fa;
    border-radius: 50px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.nh-user-profile:hover {
    background: #e9ecef;
    border-color: #dee2e6;
    text-decoration: none;
}

.nh-user-avatar {
    position: relative;
    width: 40px;
    height: 40px;
    flex-shrink: 0;
}

.nh-user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.nh-user-status {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #ffffff;
}

.nh-user-status.online {
    background: #198754;
}

.nh-user-status.away {
    background: #ffc107;
}

.nh-user-status.busy {
    background: #dc3545;
}

.nh-user-info {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.nh-user-name {
    font-weight: 600;
    font-size: 14px;
    color: #1a2332;
}

.nh-user-role {
    font-size: 11px;
    color: #6c757d;
    font-weight: 500;
}

.nh-user-chevron {
    color: #adb5bd;
    font-size: 12px;
    margin-left: 4px;
    transition: transform 0.3s ease;
}

.nh-user-profile:hover .nh-user-chevron {
    transform: rotate(180deg);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .nh-admin-header {
        padding: 10px 16px;
        min-height: 64px;
    }
    
    .nh-page-title {
        font-size: 18px;
    }
    
    .nh-notif-menu {
        min-width: 300px;
        max-width: 340px;
        right: -10px !important;
    }
    
    .nh-user-info {
        display: none;
    }
    
    .nh-user-profile {
        padding: 6px;
    }
    
    .nh-user-chevron {
        display: none;
    }
}

@media (max-width: 480px) {
    .nh-notif-menu {
        min-width: 280px;
        max-width: 300px;
        right: -20px !important;
    }
    
    .nh-notif-item {
        padding: 10px 14px;
    }
    
    .nh-notif-header {
        padding: 12px 16px;
    }
}
</style>

<!-- Include Bootstrap JS for dropdown functionality -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
