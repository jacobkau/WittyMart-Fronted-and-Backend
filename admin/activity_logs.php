<?php

require_once 'includes/config.php';
requireAdmin();

$page    = isset($_GET['page'])    ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// ============================================
// FILTER STATE
// ============================================
$filterAction = trim($_GET['filter_action'] ?? '');
$filterUser   = trim($_GET['filter_user']   ?? '');
$filterFrom   = trim($_GET['filter_from']   ?? '');
$filterTo     = trim($_GET['filter_to']     ?? '');

/**
 * Build the WHERE clause + params from the current filters.
 * Returns [sqlWhereString, [params...]]
 */
function buildActivityFilter($filterAction, $filterUser, $filterFrom, $filterTo) {
    $where  = [];
    $params = [];

    if ($filterAction !== '') {
        $where[]  = "action = ?";
        $params[] = $filterAction;
    }
    if ($filterUser !== '') {
        $where[]  = "user_name ILIKE ?";
        $params[] = '%' . $filterUser . '%';
    }
    if ($filterFrom !== '') {
        $where[]  = "created_at >= ?";
        $params[] = $filterFrom . ' 00:00:00';
    }
    if ($filterTo !== '') {
        $where[]  = "created_at <= ?";
        $params[] = $filterTo . ' 23:59:59';
    }

    $sql = '';
    if (!empty($where)) {
        $sql = ' WHERE ' . implode(' AND ', $where);
    }
    return [$sql, $params];
}

// ============================================
// CSV EXPORT 
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        list($whereSql, $params) = buildActivityFilter(
            $filterAction, $filterUser, $filterFrom, $filterTo
        );

        $sql = "SELECT id, user_id, user_name, action,
                       details AS description, ip_address, user_agent, created_at
                FROM activity_logs"
             . $whereSql
             . " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filename reflects the filters
        $parts = ['activity_logs'];
        if ($filterAction) $parts[] = preg_replace('/[^a-z0-9]/i', '', $filterAction);
        if ($filterFrom)   $parts[] = 'from_' . str_replace('-', '', $filterFrom);
        if ($filterTo)     $parts[] = 'to_'   . str_replace('-', '', $filterTo);
        $filename = implode('_', $parts) . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); 

        fputcsv($out, [
            'ID', 'User ID', 'User', 'Action', 'Description',
            'IP Address', 'User Agent', 'Date'
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['user_id'],
                $r['user_name'] ?? 'System',
                $r['action'] ?? '',
                $r['description'] ?? '',
                $r['ip_address'] ?? '',
                $r['user_agent'] ?? '',
                $r['created_at'],
            ]);
        }
        fclose($out);
        exit;
    } catch (PDOException $e) {
        error_log('Activity logs CSV export error: ' . $e->getMessage());
        // fall through to page load
    }
}

// ============================================
// PAGINATED FETCH 
// ============================================
list($whereSql, $params) = buildActivityFilter(
    $filterAction, $filterUser, $filterFrom, $filterTo
);

$offset = ($page - 1) * $perPage;

try {
    // Count
    $countSql = "SELECT COUNT(*) FROM activity_logs" . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));

    // Fetch page
    $listSql = "SELECT id, user_id, user_name, action,
                       details AS description, ip_address, created_at
                FROM activity_logs"
             . $whereSql
             . " ORDER BY created_at DESC LIMIT ? OFFSET ?";

    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute(array_merge($params, [$perPage, $offset]));
    $logs = $listStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Activity logs fetch error: ' . $e->getMessage());
    $logs = [];
    $total = 0;
    $totalPages = 1;
}

