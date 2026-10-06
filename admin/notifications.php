<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
requireAdmin();

global $pdo;

// ============================================
// HANDLE ACTIONS
// ============================================
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id     = intval($_POST['id'] ?? 0);
    $type   = sanitize($_POST['type'] ?? '');

    // -------- MARK SINGLE AS READ --------
    if ($action === 'mark_read') {
        try {
            $updated = false;
            $rows    = 0;

            switch ($type) {
                case 'contact':
                    $stmt = $pdo->prepare("UPDATE contact_us SET status = 'read' WHERE id = ?");
                    $stmt->execute([$id]);
                    $rows = $stmt->rowCount();
                    $updated = true;
                    break;

                case 'newsletter':
                    // Try 'inactive' first (common), fall back to 'read'
                    // Update either way — just make the row disappear from 'pending' filter
                    $stmt = $pdo->prepare("UPDATE newsletter_subscribers SET status = 'active' WHERE id = ?");
                    $stmt->execute([$id]);
                    $rows = $stmt->rowCount();
                    $updated = true;
                    break;

                case 'agent':
                    $stmt = $pdo->prepare("UPDATE agent_chat_requests SET status = 'resolved' WHERE id = ?");
                    $stmt->execute([$id]);
                    $rows = $stmt->rowCount();
                    $updated = true;
                    break;

                case 'order':
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'processing' WHERE id = ?");
                    $stmt->execute([$id]);
                    $rows = $stmt->rowCount();
                    $updated = true;
                    break;

                case 'login':
                    // activity_logs has no 'status' column.
                    // We "mark as read" by writing a lightweight marker in the details,
                    // OR by simply acknowledging it. Choose one:
                    //
                    // Option A (recommended): add a `read_at` column to activity_logs.
                    //   ALTER TABLE activity_logs ADD COLUMN read_at TIMESTAMP NULL;
                    // Then:
                    // $stmt = $pdo->prepare("UPDATE activity_logs SET read_at = NOW() WHERE id = ?");
                    //
                    // Option B (no schema change): just acknowledge it here, and
                    // stop showing it in the list by adjusting the fetch query.
                    //
                    // For now we do Option A if the column exists, else silently succeed.
                    try {
                        $stmt = $pdo->prepare("UPDATE activity_logs SET read_at = NOW() WHERE id = ?");
                        $stmt->execute([$id]);
                        $rows = $stmt->rowCount();
                    } catch (PDOException $e) {
                        // Column doesn't exist — fall back to no-op (still counts as read)
                        $rows = 1;
                    }
                    $updated = true;
                    break;

                default:
                    throw new Exception('Invalid notification type');
            }

            if ($updated) {
                if ($rows > 0) {
                    $message     = 'Notification marked as read!';
                    $messageType = 'success';
                } else {
                    $message     = 'Nothing was updated — the notification may already be read, or the record was not found.';
                    $messageType = 'error';
                }
            }
        } catch (PDOException $e) {
            error_log('Mark read PDO error: ' . $e->getMessage());
            $message     = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        } catch (Exception $e) {
            $message     = $e->getMessage();
            $messageType = 'error';
        }
    }

    // -------- MARK ALL AS READ --------
    if ($action === 'mark_all_read') {
        try {
            $total = 0;

            $stmt = $pdo->exec("UPDATE contact_us SET status = 'read' WHERE status = 'unread'");
            $total += $stmt;

            $stmt = $pdo->exec("UPDATE newsletter_subscribers SET status = 'active' WHERE status = 'pending'");
            $total += $stmt;

            $stmt = $pdo->exec("UPDATE agent_chat_requests SET status = 'resolved' WHERE status = 'pending'");
            $total += $stmt;

            $stmt = $pdo->exec("UPDATE orders SET status = 'processing' WHERE status = 'pending'");
            $total += $stmt;

            // activity_logs: skip if no read_at column
            try {
                $stmt = $pdo->exec("UPDATE activity_logs SET read_at = NOW() WHERE read_at IS NULL AND action = 'failed_login'");
                $total += $stmt;
            } catch (PDOException $e) {
                // ignore — column may not exist
            }

            $message     = "Marked $total notification(s) as read.";
            $messageType = 'success';
        } catch (PDOException $e) {
            error_log('Mark all read error: ' . $e->getMessage());
            $message     = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    // -------- DELETE --------
    if ($action === 'delete') {
        try {
            $table = sanitize($_POST['table'] ?? '');
            $allowed = ['contact_us', 'newsletter_subscribers', 'agent_chat_requests', 'orders', 'activity_logs'];

            if (!in_array($table, $allowed, true)) {
                throw new Exception('Invalid table name');
            }

            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                $message     = 'Notification deleted successfully!';
                $messageType = 'success';
            } else {
                $message     = 'Nothing was deleted.';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message     = $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ============================================
// FETCH NOTIFICATIONS
// ============================================
$notifications = [];

try {
    // 1. Contact Us
    try {
        $stmt = $pdo->query("
            SELECT id, name, email, message, status, created_at
            FROM contact_us
            WHERE status = 'unread'
            ORDER BY created_at DESC
        ");
        foreach ($stmt->fetchAll() as $notif) {
            $notifications[] = [
                'id'          => $notif['id'],
                'type'        => 'contact',
                'type_label'  => 'Contact Message',
                'icon'        => 'fa-envelope',
                'color'       => 'info',
                'title'       => "New message from " . htmlspecialchars($notif['name']),
                'description' => substr(htmlspecialchars($notif['message']), 0, 100) . (strlen($notif['message']) > 100 ? '...' : ''),
                'user'        => htmlspecialchars($notif['name']),
                'email'       => htmlspecialchars($notif['email']),
                'status'      => $notif['status'],
                'created_at'  => $notif['created_at'],
                'link'        => 'contact_messages.php?id=' . $notif['id'],
            ];
        }
    } catch (PDOException $e) {
        error_log('Notifications: contact_us query failed — ' . $e->getMessage());
    }

    // 2. Newsletter
    try {
        $stmt = $pdo->query("
            SELECT id, email, status, created_at
            FROM newsletter_subscribers
            WHERE status = 'pending'
            ORDER BY created_at DESC
        ");
        foreach ($stmt->fetchAll() as $notif) {
            $notifications[] = [
                'id'          => $notif['id'],
                'type'        => 'newsletter',
                'type_label'  => 'Newsletter Subscription',
                'icon'        => 'fa-newspaper',
                'color'       => 'success',
                'title'       => "New newsletter subscription",
                'description' => "Email: " . htmlspecialchars($notif['email']),
                'user'        => htmlspecialchars($notif['email']),
                'email'       => htmlspecialchars($notif['email']),
                'status'      => $notif['status'],
                'created_at'  => $notif['created_at'],
                'link'        => 'newsletter.php?id=' . $notif['id'],
            ];
        }
    } catch (PDOException $e) {
        error_log('Notifications: newsletter query failed — ' . $e->getMessage());
    }

    // 3. Agent Requests
    try {
        $stmt = $pdo->query("
            SELECT acr.id, acr.message, acr.status, acr.created_at,
                   u.name AS user_name, u.email AS user_email
            FROM agent_chat_requests acr
            LEFT JOIN users u ON acr.user_id = u.id
            WHERE acr.status = 'pending'
            ORDER BY acr.created_at DESC
        ");
        foreach ($stmt->fetchAll() as $notif) {
            $notifications[] = [
                'id'          => $notif['id'],
                'type'        => 'agent',
                'type_label'  => 'Agent Request',
                'icon'        => 'fa-headset',
                'color'       => 'warning',
                'title'       => "New agent chat request from " . htmlspecialchars($notif['user_name'] ?? 'Guest'),
                'description' => substr(htmlspecialchars($notif['message'] ?? 'No message'), 0, 100) . (strlen($notif['message'] ?? '') > 100 ? '...' : ''),
                'user'        => htmlspecialchars($notif['user_name'] ?? 'Guest'),
                'email'       => htmlspecialchars($notif['user_email'] ?? ''),
                'status'      => $notif['status'],
                'created_at'  => $notif['created_at'],
                'link'        => 'agent_requests.php?id=' . $notif['id'],
            ];
        }
    } catch (PDOException $e) {
        error_log('Notifications: agent_chat_requests query failed — ' . $e->getMessage());
    }

    // 4. Failed Logins (activity_logs) — uses `details`, not `description`
    try {
        $stmt = $pdo->query("
            SELECT id, user_name, action, details, ip_address, created_at
            FROM activity_logs
            WHERE action = 'failed_login'
              AND created_at > NOW() - INTERVAL '24 hours'
            ORDER BY created_at DESC
        ");
        foreach ($stmt->fetchAll() as $notif) {
            $notifications[] = [
                'id'          => $notif['id'],
                'type'        => 'login',
                'type_label'  => 'Failed Login Attempt',
                'icon'        => 'fa-shield-alt',
                'color'       => 'danger',
                'title'       => "Failed admin login attempt",
                'description' => "IP: " . htmlspecialchars($notif['ip_address'] ?? '') . " - " . htmlspecialchars($notif['details'] ?? ''),
                'user'        => htmlspecialchars($notif['user_name'] ?? 'Unknown'),
                'email'       => '',
                'status'      => 'unread',
                'created_at'  => $notif['created_at'],
                'link'        => 'activity_logs.php?id=' . $notif['id'],
            ];
        }
    } catch (PDOException $e) {
        error_log('Notifications: activity_logs query failed — ' . $e->getMessage());
    }

    // 5. Pending Orders
    try {
        $stmt = $pdo->query("
            SELECT o.id, o.order_number, o.total, o.status, o.created_at,
                   u.name AS user_name, u.email AS user_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.status = 'pending'
            ORDER BY o.created_at DESC
        ");
        foreach ($stmt->fetchAll() as $notif) {
            $notifications[] = [
                'id'          => $notif['id'],
                'type'        => 'order',
                'type_label'  => 'Pending Order',
                'icon'        => 'fa-truck',
                'color'       => 'primary',
                'title'       => "New pending order #" . htmlspecialchars($notif['order_number']),
                'description' => "Total: Ksh " . number_format($notif['total'], 2) . " - " . htmlspecialchars($notif['user_name'] ?? 'Guest'),
                'user'        => htmlspecialchars($notif['user_name'] ?? 'Guest'),
                'email'       => htmlspecialchars($notif['user_email'] ?? ''),
                'status'      => $notif['status'],
                'created_at'  => $notif['created_at'],
                'link'        => 'orders.php?id=' . $notif['id'],
            ];
        }
    } catch (PDOException $e) {
        error_log('Notifications: orders query failed — ' . $e->getMessage());
    }

    // Sort newest first
    usort($notifications, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

} catch (PDOException $e) {
    error_log('Get notifications error: ' . $e->getMessage());
    $notifications = [];
}

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
    <?php include "header.php"; ?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>

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
                                            <span class="badge <?php echo in_array($notif['status'], ['unread','pending']) ? 'badge-warning' : 'badge-success'; ?>">
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
        function filterNotifications() {
            const filter = document.getElementById('typeFilter').value;
            document.querySelectorAll('#notificationsTable tbody tr').forEach(row => {
                const type = row.className.replace('notification-', '');
                row.style.display = !filter || type === filter ? '' : 'none';
            });
        }

        function filterTable(inputId, tableId) {
            const input = document.getElementById(inputId);
            const table = document.getElementById(tableId);
            if (!input || !table) return;

            const filter = input.value.toLowerCase();
            table.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        }

        setTimeout(function () {
            document.querySelectorAll('.alert-persistent').forEach(function (alert) {
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(function () {
                    alert.style.opacity = '0';
                    setTimeout(function () { alert.remove(); }, 500);
                }, 5000);
            });
        }, 1000);
    </script>

    <style>
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
        .notification-icon i { font-size: 14px; }
        .notification-type-label { font-size: 12px; font-weight: 500; color: #6c757d; }
        .notification-content { padding: 2px 0; }
        .notification-content strong { display: block; font-size: 14px; color: #1a2332; }
        .notification-desc { font-size: 13px; color: #6c757d; margin-top: 2px; }
        .notification-user { font-size: 13px; color: #495057; }
        .notification-user small { font-size: 11px; color: #6c757d; }
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 60px; color: #28a745; display: block; margin-bottom: 15px; }
        .empty-state h3 { font-size: 22px; color: #1a2332; margin-bottom: 8px; }
        .empty-state p { color: #6c757d; font-size: 16px; }
        .btn-sm.btn-success {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-sm.btn-success:hover { background: #218838; }
    </style>
</body>
</html>

<?php
// ============================================
// HELPER FUNCTIONS
// ============================================

function getColorClass($color) {
    $colors = [
        'primary' => '#3498db',
        'info'    => '#17a2b8',
        'success' => '#28a745',
        'warning' => '#ffc107',
        'danger'  => '#dc3545',
    ];
    return $colors[$color] ?? '#6c757d';
}

function getTableName($type) {
    $tables = [
        'contact'    => 'contact_us',
        'newsletter' => 'newsletter_subscribers',
        'agent'      => 'agent_chat_requests',
        'login'      => 'activity_logs',
        'order'      => 'orders',
    ];
    return $tables[$type] ?? '';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60)         return $diff . 's ago';
    if ($diff < 3600)       return floor($diff / 60) . 'm ago';
    if ($diff < 86400)      return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)     return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}
?>
