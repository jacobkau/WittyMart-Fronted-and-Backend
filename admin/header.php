<?php
session_start();
require_once 'includes/config.php';

function getNotificationCounts($pdo) {
    $notifications = [];
    $total = 0;
    
    try {
 
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


$notifData = getNotificationCounts($pdo);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="nh-admin-header">
    <div class="nh-header-left">
        <h1 class="nh-page-title" style="margin-left:260px">
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
            
            <ul class="dropdown-menu nh-notif-menu dropdown-menu-end" style="width:420px">
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
                    <img src="images/<?= htmlspecialchars($user_avatar) ?>" 
                         alt="Profile" 
                         onerror="this.src='images/default.jpg'">
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
