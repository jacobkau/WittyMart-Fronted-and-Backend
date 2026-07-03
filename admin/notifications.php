<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/auth.php';

requireAdmin();

global $pdo;

// ===== HANDLE ACTIONS =====
$message = '';
$messageType = '';

// Mark notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = intval($_POST['id'] ?? 0);
    $type = sanitize($_POST['type'] ?? '');
    
    if ($action === 'mark_read') {
        try {
            switch ($type) {
                case 'contact':
                    $stmt = $pdo->prepare("UPDATE contact_us SET status = 'read' WHERE id = ?");
                    break;
                case 'newsletter':
                    $stmt = $pdo->prepare("UPDATE newsletter_subscribers SET status = 'active' WHERE id = ?");
                    break;
                case 'agent':
                    $stmt = $pdo->prepare("UPDATE agent_chat_requests SET status = 'resolved' WHERE id = ?");
                    break;
                case 'order':
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'processing' WHERE id = ?");
                    break;
                case 'login':
                    $stmt = $pdo->prepare("UPDATE activity_logs SET status = 'read' WHERE id = ?");
                    break;
                default:
                    throw new Exception('Invalid notification type');
            }
            
            if ($stmt->execute([$id])) {
                $message = 'Notification marked as read!';
                $messageType = 'success';
            } else {
                $message = 'Failed to mark notification as read.';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            error_log('Mark read error: ' . $e->getMessage());
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // Mark all as read
    if ($action === 'mark_all_read') {
        try {
            // Mark all contact messages as read
            $pdo->exec("UPDATE contact_us SET status = 'read' WHERE status = 'unread'");
            
            // Mark all newsletter subscribers as active
            $pdo->exec("UPDATE newsletter_subscribers SET status = 'active' WHERE status = 'pending'");
            
            // Mark all agent requests as resolved
            $pdo->exec("UPDATE agent_chat_requests SET status = 'resolved' WHERE status = 'pending'");
            
            $message = 'All notifications marked as read!';
            $messageType = 'success';
        } catch (PDOException $e) {
            error_log('Mark all read error: ' . $e->getMessage());
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // Delete notification
    if ($action === 'delete') {
        try {
            $table = sanitize($_POST['table'] ?? '');
            if (!$table) {
                throw new Exception('Invalid table name');
            }
            
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = 'Notification deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete notification.';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ===== GET ALL NOTIFICATIONS =====
$notifications = [];

try {
    // 1. Contact Us Messages
    $stmt = $pdo->query("
        SELECT id, name, email, message, 'contact' as type, 
               'unread' as status, created_at, status as current_status
        FROM contact_us 
        WHERE status = 'unread'
        ORDER BY created_at DESC
    ");
    $contactNotifications = $stmt->fetchAll();
    foreach ($contactNotifications as $notif) {
        $notifications[] = [
            'id' => $notif['id'],
            'type' => 'contact',
            'type_label' => 'Contact Message',
            'icon' => 'fa-envelope',
            'color' => 'info',
            'title' => "New message from " . htmlspecialchars($notif['name']),
            'description' => substr(htmlspecialchars($notif['message']), 0, 100) . (strlen($notif['message']) > 100 ? '...' : ''),
            'user' => htmlspecialchars($notif['name']),
            'email' => htmlspecialchars($notif['email']),
            'status' => $notif['current_status'],
            'created_at' => $notif['created_at'],
            'link' => 'contact_messages.php?id=' . $notif['id']
        ];
    }
    
    // 2. Newsletter Subscriptions
    $stmt = $pdo->query("
        SELECT id, email, 'newsletter' as type, 
               'pending' as status, created_at, status as current_status
        FROM newsletter_subscribers 
        WHERE status = 'pending'
        ORDER BY created_at DESC
    ");
    $newsletterNotifications = $stmt->fetchAll();
    foreach ($newsletterNotifications as $notif) {
        $notifications[] = [
            'id' => $notif['id'],
            'type' => 'newsletter',
            'type_label' => 'Newsletter Subscription',
            'icon' => 'fa-newspaper',
            'color' => 'success',
            'title' => "New newsletter subscription",
            'description' => "Email: " . htmlspecialchars($notif['email']),
            'user' => htmlspecialchars($notif['email']),
            'email' => htmlspecialchars($notif['email']),
            'status' => $notif['current_status'],
            'created_at' => $notif['created_at'],
            'link' => 'newsletter.php?id=' . $notif['id']
        ];
    }
    
    // 3. Agent Chat Requests
    $stmt = $pdo->query("
        SELECT acr.id, acr.message, acr.status as current_status, 
               acr.created_at, u.name as user_name, u.email as user_email,
               'agent' as type
        FROM agent_chat_requests acr
        LEFT JOIN users u ON acr.user_id = u.id
        WHERE acr.status = 'pending'
        ORDER BY acr.created_at DESC
    ");
    $agentNotifications = $stmt->fetchAll();
    foreach ($agentNotifications as $notif) {
        $notifications[] = [
            'id' => $notif['id'],
            'type' => 'agent',
            'type_label' => 'Agent Request',
            'icon' => 'fa-headset',
            'color' => 'warning',
            'title' => "New agent chat request from " . htmlspecialchars($notif['user_name'] ?? 'Guest'),
            'description' => substr(htmlspecialchars($notif['message'] ?? 'No message'), 0, 100) . (strlen($notif['message'] ?? '') > 100 ? '...' : ''),
            'user' => htmlspecialchars($notif['user_name'] ?? 'Guest'),
            'email' => htmlspecialchars($notif['user_email'] ?? ''),
            'status' => $notif['current_status'],
            'created_at' => $notif['created_at'],
            'link' => 'agent_requests.php?id=' . $notif['id']
        ];
    }
    
    // 4. Failed Login Attempts
    $stmt = $pdo->query("
        SELECT id, user_name, action, description, ip_address, 
               created_at, 'login' as type
        FROM activity_logs 
        WHERE action = 'failed_login' 
        AND created_at > NOW() - INTERVAL '24 hours'
        ORDER BY created_at DESC
    ");
    $loginNotifications = $stmt->fetchAll();
    foreach ($loginNotifications as $notif) {
        $notifications[] = [
            'id' => $notif['id'],
            'type' => 'login',
            'type_label' => 'Failed Login Attempt',
            'icon' => 'fa-shield-alt',
            'color' => 'danger',
            'title' => "Failed admin login attempt",
            'description' => "IP: " . htmlspecialchars($notif['ip_address']) . " - " . htmlspecialchars($notif['description'] ?? ''),
            'user' => htmlspecialchars($notif['user_name'] ?? 'Unknown'),
            'email' => '',
            'status' => 'unread',
            'created_at' => $notif['created_at'],
            'link' => 'activity_logs.php?id=' . $notif['id']
        ];
    }
    
    // 5. Pending Orders
    $stmt = $pdo->query("
        SELECT o.id, o.order_number, o.total, o.status, o.created_at,
               u.name as user_name, u.email as user_email,
               'order' as type
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.status = 'pending'
        ORDER BY o.created_at DESC
    ");
    $orderNotifications = $stmt->fetchAll();
    foreach ($orderNotifications as $notif) {
        $notifications[] = [
            'id' => $notif['id'],
            'type' => 'order',
            'type_label' => 'Pending Order',
            'icon' => 'fa-truck',
            'color' => 'primary',
            'title' => "New pending order #" . htmlspecialchars($notif['order_number']),
            'description' => "Total: Ksh " . number_format($notif['total'], 2) . " - " . htmlspecialchars($notif['user_name'] ?? 'Guest'),
            'user' => htmlspecialchars($notif['user_name'] ?? 'Guest'),
            'email' => htmlspecialchars($notif['user_email'] ?? ''),
            'status' => $notif['status'],
            'created_at' => $notif['created_at'],
            'link' => 'orders.php?id=' . $notif['id']
        ];
    }
    
    // Sort notifications by date (newest first)
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
} catch (PDOException $e) {
    error_log('Get notifications error: ' . $e->getMessage());
    $notifications = [];
}

// Get total count
$totalNotifications = count($notifications);

$page_title = 'Notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - WittyMart Admin</title>
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px;">
                <span class="badge badge-info" style="margin-bottom:10px;">
                    <?php echo $totalNotifications; ?> unread
                </span>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Notifications List -->
            <div class="admin-card">
                <div class="card-body">
                    <div class="table-toolbar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchNotifications" placeholder="Search notifications..." onkeyup="filterTable('searchNotifications', 'notificationsTable')">
                        </div>
                        <div class="filter-box">
                            <select id="typeFilter" onchange="filterNotifications()">
                                <option value="">All Types</option>
                                <option value="contact">Contact Messages</option>
                                <option value="newsletter">Newsletter Subscriptions</option>
                                <option value="agent">Agent Requests</option>
                                <option value="login">Failed Logins</option>
                                <option value="order">Pending Orders</option>
                            </select>
                        </div>
                        <?php if ($totalNotifications > 0): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-primary" onclick="return confirm('Mark all notifications as read?')">
                                    <i class="fas fa-check-double"></i> Mark All as Read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($totalNotifications > 0): ?>
                        <table class="admin-table" id="notificationsTable">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Notification</th>
                                    <th>User</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notif): ?>
                                    <tr class="notification-<?php echo $notif['type']; ?>">
                                        <td>
                                            <span class="notification-icon" style="background: <?php echo getColorClass($notif['color']); ?>;">
                                                <i class="fas <?php echo $notif['icon']; ?>"></i>
                                            </span>
                                            <span class="notification-type-label"><?php echo $notif['type_label']; ?></span>
                                        </td>
                                        <td>
                                            <div class="notification-content">
                                                <strong><?php echo $notif['title']; ?></strong>
                                                <div class="notification-desc"><?php echo $notif['description']; ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="notification-user">
                                                <?php if ($notif['user']): ?>
                                                    <i class="fas fa-user"></i> <?php echo $notif['user']; ?>
                                                <?php endif; ?>
                                                <?php if ($notif['email']): ?>
                                                    <br><small><i class="fas fa-envelope"></i> <?php echo $notif['email']; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span title="<?php echo date('Y-m-d H:i:s', strtotime($notif['created_at'])); ?>">
                                                <?php echo timeAgo($notif['created_at']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $notif['status'] === 'unread' || $notif['status'] === 'pending' ? 'badge-warning' : 'badge-success'; ?>">
                                                <?php echo ucfirst($notif['status'] ?? 'unread'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo $notif['link']; ?>" class="btn-sm btn-edit">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="id" value="<?php echo $notif['id']; ?>">
                                                <input type="hidden" name="type" value="<?php echo $notif['type']; ?>">
                                                <button type="submit" class="btn-sm btn-success" title="Mark as read">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $notif['id']; ?>">
                                                <input type="hidden" name="table" value="<?php echo getTableName($notif['type']); ?>">
                                                <button type="submit" class="btn-sm btn-delete" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h3>All caught up!</h3>
                            <p>No new notifications to display.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // ===== FILTER NOTIFICATIONS =====
        function filterNotifications() {
            const filter = document.getElementById('typeFilter').value;
            const rows = document.querySelectorAll('#notificationsTable tbody tr');
            rows.forEach(row => {
                if (row.cells.length > 0) {
                    const type = row.className.replace('notification-', '');
                    row.style.display = !filter || type === filter ? '' : 'none';
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

        // ===== TIME AGO =====
        function timeAgo(date) {
            const diff = Math.floor((new Date() - new Date(date)) / 1000);
            if (diff < 60) return diff + 's ago';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
            return new Date(date).toLocaleDateString();
        }
    </script>

    <style>
        /* ===== NOTIFICATION SPECIFIC STYLES ===== */
        .notification-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            color: white;
            margin-right: 8px;
        }

        .notification-icon i {
            font-size: 14px;
        }

        .notification-type-label {
            font-size: 12px;
            font-weight: 500;
            color: #6c757d;
        }

        .notification-content {
            padding: 2px 0;
        }

        .notification-content strong {
            display: block;
            font-size: 14px;
            color: #1a2332;
        }

        .notification-desc {
            font-size: 13px;
            color: #6c757d;
            margin-top: 2px;
        }

        .notification-user {
            font-size: 13px;
            color: #495057;
        }

        .notification-user small {
            font-size: 11px;
            color: #6c757d;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 60px;
            color: #28a745;
            display: block;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 22px;
            color: #1a2332;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #6c757d;
            font-size: 16px;
        }

        .btn-sm.btn-success {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-sm.btn-success:hover {
            background: #218838;
        }
    </style>
</body>
</html>

<?php
// ===== HELPER FUNCTIONS =====

function getColorClass($color) {
    $colors = [
        'primary' => '#3498db',
        'info' => '#17a2b8',
        'success' => '#28a745',
        'warning' => '#ffc107',
        'danger' => '#dc3545'
    ];
    return $colors[$color] ?? '#6c757d';
}

function getTableName($type) {
    $tables = [
        'contact' => 'contact_us',
        'newsletter' => 'newsletter_subscribers',
        'agent' => 'agent_chat_requests',
        'login' => 'activity_logs',
        'order' => 'orders'
    ];
    return $tables[$type] ?? '';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . 's ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>