// ============================================
// CLEAR OLD LOGS
// ============================================
$message = '';
$messageType = '';
if (isset($_GET['clear']) && $_GET['clear'] === 'true') {
    $days = intval($_GET['days'] ?? 30);
    if ($days > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < NOW() - (? || ' days')::interval");
            $stmt->execute([$days]);
            $deleted = $stmt->rowCount();
            $message = "Cleared {$deleted} log(s) older than {$days} days.";
            $messageType = 'success';
        } catch (PDOException $e) {
            error_log('Clear logs error: ' . $e->getMessage());
            $message = 'Failed to clear logs: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ============================================
// DISTINCT ACTIONS (for the filter dropdown)
// ============================================
$actionsList = [];
try {
    $actionsList = $pdo->query("
        SELECT DISTINCT action FROM activity_logs
        WHERE action IS NOT NULL AND action <> ''
        ORDER BY action
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('Fetch distinct actions error: ' . $e->getMessage());
}

/** Preserve current filters when building a URL */
function buildQueryString($overrides = []) {
    $params = array_merge([
        'filter_action' => $_GET['filter_action'] ?? '',
        'filter_user'   => $_GET['filter_user']   ?? '',
        'filter_from'   => $_GET['filter_from']   ?? '',
        'filter_to'     => $_GET['filter_to']     ?? '',
        'page'          => $_GET['page']          ?? 1,
    ], $overrides);
    // drop empty
    $params = array_filter($params, function ($v) { return $v !== '' && $v !== null; });
    return '?' . http_build_query($params);
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
    <?php include "header.php"; ?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>

        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <span class="badge badge-info" style="padding:8px 16px; background:#e8f5f0; color:#05573c; border-radius:20px; font-weight:600;">
                    <i class="fas fa-history"></i>
                    Total: <?php echo $total; ?> logs
                    <?php if ($filterAction || $filterUser || $filterFrom || $filterTo): ?>
                        <span style="color:#888;">(filtered)</span>
                    <?php endif; ?>
                </span>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn-sm btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="<?php echo htmlspecialchars(buildQueryString(['export' => 'csv', 'page' => null])); ?>"
                       class="btn-sm btn-success">
                        <i class="fas fa-file-csv"></i> Download CSV
                        <?php if ($filterAction || $filterUser || $filterFrom || $filterTo): ?>
                            (filtered)
                        <?php endif; ?>
                    </a>
                    <button type="button" class="btn-sm btn-danger" onclick="clearLogs()">
                        <i class="fas fa-trash"></i> Clear Old Logs
                    </button>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-card">
                <div class="card-body">

                    <!-- ============================================
                         FILTER TOOLBAR
                         ============================================ -->
                    <form method="GET" class="logs-filter" style="padding:14px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; border-bottom:1px solid #eee;">
                        <div class="search-box" style="flex:1; min-width:220px;">
                            <i class="fas fa-search"></i>
                            <input type="text" name="filter_user"
                                   value="<?php echo htmlspecialchars($filterUser); ?>"
                                   placeholder="Filter by user name...">
                        </div>

                        <select name="filter_action" class="filter-select">
                            <option value="">All Actions</option>
                            <?php foreach ($actionsList as $a): ?>
                                <option value="<?php echo htmlspecialchars($a); ?>"
                                    <?php echo $filterAction === $a ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($a); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input type="date" name="filter_from" class="filter-select"
                               value="<?php echo htmlspecialchars($filterFrom); ?>"
                               title="From date">

                        <input type="date" name="filter_to" class="filter-select"
                               value="<?php echo htmlspecialchars($filterTo); ?>"
                               title="To date">

                        <button type="submit" class="btn-sm btn-primary">
                            <i class="fas fa-filter"></i> Apply
                        </button>

                        <?php if ($filterAction || $filterUser || $filterFrom || $filterTo): ?>
                            <a href="activity_logs.php" class="btn-sm" style="background:#f0f0f0; color:#333; text-decoration:none; padding:6px 14px; border-radius:4px; font-size:12px; font-weight:600;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>

                    <!-- Local text filter on rendered rows -->
                    <div class="table-toolbar" style="padding:14px;">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchLogs"
                                   placeholder="Quick search on this page..."
                                   onkeyup="filterTable('searchLogs', 'logsTable')">
                        </div>
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

                        <!-- Pagination (keeps filters) -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination no-print">
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo htmlspecialchars(buildQueryString(['page' => $page - 1])); ?>" class="page-link">
                                        <i class="fas fa-chevron-left"></i> Prev
                                    </a>
                                <?php endif; ?>

                                <?php
                                    $start = max(1, $page - 2);
                                    $end   = min($totalPages, $page + 2);
                                    if ($start > 1) echo '<span class="page-link" style="pointer-events:none;">…</span>';
                                    for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <a href="<?php echo htmlspecialchars(buildQueryString(['page' => $i])); ?>"
                                       class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php
                                    endfor;
                                    if ($end < $totalPages) echo '<span class="page-link" style="pointer-events:none;">…</span>';
                                ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?php echo htmlspecialchars(buildQueryString(['page' => $page + 1])); ?>" class="page-link">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
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
            table.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
            });
        }

        function clearLogs() {
            const days = prompt('Delete logs older than how many days? (Default: 30)', '30');
            if (days !== null && parseInt(days, 10) > 0) {
                window.location.href = '?clear=true&days=' + parseInt(days, 10);
            }
        }
    </script>

    <style>
        .badge-login   { background: #17a2b8; color: #fff; }
        .badge-logout  { background: #dc3545; color: #fff; }
        .badge-create  { background: #28a745; color: #fff; }
        .badge-update  { background: #ffc107; color: #333; }
        .badge-delete  { background: #dc3545; color: #fff; }
        .badge-view    { background: #007bff; color: #fff; }
        .badge-system  { background: #6c757d; color: #fff; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 15px;
            flex-wrap: wrap;
            align-items: center;
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
            font-size: 13px;
        }
        .page-link:hover { background: var(--primary); color: #fff; }
        .page-link.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        .filter-select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: #fff;
            font-size: 13px;
            font-family: inherit;
            cursor: pointer;
        }
        .filter-select:focus {
            outline: none;
            border-color: #05573c;
            box-shadow: 0 0 0 3px rgba(5,87,60,0.1);
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .search-box input {
            border: none;
            background: transparent;
            padding: 6px 0;
            outline: none;
            width: 100%;
            font-size: 13px;
        }

        .btn-sm.btn-primary {
            background: #05573c; color: #fff;
            border: none; padding: 8px 16px;
            border-radius: 4px; cursor: pointer;
            font-size: 12px; font-weight: 600;
            font-family: inherit;
        }
        .btn-sm.btn-primary:hover { background: #03402c; }

        .btn-sm.btn-danger { background: #dc3545; color: #fff; border: none; padding: 8px 14px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; font-family: inherit; }
        .btn-sm.btn-danger:hover { background: #c82333; }

        .btn-sm.btn-success {
            background: #28a745; color: #fff;
            text-decoration: none;
            padding: 8px 14px; border-radius: 4px;
            font-size: 12px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-sm.btn-success:hover { background: #218838; }

        /* ============================================
           PRINT STYLES
           ============================================ */
        @media print {
            @page { margin: 12mm; size: A4; }

            header, .admin-header, .sidebar, .admin-sidebar,
            .logs-filter, .table-toolbar, .pagination,
            .alert, .btn-sm, button, form, nav {
                display: none !important;
            }

            body {
                background: #fff !important;
                color: #000 !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 11pt;
            }

            .admin-wrapper { display: block !important; }
            .admin-main { padding: 0 !important; margin: 0 !important; width: 100% !important; }
            .admin-card, .card-body { box-shadow: none !important; border: none !important; border-radius: 0 !important; padding: 0 !important; }

            .admin-main::before {
                content: "WittyMart — Activity Logs";
                display: block;
                font-size: 18pt;
                font-weight: 700;
                color: #05573c;
                border-bottom: 3px solid #05573c;
                padding-bottom: 8px;
                margin-bottom: 16px;
            }
            .admin-main::after {
                content: "Printed on <?php echo date('d M Y, H:i'); ?>";
                display: block;
                font-size: 9pt;
                color: #666;
                text-align: right;
                margin-top: 12px;
            }

            table { width: 100% !important; border-collapse: collapse !important; font-size: 9pt; }
            table th, table td {
                border: 1px solid #999 !important;
                padding: 6px 8px !important;
                color: #000 !important;
                background: #fff !important;
            }
            table thead th {
                background: #f0f0f0 !important;
                font-weight: 700 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            table tbody tr { page-break-inside: avoid; }

            .badge {
                background: transparent !important;
                color: #000 !important;
                border: 1px solid #666 !important;
                padding: 2px 6px !important;
                font-weight: 600 !important;
            }
        }
    </style>
</body>
</html>
