<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/config.php';
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
                    logActivity(
                        'update_order',
                        'Updated order #' . $id . ' status to: ' . $status,
                        $_SESSION['user_id'],
                        $_SESSION['user_name']
                    );
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
                logActivity(
                    'delete_order',
                    'Deleted order #' . $id,
                    $_SESSION['user_id'],
                    $_SESSION['user_name']
                );
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
    <style>
        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px 0;
            margin-bottom: 15px;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            padding: 5px 12px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        
        .search-box input {
            border: none;
            background: transparent;
            padding: 6px 0;
            outline: none;
            width: 200px;
        }
        
        .filter-box select {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
        }
        
        .status-select {
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            background: #fff;
            cursor: pointer;
        }
        
        .badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; color: #fff; }
        .badge-primary { background: #007bff; color: #fff; }
        .badge-success { background: #28a745; color: #fff; }
        .badge-danger { background: #dc3545; color: #fff; }
        .badge-secondary { background: #6c757d; color: #fff; }
        
        .btn-sm {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-edit {
            background: #28a745;
            color: #fff;
        }
        
        .btn-delete {
            background: #dc3545;
            color: #fff;
        }
        
        .btn-edit:hover, .btn-delete:hover {
            opacity: 0.8;
        }
        
        .order-details {
            padding: 10px 0;
        }
        
        .order-details p {
            margin: 5px 0;
        }
        
        .order-details hr {
            margin: 15px 0;
            border: none;
            border-top: 1px solid #dee2e6;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .text-muted {
            color: #6c757d;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        .text-center {
            text-align: center;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .modal-header h2 {
            margin: 0;
        }
        
        .close {
            font-size: 28px;
            font-weight: 700;
            cursor: pointer;
            color: #aaa;
        }
        
        .close:hover {
            color: #333;
        }
        
        .admin-wrapper {
            display: flex;
        }
        
        .admin-main {
            flex: 1;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .admin-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-body {
            padding: 20px;
            overflow-x: auto;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }
        
        .admin-table td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .admin-table tr:hover {
            background: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .table-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .admin-table {
                font-size: 13px;
            }
            
            .admin-table th,
            .admin-table td {
                padding: 6px 8px;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
       <?php include "sidebar.php" ?>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <span class="badge badge-info">Total: <?php echo count($orders); ?> orders</span>
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
                        if (data.items && data.items.length > 0) {
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
                        } else {
                            html += `
                                <tr>
                                    <td colspan="4" style="text-align:center; color:#888; padding:15px;">
                                        <i class="fas fa-box-open"></i> No items found
                                    </td>
                                </tr>
                            `;
                        }
                        html += `
                                    </tbody>
                                </table>
                                <div style="text-align: right; margin-top: 15px; font-size: 18px; font-weight: 700; color: #05573c;">
                                    Grand Total: ${formatPrice(data.order.total)}
                                </div>
                            </div>
                        `;
                        document.getElementById('orderDetails').innerHTML = html;
                    } else {
                        document.getElementById('orderDetails').innerHTML = '<p class="text-danger">Failed to load order details: ' + (data.message || 'Unknown error') + '</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('orderDetails').innerHTML = '<p class="text-danger">Error loading order details. Please try again.</p>';
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
                        const statusSelect = statusCell.querySelector('select');
                        if (statusSelect) {
                            const status = statusSelect.value.toLowerCase();
                            row.style.display = !filter || status === filter ? '' : 'none';
                        }
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

        // ===== AUTO-HIDE ALERTS =====
        setTimeout(function() {
            document.querySelectorAll('.alert-persistent').forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>
