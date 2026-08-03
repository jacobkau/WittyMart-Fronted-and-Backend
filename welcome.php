<?php
// Start session and check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: home.php');
    exit();
}

// Get user info
$userName = $_SESSION['user_name'] ?? 'Dear Customer';
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Get user data from database
$userData = [];
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'includes/config.php';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
    } catch (Exception $e) {
        // Silently fail
    }
}

// Example stats (replace with actual database queries)
$totalOrders = 0;
$totalRevenue = 0;
$totalCustomers = 0;
$totalProducts = 0;

try {
    // Get order stats
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM orders WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $orderStats = $stmt->fetch();
    $totalOrders = $orderStats['count'] ?? 0;
    $totalRevenue = $orderStats['total'] ?? 0;
    
    // Get recent orders
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $recentOrders = $stmt->fetchAll();
} catch (Exception $e) {
    $recentOrders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WittyMart</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Dashboard styles */
        .welcome-section {
            background: linear-gradient(135deg, #05573c 0%, #0a7a55 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .welcome-section h1 {
            font-size: 28px;
            margin: 0 0 5px 0;
        }
        
        .welcome-section p {
            opacity: 0.9;
            margin: 0 0 10px 0;
        }
        
        .badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 30px;
            color: #05573c;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-label {
            color: #888;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }
        
        .card-header h3 i {
            color: #05573c;
            margin-right: 8px;
        }
        
        .card-header a {
            color: #05573c;
            text-decoration: none;
            font-size: 13px;
        }
        
        .order-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-info h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #333;
        }
        
        .order-info p {
            margin: 0;
            font-size: 12px;
            color: #888;
        }
        
        .order-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .order-status.shipped {
            background: #d4edda;
            color: #155724;
        }
        
        .order-status.delivered {
            background: #cce5ff;
            color: #004085;
        }
        
        .order-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .order-status.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-status.processing {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .notification-item {
            padding: 12px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notif-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #d4edda;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #155724;
            flex-shrink: 0;
        }
        
        .notif-content {
            flex: 1;
        }
        
        .notif-content p {
            margin: 0;
            font-size: 13px;
            color: #333;
        }
        
        .notif-time {
            font-size: 11px;
            color: #888;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 15px;
        }
        
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background: #05573c;
            color: white;
        }
        
        .action-btn i {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .action-btn span {
            font-size: 12px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        /* Dark mode */
        body.dark-mode .stat-card {
            background: #1a1a2e;
        }
        
        body.dark-mode .stat-number {
            color: #eee;
        }
        
        body.dark-mode .dashboard-card {
            background: #1a1a2e;
        }
        
        body.dark-mode .card-header {
            border-bottom-color: #2a2a3e;
        }
        
        body.dark-mode .card-header h3 {
            color: #eee;
        }
        
        body.dark-mode .order-item {
            border-bottom-color: #2a2a3e;
        }
        
        body.dark-mode .order-info h4 {
            color: #eee;
        }
        
        body.dark-mode .notification-item {
            border-bottom-color: #2a2a3e;
        }
        
        body.dark-mode .notif-content p {
            color: #eee;
        }
        
        body.dark-mode .action-btn {
            background: #2a2a3e;
            color: #eee;
        }
        
        body.dark-mode .action-btn:hover {
            background: #05573c;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .welcome-section h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <main>
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1><i class="fas fa-user-circle"></i> Welcome back, <?php echo htmlspecialchars($userName); ?>!</h1>
            <p>Here's what's happening with your account today.</p>
            <?php if ($userData): ?>
                <span class="badge"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($userData['email'] ?? ''); ?></span>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <span class="badge" style="background: #ffc107; color: #333;"><i class="fas fa-crown"></i> Administrator</span>
            <?php endif; ?>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                <div class="stat-number"><?php echo $totalOrders; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="stat-number">Ksh <?php echo number_format($totalRevenue, 0); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-number"><?php echo date('M d, Y'); ?></div>
                <div class="stat-label">Today's Date</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user"></i></div>
                <div class="stat-number"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?></div>
                <div class="stat-label">Logged In As</div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Recent Orders -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                    <a href="orders.php">View All →</a>
                </div>
                <?php if (!empty($recentOrders)): ?>
                    <?php foreach ($recentOrders as $order): ?>
                        <div class="order-item">
                            <div class="order-info">
                                <h4>#<?php echo htmlspecialchars($order['order_number'] ?? 'ORD-' . $order['id']); ?></h4>
                                <p><?php echo date('M d, Y • h:i A', strtotime($order['created_at'])); ?></p>
                                <p style="margin-top: 3px; font-weight: 600; color: #05573c;">
                                    Ksh <?php echo number_format($order['total'], 0); ?>
                                </p>
                            </div>
                            <span class="order-status <?php echo strtolower($order['status'] ?? 'pending'); ?>">
                                <?php echo htmlspecialchars($order['status'] ?? 'Pending'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-bag"></i>
                        <p>You haven't placed any orders yet.</p>
                        <a href="shop.php" style="color: #05573c; font-weight: 600;">Start Shopping →</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Notifications & Quick Actions -->
            <div>
                <!-- Notifications -->
                <div class="dashboard-card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                    </div>
                    <div class="notification-item">
                        <div class="notif-icon"><i class="fas fa-check"></i></div>
                        <div class="notif-content">
                            <p>Welcome to WittyMart! Start shopping today.</p>
                            <span class="notif-time">Just now</span>
                        </div>
                    </div>
                    <div class="notification-item">
                        <div class="notif-icon" style="background:#3498db;"><i class="fas fa-info-circle"></i></div>
                        <div class="notif-content">
                            <p>Check out our latest deals in the shop.</p>
                            <span class="notif-time">1 day ago</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="shop.php" class="action-btn">
                            <i class="fas fa-shopping-bag"></i>
                            <span>Shop Now</span>
                        </a>
                        <a href="cart.php" class="action-btn">
                            <i class="fas fa-shopping-cart"></i>
                            <span>View Cart</span>
                        </a>
                        <?php if ($isAdmin): ?>
                            <a href="admin/dashboard.php" class="action-btn">
                                <i class="fas fa-crown"></i>
                                <span>Admin Panel</span>
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="action-btn" style="background: #fee;">
                            <i class="fas fa-sign-out-alt" style="color: #e74c3c;"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include "footer.php"; ?>

    <script src="script.js" defer></script>
</body>
</html>
