<?php
// header.php - Complete Admin Header with Notifications
session_start();

// Include database configuration
require_once 'includes/config.php';

// Function to get all notifications with counts
function getNotificationCounts($pdo) {
    $notifications = [];
    $total = 0;
    
    try {
        // 1. Orders pending delivery
        $query = "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'";
        $stmt = $pdo->query($query);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)$row['count'];
        $notifications['orders'] = [
            'count' => $count,
            'icon' => 'fa-truck',
            'color' => 'primary',
            'label' => 'Pending Orders',
            'link' => 'orders.php?status=pending',
            'description' => 'Orders awaiting delivery'
        ];
        $total += $count;
        
        // 2. Contact us messages
        $query = "SELECT COUNT(*) as count FROM contact_us WHERE status = 'unread'";
        $stmt = $pdo->query($query);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)$row['count'];
        $notifications['contact'] = [
            'count' => $count,
            'icon' => 'fa-envelope',
            'color' => 'info',
            'label' => 'Contact Messages',
            'link' => 'contact_messages.php?status=unread',
            'description' => 'Unread contact form submissions'
        ];
        $total += $count;
        
        // 3. Newsletter subscriptions
        $query = "SELECT COUNT(*) as count FROM newsletter_subscribers WHERE status = 'pending'";
        $stmt = $pdo->query($query);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)$row['count'];
        $notifications['newsletter'] = [
            'count' => $count,
            'icon' => 'fa-newspaper',
            'color' => 'success',
            'label' => 'Newsletter Subscribers',
            'link' => 'newsletter.php?status=pending',
            'description' => 'Pending subscription confirmations'
        ];
        $total += $count;
        
        // 4. Agent chat requests
        $query = "SELECT COUNT(*) as count FROM agent_chat_requests WHERE status = 'pending'";
        $stmt = $pdo->query($query);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)$row['count'];
        $notifications['agents'] = [
            'count' => $count,
            'icon' => 'fa-headset',
            'color' => 'warning',
            'label' => 'Agent Requests',
            'link' => 'agent_requests.php?status=pending',
            'description' => 'Chat requests from customers'
        ];
        $total += $count;
        
        // 5. Failed admin login attempts (last 24 hours)
        $query = "SELECT COUNT(*) as count FROM activity_logs 
                  WHERE action = 'failed_login' 
                  AND created_at > NOW() - INTERVAL '24 hours'
                  AND user_name = 'admin'";
        $stmt = $pdo->query($query);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)$row['count'];
        $notifications['login_attempts'] = [
            'count' => $count,
            'icon' => 'fa-shield-alt',
            'color' => 'danger',
            'label' => 'Failed Logins',
            'link' => 'activity_logs.php?action=failed_login',
            'description' => 'Suspicious admin login attempts'
        ];
        $total += $count;
        
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
    }
    
    return [
        'total' => $total,
        'items' => $notifications
    ];
}

// Get notification data
$notifData = getNotificationCounts($pdo);

