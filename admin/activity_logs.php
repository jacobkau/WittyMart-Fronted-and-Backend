<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';

requireAdmin();

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;

$logsData = getActivityLogs($page, $perPage);
$logs = $logsData['logs'];
$totalPages = $logsData['totalPages'];

// Clear logs if requested
if (isset($_GET['clear']) && $_GET['clear'] === 'true') {
    if (isset($_GET['days'])) {
        $days = intval($_GET['days']);
        if (clearActivityLogs($days)) {
            $message = "Activity logs older than $days days cleared successfully.";
            $messageType = 'success';
        }
    }
}

$page_title = 'Activity Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
       <?php include "sidebar.php"?>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px">
                    <button class="btn-sm btn-danger" onclick="clearLogs()">
                        <i class="fas fa-trash"></i> Clear Old Logs
                    </button>
            </header>

            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-card">
                <div class="card-body">
                    <div class="table-toolbar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchLogs" placeholder="Search logs..." onkeyup="filterTable('searchLogs', 'logsTable')">
                        </div>
                        <span class="badge badge-info">Total: <?php echo $logsData['total']; ?></span>
                    </div>

                    <?php if (count($logs) > 0): ?>
                        <table class="admin-table" id="logsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($log['id']); ?></td>
                                        <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo getActivityBadge($log['action']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($log['action'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted text-center" style="padding: 40px 0;">
                            <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                            No activity logs found
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
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
        
        function clearLogs() {
            const days = prompt('Delete logs older than how many days? (Default: 30)', '30');
            if (days !== null && days > 0) {
                window.location.href = '?clear=true&days=' + days;
            }
        }
    </script>

    <style>
        .badge-login { background: #17a2b8; color: #fff; }
        .badge-logout { background: #dc3545; color: #fff; }
        .badge-create { background: #28a745; color: #fff; }
        .badge-update { background: #ffc107; color: #333; }
        .badge-delete { background: #dc3545; color: #fff; }
        .badge-view { background: #007bff; color: #fff; }
        .badge-system { background: #6c757d; color: #fff; }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .page-link {
            display: inline-block;
            padding: 6px 12px;
            background: var(--bg);
            color: var(--text);
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background: var(--primary);
            color: #fff;
        }
        
        .page-link.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        
        .btn-sm.btn-danger {
            background: #dc3545;
            color: #fff;
        }
        
        .btn-sm.btn-danger:hover {
            background: #c82333;
        }
    </style>
</body>
</html>
