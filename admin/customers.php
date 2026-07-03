<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== CORRECT PATHS =====
require_once 'includes/config.php';
require_once 'includes/auth.php';

requireAdmin();

// ===== GET DATABASE CONNECTION =====
global $pdo;

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
            if ($stmt->execute([$id]) && $stmt->rowCount() > 0) {
                $message = 'Customer deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Customer not found or cannot be deleted.';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            error_log('Delete customer error: ' . $e->getMessage());
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ===== GET CUSTOMERS =====
try {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get customers error: ' . $e->getMessage());
    $customers = [];
}

$page_title = 'Customers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
       <?php include "sidebar.php" ?>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px:">
                <span style="margin-bottom:20px:" class="badge badge-info">Total: <?php echo count($customers); ?></span>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Customers Table -->
            <div class="admin-card" style="padding:14px">
                <div class="card-body" style="padding:14px">
                    <div class="table-toolbar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchCustomers" placeholder="Search customers..." onkeyup="filterTable('searchCustomers', 'customersTable')">
                        </div>
                    </div>

                    <?php if (count($customers) > 0): ?>
                        <table class="admin-table" id="customersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Joined</th>
                                    <th>Orders</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($customer['id']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                        <td>
                                            <?php
                                            try {
                                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
                                                $stmt->execute([$customer['id']]);
                                                $orderCount = $stmt->fetch()['count'];
                                                echo $orderCount;
                                            } catch (PDOException $e) {
                                                echo '0';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn-sm btn-edit" onclick="viewCustomer(<?php echo $customer['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">
                                                <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this customer?')">
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
                            <i class="fas fa-users" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                            No customers registered yet
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- View Customer Modal -->
    <div id="viewCustomerModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2><i class="fas fa-user-circle"></i> Customer Details</h2>
                <span class="close" onclick="closeModal('viewCustomerModal')">&times;</span>
            </div>
            <div id="customerDetails">
                <p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading customer details...</p>
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

        // ===== VIEW CUSTOMER =====
        function viewCustomer(id) {
            openModal('viewCustomerModal');
            document.getElementById('customerDetails').innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading customer details...</p>';
            
            fetch('includes/ajax.php?action=get_customer&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = `
                            <div class="customer-details">
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <i class="fas fa-user-circle" style="font-size: 64px; color: #05573c;"></i>
                                </div>
                                <p><strong><i class="fas fa-user"></i> Name:</strong> ${data.customer.name}</p>
                                <p><strong><i class="fas fa-envelope"></i> Email:</strong> ${data.customer.email}</p>
                                <p><strong><i class="fas fa-phone"></i> Phone:</strong> ${data.customer.phone || 'N/A'}</p>
                                <p><strong><i class="fas fa-calendar"></i> Joined:</strong> ${data.customer.created_at}</p>
                                <p><strong><i class="fas fa-shopping-cart"></i> Total Orders:</strong> ${data.order_count}</p>
                                <p><strong><i class="fas fa-money-bill-wave"></i> Total Spent:</strong> ${data.total_spent}</p>
                                ${data.recent_orders && data.recent_orders.length > 0 ? `
                                    <hr>
                                    <h4>Recent Orders</h4>
                                    <ul style="list-style: none; padding: 0;">
                                        ${data.recent_orders.map(order => `
                                            <li style="padding: 5px 0; border-bottom: 1px solid #eee;">
                                                #${order.id} - ${order.total} - ${order.status}
                                            </li>
                                        `).join('')}
                                    </ul>
                                ` : ''}
                            </div>
                        `;
                        document.getElementById('customerDetails').innerHTML = html;
                    } else {
                        document.getElementById('customerDetails').innerHTML = '<p class="text-danger">Failed to load customer details.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('customerDetails').innerHTML = '<p class="text-danger">Error loading customer details.</p>';
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
    </script>
</body>
</html>
