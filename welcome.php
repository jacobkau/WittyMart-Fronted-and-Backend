<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WittyMart</title>
    <link rel="icon" href="images/witty mart.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
 <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <!-- Main Content -->
    <main>
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1><i class="fas fa-user-circle"></i> Welcome back, Dear Customer! </h1>
            <p>Here's what's happening with your store today.</p>
            <span class="badge"><i class="fas fa-star"></i> Premium Member</span>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                <div class="stat-number">156</div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="stat-number">$12,430</div>
                <div class="stat-label">Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number">342</div>
                <div class="stat-label">Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-box"></i></div>
                <div class="stat-number">28</div>
                <div class="stat-label">Products Sold</div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Recent Orders -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                    <a href="#">View All →</a>
                </div>
                <div class="order-item">
                    <div class="order-info">
                        <h4>#ORD-2024-001 - Smart Watch Pro</h4>
                        <p>Ordered by: John Doe • 2 hours ago</p>
                    </div>
                    <span class="order-status shipped">Shipped</span>
                </div>
                <div class="order-item">
                    <div class="order-info">
                        <h4>#ORD-2024-002 - Wireless Headphones</h4>
                        <p>Ordered by: Jane Smith • 5 hours ago</p>
                    </div>
                    <span class="order-status delivered">Delivered</span>
                </div>
                <div class="order-item">
                    <div class="order-info">
                        <h4>#ORD-2024-003 - Laptop Ultrabook</h4>
                        <p>Ordered by: Mike Johnson • 1 day ago</p>
                    </div>
                    <span class="order-status pending">Pending</span>
                </div>
                <div class="order-item">
                    <div class="order-info">
                        <h4>#ORD-2024-004 - Fitness Tracker</h4>
                        <p>Ordered by: Sarah Williams • 2 days ago</p>
                    </div>
                    <span class="order-status cancelled">Cancelled</span>
                </div>
            </div>

            <!-- Notifications & Quick Actions -->
            <div>
                <!-- Notifications -->
                <div class="dashboard-card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                        <a href="#">Mark all read</a>
                    </div>
                    <div class="notification-item">
                        <div class="notif-icon"><i class="fas fa-check"></i></div>
                        <div class="notif-content">
                            <p>Order #ORD-2024-001 has been shipped</p>
                            <span class="notif-time">2 hours ago</span>
                        </div>
                    </div>
                    <div class="notification-item">
                        <div class="notif-icon" style="background:#3498db;"><i class="fas fa-user"></i></div>
                        <div class="notif-content">
                            <p>New customer registered: Sarah Williams</p>
                            <span class="notif-time">5 hours ago</span>
                        </div>
                    </div>
                    <div class="notification-item">
                        <div class="notif-icon" style="background:#2ecc71;"><i class="fas fa-star"></i></div>
                        <div class="notif-content">
                            <p>You received a 5-star review!</p>
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
                        <a href="shop.html" class="action-btn">
                            <i class="fas fa-plus-circle"></i>
                            <span>Add Product</span>
                        </a>
                        <a href="cart.html" class="action-btn">
                            <i class="fas fa-shopping-cart"></i>
                            <span>View Cart</span>
                        </a>
                        <a href="orders.html" class="action-btn">
                            <i class="fas fa-truck"></i>
                            <span>Manage Orders</span>
                        </a>
                        <a href="contact.html" class="action-btn">
                            <i class="fas fa-headset"></i>
                            <span>Support</span>
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
