<?php

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = Database::getInstance();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $status = sanitize($_POST['status'] ?? '');
        
        if ($status && updateOrderStatus($id, $status)) {
            $message = 'Order status updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to update order status.';
            $messageType = 'error';
        }
    }
}

$orders = getOrders();
$page_title = 'Orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - WittyMart Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <img src="../images/Witty Mart.png" alt="WittyMart">
                <h2>WittyMart</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="products.php"><i class="fas fa-box"></i> Products</a>
                <a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="categories.php"><i class="fas fa-tags"></i> Categories</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1><i class="fas fa-shopping-cart"></i> Orders</h1>
                <span class="badge badge-info">Total: <?php echo count($orders); ?></span>
            </header>

            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Orders Table -->
            <div class="admin-card">
                <div class="card-body">
                    <div class="table-toolbar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchOrders" placeholder="Search orders..." onkeyup="filterTable('searchOrders', 'ordersTable')">
                        </div>
                        <div class="filter-box">
                            <select id="statusFilter" onchange="filterOrders()">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <table class="admin-table" id="ordersTable">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($orders) > 0): ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                                        <td><?php echo $order['customer_name'] ?? 'Guest'; ?></td>
                                        <td><?php echo formatPrice($order['total']); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                                <select name="status" onchange="this.form.submit()" class="status-select">
                                                    <?php
                                                    $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                                                    foreach ($statuses as $status):
                                                    ?>
                                                        <option value="<?php echo $status; ?>" <?php echo $order['status'] === $status ? 'selected' : ''; ?>>
                                                            <?php echo ucfirst($status); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo $order['payment_method'] ?? 'N/A'; ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <button class="btn-sm btn-edit" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this order?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="fas fa-inbox" style="font-size: 48px; display: block; margin: 20px 0;"></i>
                                        No orders found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Order Modal -->
    <div id="viewOrderModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Order Details</h2>
                <span class="close" onclick="closeModal('viewOrderModal')">&times;</span>
            </div>
            <div id="orderDetails">
                <p class="text-muted">Loading order details...</p>
            </div>
        </div>
    </div>

    <script>
        function viewOrder(id) {
            openModal('viewOrderModal');
            document.getElementById('orderDetails').innerHTML = '<p class="text-muted">Loading order details...</p>';
            
            // Fetch order details via AJAX
            fetch('../includes/ajax.php?action=get_order&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = `
                            <div class="order-details">
                                <p><strong>Order #:</strong> ${data.order.id}</p>
                                <p><strong>Customer:</strong> ${data.order.customer_name || 'Guest'}</p>
                                <p><strong>Total:</strong> ${data.order.total}</p>
                                <p><strong>Status:</strong> ${data.order.status}</p>
                                <p><strong>Date:</strong> ${data.order.created_at}</p>
                                <h3>Items</h3>
                                <ul>
                        `;
                        data.items.forEach(item => {
                            html += `<li>${item.quantity}x ${item.product_name} - ${item.price}</li>`;
                        });
                        html += `</ul></div>`;
                        document.getElementById('orderDetails').innerHTML = html;
                    } else {
                        document.getElementById('orderDetails').innerHTML = '<p class="text-danger">Failed to load order details.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('orderDetails').innerHTML = '<p class="text-danger">Error loading order details.</p>';
                });
        }

        function filterOrders() {
            const filter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#ordersTable tbody tr');
            rows.forEach(row => {
                if (row.cells.length > 0) {
                    const status = row.cells[3]?.textContent?.toLowerCase().trim() || '';
                    row.style.display = !filter || status === filter ? '' : 'none';
                }
            });
        }
    </script>
</body>
</html>