// Get user data
$user_name = $_SESSION['user_name'] ?? 'Administrator';
$user_role = $_SESSION['user_role'] ?? 'admin';
$user_avatar = $_SESSION['user_avatar'] ?? 'default.jpg';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- ============================================ -->
<!-- ADMIN HEADER                                 -->
<!-- ============================================ -->
<header class="nh-admin-header">
    <!-- Left: Page Title -->
    <div class="nh-header-left">
        <h1 class="nh-page-title">
            <i class="fas fa-tachometer-alt nh-title-icon"></i>
            <?= htmlspecialchars($page_title ?? 'Dashboard') ?>
        </h1>
    </div>

    <!-- Right: Notifications & User -->
    <div class="nh-header-right">
        
        <!-- ===== NOTIFICATIONS DROPDOWN ===== -->
        <div class="nh-notif-dropdown">
            <button class="nh-notif-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell nh-notif-icon"></i>
                <?php if ($notifData['total'] > 0): ?>
                    <span class="nh-notif-badge"><?= $notifData['total'] ?></span>
                <?php endif; ?>
            </button>
            
            <ul class="dropdown-menu nh-notif-menu dropdown-menu-end">
                <!-- Header -->
                <li class="nh-notif-header">
                    <span class="nh-notif-title">
                        <i class="fas fa-bell"></i> Notifications
                    </span>
                    <a href="notifications.php" class="nh-notif-view-all">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </li>
                
                <li><hr class="dropdown-divider"></li>
                
                <!-- Notification Items -->
                <?php 
                $hasNotifications = false;
                foreach ($notifData['items'] as $key => $item): 
                    if ($item['count'] > 0):
                        $hasNotifications = true;
                ?>
                    <li>
                        <a class="dropdown-item nh-notif-item" href="<?= $item['link'] ?>">
                            <div class="nh-notif-icon-wrapper bg-<?= $item['color'] ?>">
                                <i class="fas <?= $item['icon'] ?>"></i>
                            </div>
                            <div class="nh-notif-content">
                                <span class="nh-notif-label"><?= $item['label'] ?></span>
                                <span class="nh-notif-desc"><?= $item['description'] ?></span>
                                <span class="nh-notif-count">
                                    <span class="badge bg-<?= $item['color'] ?>">
                                        <?= $item['count'] ?> new
                                    </span>
                                </span>
                            </div>
                            <span class="nh-notif-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        </a>
                    </li>
                <?php 
                    endif;
                endforeach; 
                ?>
                
                <!-- Empty State -->
                <?php if (!$hasNotifications): ?>
                    <li class="nh-notif-empty">
                        <i class="fas fa-check-circle"></i>
                        <span>All caught up!</span>
                        <small>No new notifications</small>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- ===== USER PROFILE ===== -->
        <div class="nh-user-dropdown">
            <a href="#" class="nh-user-profile" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="nh-user-avatar">
                    <img src="assets/images/avatars/<?= htmlspecialchars($user_avatar) ?>" 
                         alt="Profile" 
                         onerror="this.src='assets/images/avatars/default.jpg'">
                    <span class="nh-user-status online"></span>
                </div>
                <div class="nh-user-info">
                    <span class="nh-user-name">
                        <?= htmlspecialchars($user_name) ?>
                    </span>
                    <span class="nh-user-role">
                        <i class="fas fa-user-shield"></i> 
                        <?= ucfirst(htmlspecialchars($user_role)) ?>
                    </span>
                </div>
                <i class="fas fa-chevron-down nh-user-chevron"></i>
            </a>
            
            <!-- User Dropdown Menu -->
            <ul class="dropdown-menu nh-user-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="profile.php">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="notifications.php">
                        <i class="fas fa-bell"></i> Notifications
                        <?php if ($notifData['total'] > 0): ?>
                            <span class="badge bg-danger ms-2"><?= $notifData['total'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- ============================================ -->
<!-- STYLES                                      -->
<!-- ============================================ -->
<style>
/* ==============================================
   NH ADMIN HEADER - Unique Class Names
   ============================================== */
.nh-admin-header {
    background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%);
    padding: 12px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #3498db;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.2);
    position: sticky;
    top: 0;
    z-index: 1050;
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
    color: #ffffff;
    margin: 0;
    letter-spacing: -0.3px;
}

.nh-title-icon {
    color: #3498db;
    margin-right: 12px;
}

/* Right Side */
.nh-header-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* ==============================================
   NOTIFICATIONS
   ============================================== */
.nh-notif-dropdown {
    position: relative;
}

.nh-notif-toggle {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
    cursor: pointer;
    color: #ffffff;
}

.nh-notif-toggle:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.05);
    border-color: rgba(255, 255, 255, 0.3);
}

.nh-notif-toggle:focus {
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.4);
}

.nh-notif-icon {
    font-size: 20px;
    color: #ffffff;
}

.nh-notif-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #e74c3c;
    color: white;
    font-size: 10px;
    font-weight: 700;
    min-width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #1a2332;
    padding: 0 5px;
    animation: nh-pulse 2s infinite;
}

@keyframes nh-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
}

/* Notification Menu */
.nh-notif-menu {
    min-width: 380px;
    max-width: 420px;
    padding: 0;
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);
    margin-top: 12px;
    overflow: hidden;
    background: #ffffff;
}

.nh-notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.nh-notif-title {
    font-weight: 700;
    font-size: 15px;
    color: #1a2332;
}

.nh-notif-title i {
    color: #3498db;
    margin-right: 8px;
}

.nh-notif-view-all {
    font-size: 13px;
    color: #3498db;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.nh-notif-view-all:hover {
    color: #2980b9;
    text-decoration: none;
}

.nh-notif-view-all i {
    transition: transform 0.3s ease;
}

.nh-notif-view-all:hover i {
    transform: translateX(4px);
}

.nh-notif-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 20px;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
    text-decoration: none;
}

.nh-notif-item:hover {
    background: #f8f9fa;
    border-left-color: #3498db;
    text-decoration: none;
}

