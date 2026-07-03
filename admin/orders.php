<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/config.php';
require_once 'includes/auth.php';

requireAdmin();

// ===== GET DATABASE CONNECTION =====
global $pdo;

// Handle status update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $status = sanitize($_POST['status'] ?? '');
        
        if ($status) {
            try {
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                if ($stmt->execute([$status, $id])) {
                    $message = 'Order status updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update order status.';
                    $messageType = 'error';
                }
            } catch (PDOException $e) {
                error_log('Update order status error: ' . $e->getMessage());
                $message = 'Database error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // Handle delete
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        try {
            // First delete order items
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$id]);
            
            // Then delete order
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = 'Order deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete order.';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            error_log('Delete order error: ' . $e->getMessage());
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ===== GET ORDERS =====
try {
    $stmt = $pdo->query("
        SELECT o.*, u.name as customer_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get orders error: ' . $e->getMessage());
    $orders = [];
}

$page_title = 'Orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - WittyMart Admin</title>
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
       <?php include "sidebar.php" ?>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:10px:">
                <h1 style="margin-bottom:10px:"><i class="fas fa-shopping-cart"></i> Orders</h1>
                <span style="margin-bottom:10px:" class="badge badge-info">Total: <?php echo count($orders); ?></span>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
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

                    <?php if (count($orders) > 0): ?>
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
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo htmlspecialchars($order['id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></td>
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
                                        <td><?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></td>
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
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted text-center" style="padding: 40px 0;">
                            <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                            No orders found
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- View Order Modal -->
    <div id="viewOrderModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2><i class="fas fa-eye"></i> Order Details</h2>
                <span class="close" onclick="closeModal('viewOrderModal')">&times;</span>
            </div>
            <div id="orderDetails">
                <p class="text-muted">Loading order details...</p>
            </div>
        </div>
    </div>

    <script>
        // ===== MODAL FUNCTIONS =====
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });

        // ===== VIEW ORDER =====
        function viewOrder(id) {
            openModal('viewOrderModal');
            document.getElementById('orderDetails').innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading order details...</p>';
            
            // Fetch order details via AJAX
            fetch('includes/ajax.php?action=get_order&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = `
                            <div class="order-details">
                                <p><strong>Order #:</strong> ${data.order.id}</p>
                                <p><strong>Customer:</strong> ${data.order.customer_name || 'Guest'}</p>
                                <p><strong>Total:</strong> ${formatPrice(data.order.total)}</p>
                                <p><strong>Status:</strong> <span class="badge ${getStatusBadge(data.order.status)}">${data.order.status}</span></p>
                                <p><strong>Date:</strong> ${data.order.created_at}</p>
                                <p><strong>Payment Method:</strong> ${data.order.payment_method || 'N/A'}</p>
                                <p><strong>Shipping Address:</strong> ${data.order.shipping_address || 'N/A'}</p>
                                <hr>
                                <h3>Order Items</h3>
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        data.items.forEach(item => {
                            html += `
                                <tr>
                                    <td>${item.product_name}</td>
                                    <td>${item.quantity}</td>
                                    <td>${formatPrice(item.price)}</td>
                                    <td>${formatPrice(item.quantity * item.price)}</td>
                                </tr>
                            `;
                        });
                        html += `
                                    </tbody>
                                </table>
                                <div style="text-align: right; margin-top: 15px;">
                                    <strong>Grand Total: ${formatPrice(data.order.total)}</strong>
                                </div>
                            </div>
                        `;
                        document.getElementById('orderDetails').innerHTML = html;
                    } else {
                        document.getElementById('orderDetails').innerHTML = '<p class="text-danger">Failed to load order details.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('orderDetails').innerHTML = '<p class="text-danger">Error loading order details.</p>';
                });
        }

        // ===== FILTER ORDERS =====
        function filterOrders() {
            const filter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#ordersTable tbody tr');
            rows.forEach(row => {
                if (row.cells.length > 0) {
                    const statusCell = row.cells[3];
                    if (statusCell) {
                        const status = statusCell.textContent?.toLowerCase().trim() || '';
                        row.style.display = !filter || status === filter ? '' : 'none';
                    }
                }
            });
        }

        // ===== FILTER TABLE =====
        function filterTable(inputId, tableId) {
            const input = document.getElementById(inputId);
            const table = document.getElementById(tableId);
            if (!input || !table) return;

            const filter = input.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        }

        // ===== FORMAT PRICE =====
        function formatPrice(price) {
            return 'Ksh ' + parseFloat(price).toFixed(2);
        }

        // ===== GET STATUS BADGE =====
        function getStatusBadge(status) {
            const badges = {
                'pending': 'badge-warning',
                'processing': 'badge-info',
                'shipped': 'badge-primary',
                'delivered': 'badge-success',
                'cancelled': 'badge-danger'
            };
            return badges[status] || 'badge-secondary';
        }
    </script>
</body>
</html>
