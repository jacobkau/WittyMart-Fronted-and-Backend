<?php
// admin/main.php - Main Layout with Sidebar + Header
session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get notification counts
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

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page ? 'active' : '';
}

// Start output buffering to capture page content
ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?> - WittyMart Admin</title>
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

        <!-- Sidebar Toggle Button (Mobile) -->
        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>

        <!-- ===== SIDEBAR ===== -->
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <img src="images/logo.png" alt="WittyMart">
                <h2>WittyMart</h2>
                <span class="admin-role">Admin Panel</span>
                <button class="sidebar-close-btn" onclick="closeSidebar()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="<?php echo isActive('dashboard.php'); ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                
                <div class="sidebar-label">Store</div>
                <a href="products.php" class="<?php echo isActive('products.php'); ?>">
                    <i class="fas fa-box"></i> Products
                </a>
                <a href="orders.php" class="<?php echo isActive('orders.php'); ?>">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
                <a href="customers.php" class="<?php echo isActive('customers.php'); ?>">
                    <i class="fas fa-users"></i> Customers
                </a>
                <a href="categories.php" class="<?php echo isActive('categories.php'); ?>">
                    <i class="fas fa-tags"></i> Categories
                </a>
                
                <hr class="sidebar-divider">
             
                <div class="sidebar-label">Account</div>
                <a href="admins.php" class="<?php echo isActive('admins.php'); ?>">
                    <i class="fas fa-user-shield"></i> Admin Management
                </a>
                <a href="profile.php" class="<?php echo isActive('profile.php'); ?>">
                    <i class="fas fa-user-cog"></i> Profile
                </a>
                <a href="settings.php" class="<?php echo isActive('settings.php'); ?>">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="activity_logs.php" class="<?php echo isActive('activity_logs.php'); ?>">
                    <i class="fas fa-history"></i> Activity Logs
                </a>
                
                <hr class="sidebar-divider">
            
                <a href="logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </aside>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="admin-main">
            <!-- ===== HEADER ===== -->
            <div class="nh-admin-header">
                <div class="nh-header-left">
                    <h1 class="nh-page-title">
                        <i class="fas fa-tachometer-alt nh-title-icon"></i>
                        <?= htmlspecialchars($page_title ?? 'Dashboard') ?>
                    </h1>
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
                                <span class="nh-notif-title">
                                    <i class="fas fa-bell"></i> Notifications
                                </span>
                                <a href="notifications.php" class="nh-notif-view-all">
                                    View All <i class="fas fa-arrow-right"></i>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
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
                            
                            <?php if (!$hasNotifications): ?>
                                <li class="nh-notif-empty">
                                    <i class="fas fa-check-circle"></i>
                                    <span>All caught up!</span>
                                    <small>No new notifications</small>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- User Profile -->
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

            <!-- ===== PAGE CONTENT ===== -->
            <div class="content-wrapper">
                <?php
                // This is where the page-specific content will be rendered
                // The content is captured from the page that included main.php
                echo $page_content ?? '';
                ?>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== SIDEBAR TOGGLE FUNCTIONS =====
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const wrapper = document.querySelector('.admin-wrapper');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            
            if (wrapper) {
                wrapper.classList.toggle('sidebar-open');
            }
            
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }

        function closeSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const wrapper = document.querySelector('.admin-wrapper');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            
            if (wrapper) {
                wrapper.classList.remove('sidebar-open');
            }
            
            document.body.style.overflow = '';
        }

        // Close sidebar on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });

        // Close sidebar on window resize (desktop)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        // Prevent clicks inside sidebar from closing it
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('adminSidebar');
            if (sidebar) {
                sidebar.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });
    </script>
    <script src="admin.js"></script>
</body>
</html>
