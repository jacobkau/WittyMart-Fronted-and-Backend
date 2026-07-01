<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

requireAdmin();

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $db->getPDO()->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
        if ($stmt->execute([$id])) {
            $message = 'Customer deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete customer.';
            $messageType = 'error';
        }
    }
}

$users = $db->getUsers();
$customers = array_filter($users, function($user) {
    return $user['role'] === 'user';
});

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
    <div class="admin-wrapper">
       <?php include "sidebar.php" ?>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1><i class="fas fa-users"></i> Customers</h1>
                <span class="badge badge-info">Total: <?php echo count($customers); ?></span>
            </header>

            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Customers Table -->
            <div class="admin-card">
                <div class="card-body">
                    <div class="table-toolbar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchCustomers" placeholder="Search customers..." onkeyup="filterTable('searchCustomers', 'customersTable')">
                        </div>
                    </div>

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
                            <?php if (count($customers) > 0): ?>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td>#<?php echo $customer['id']; ?></td>
                                        <td><?php echo $customer['name']; ?></td>
                                        <td><?php echo $customer['email']; ?></td>
                                        <td><?php echo $customer['phone'] ?? 'N/A'; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                        <td>
                                            <?php
                                            $stmt = $db->getPDO()->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
                                            $stmt->execute([$customer['id']]);
                                            $orderCount = $stmt->fetch()['count'];
                                            echo $orderCount;
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
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="fas fa-users" style="font-size: 48px; display: block; margin: 20px 0;"></i>
                                        No customers registered yet
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- View Customer Modal -->
    <div id="viewCustomerModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Customer Details</h2>
                <span class="close" onclick="closeModal('viewCustomerModal')">&times;</span>
            </div>
            <div id="customerDetails">
                <p class="text-muted">Loading customer details...</p>
            </div>
        </div>
    </div>

    <script>
        function viewCustomer(id) {
            openModal('viewCustomerModal');
            document.getElementById('customerDetails').innerHTML = '<p class="text-muted">Loading customer details...</p>';
            
            fetch('../includes/ajax.php?action=get_customer&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = `
                            <div class="customer-details">
                                <p><strong>Name:</strong> ${data.customer.name}</p>
                                <p><strong>Email:</strong> ${data.customer.email}</p>
                                <p><strong>Phone:</strong> ${data.customer.phone || 'N/A'}</p>
                                <p><strong>Joined:</strong> ${data.customer.created_at}</p>
                                <p><strong>Total Orders:</strong> ${data.order_count}</p>
                                <p><strong>Total Spent:</strong> ${data.total_spent}</p>
                            </div>
                        `;
                        document.getElementById('customerDetails').innerHTML = html;
                    } else {
                        document.getElementById('customerDetails').innerHTML = '<p class="text-danger">Failed to load customer details.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('customerDetails').innerHTML = '<p class="text-danger">Error loading customer details.</p>';
                });
        }
    </script>
</body>
</html>