.nh-notif-icon-wrapper {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.nh-notif-icon-wrapper i {
    font-size: 18px;
    color: white;
}

/* Color classes for notification icons */
.bg-primary { background: #3498db; }
.bg-info { background: #17a2b8; }
.bg-success { background: #28a745; }
.bg-warning { background: #ffc107; }
.bg-danger { background: #dc3545; }

.nh-notif-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.nh-notif-label {
    font-size: 14px;
    font-weight: 600;
    color: #1a2332;
}

.nh-notif-desc {
    font-size: 12px;
    color: #6c757d;
    margin-top: 1px;
}

.nh-notif-count {
    margin-top: 4px;
}

.nh-notif-count .badge {
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 12px;
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

/* Empty State */
.nh-notif-empty {
    text-align: center;
    padding: 40px 20px;
}

.nh-notif-empty i {
    font-size: 50px;
    color: #28a745;
    display: block;
    margin-bottom: 12px;
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

/* ==============================================
   USER PROFILE
   ============================================== */
.nh-user-dropdown {
    position: relative;
}

.nh-user-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 16px 6px 6px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 50px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
}

.nh-user-profile:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.2);
    text-decoration: none;
}

.nh-user-profile:focus {
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.4);
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
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.nh-user-status {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #1a2332;
}

.nh-user-status.online {
    background: #28a745;
}

.nh-user-status.away {
    background: #ffc107;
}

.nh-user-status.busy {
    background: #dc3545;
}

.nh-user-status.offline {
    background: #6c757d;
}

.nh-user-info {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.nh-user-name {
    font-weight: 600;
    font-size: 14px;
    color: #ffffff;
}

.nh-user-role {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.6);
}

.nh-user-role i {
    font-size: 10px;
}

.nh-user-chevron {
    color: rgba(255, 255, 255, 0.4);
    font-size: 12px;
    margin-left: 4px;
    transition: transform 0.3s ease;
}

.nh-user-profile:hover .nh-user-chevron {
    transform: rotate(180deg);
}

/* User Dropdown Menu */
.nh-user-menu {
    min-width: 220px;
    padding: 8px 0;
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    margin-top: 12px;
}

.nh-user-menu .dropdown-item {
    padding: 10px 20px;
    font-size: 14px;
    color: #1a2332;
    transition: all 0.2s ease;
}

.nh-user-menu .dropdown-item i {
    width: 20px;
    margin-right: 10px;
    color: #6c757d;
    text-align: center;
}

.nh-user-menu .dropdown-item:hover {
    background: #f8f9fa;
    color: #3498db;
}

.nh-user-menu .dropdown-item:hover i {
    color: #3498db;
}

.nh-user-menu .dropdown-item.text-danger:hover {
    background: #f8d7da;
    color: #dc3545;
}

.nh-user-menu .dropdown-item.text-danger:hover i {
    color: #dc3545;
}

.nh-user-menu .dropdown-divider {
    margin: 6px 0;
}

/* ==============================================
   RESPONSIVE
   ============================================== */
@media (max-width: 992px) {
    .nh-admin-header {
        padding: 10px 20px;
        min-height: 64px;
    }
    
    .nh-page-title {
        font-size: 20px;
    }
}

@media (max-width: 768px) {
    .nh-admin-header {
        padding: 8px 16px;
        min-height: 60px;
    }
    
    .nh-page-title {
        font-size: 17px;
    }
    
    .nh-page-title i {
        display: none;
    }
    
    .nh-notif-menu {
        min-width: 320px;
        max-width: 350px;
        right: -10px !important;
    }
    
    .nh-user-info {
        display: none;
    }
    
    .nh-user-profile {
        padding: 4px;
    }
    
    .nh-user-chevron {
        display: none;
    }
    
    .nh-notif-desc {
        display: none;
    }
}

@media (max-width: 480px) {
    .nh-admin-header {
        padding: 6px 12px;
        min-height: 56px;
        flex-wrap: wrap;
    }
    
    .nh-header-left {
        flex: 1;
    }
    
    .nh-page-title {
        font-size: 15px;
    }
    
    .nh-notif-menu {
        min-width: 280px;
        max-width: 300px;
        right: -15px !important;
    }
    
    .nh-notif-item {
        padding: 10px 14px;
    }
    
    .nh-notif-header {
        padding: 12px 16px;
    }
    
    .nh-notif-label {
        font-size: 13px;
    }
    
    .nh-notif-icon-wrapper {
        width: 36px;
        height: 36px;
    }
    
    .nh-notif-icon-wrapper i {
        font-size: 15px;
    }
    
    .nh-user-avatar {
        width: 34px;
        height: 34px;
    }
}
</style>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Page Content Starts Here -->
<div class="container-fluid mt-4">
    <!-- Your page content goes here -->
</div>
</body>
</html>
