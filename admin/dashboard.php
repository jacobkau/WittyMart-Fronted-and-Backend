<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/auth.php';



requireAdmin();

$stats = getStats();
$orders = getOrders();
$recent_orders = array_slice($orders, 0, 5);

$page_title = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WittyMart Admin</title>
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
       <?php include "sidebar.php"?>

        <!-- Main Content -->
        <main class="admin-main">
          <?php include "header.php"?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['products'] ?? 0; ?></h3>
                        <p>Products</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['orders'] ?? 0; ?></h3>
                        <p>Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $stats['customers'] ?? 0; ?></h3>
                        <p>Customers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-info">
                        <h3><?php echo formatPrice($stats['revenue'] ?? 0); ?></h3>
                        <p>Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="admin-card">
                <div class="card-header">
                    <h2><i class="fas fa-clock"></i> Recent Orders</h2>
                    <a href="orders.php" class="btn-link">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_orders)): ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></td>
                                        <td><?php echo formatPrice($order['total']); ?></td>
                                        <td>
                                            <span class="badge <?php echo getStatusBadge($order['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No orders found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="admin.js"></script>
</body>
</html>
