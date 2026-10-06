<?php

require_once 'includes/config.php';
requireAdmin();

// ============================================
// DETECT AVAILABLE SOURCE TABLES
// Both tables have DIFFERENT schemas:
//   activity_log  (singular): uses `details`
//   activity_logs (plural):   uses `description`
// ============================================
$hasLogSingular = false;
$hasLogPlural   = false;

try {
    $tables = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name IN ('activity_log', 'activity_logs')
    ")->fetchAll(PDO::FETCH_COLUMN);

    $hasLogSingular = in_array('activity_log',  $tables, true);
    $hasLogPlural   = in_array('activity_logs', $tables, true);
} catch (PDOException $e) {
    error_log('Table detect error: ' . $e->getMessage());
}

// ============================================
// BUILD UNION query that works with BOTH schemas
// ============================================
$buildUnionQuery = function () use ($hasLogSingular, $hasLogPlural) {
    $queries = [];

    if ($hasLogPlural) {
        $queries[] = "
            SELECT
                id,
                user_id,
                user_name,
                action,
                description AS description,
                ip_address,
                user_agent,
                created_at,
                'activity_logs' AS source_table
            FROM activity_logs
        ";
    }

    if ($hasLogSingular) {
        $queries[] = "
            SELECT
                id,
                user_id,
                user_name,
                action,
                details AS description,
                ip_address,
                user_agent,
                created_at,
                'activity_log' AS source_table
            FROM activity_log
        ";
    }

    if (empty($queries)) {
        return null;
    }

    return '(' . implode("\nUNION ALL\n", $queries) . ') AS combined_logs';
};

$unionSql = $buildUnionQuery();

// ============================================
// FILTER STATE
// ============================================
$filterAction = trim($_GET['filter_action'] ?? '');
$filterUser   = trim($_GET['filter_user']   ?? '');
$filterFrom   = trim($_GET['filter_from']   ?? '');
$filterTo     = trim($_GET['filter_to']     ?? '');
$filterSource = trim($_GET['filter_source'] ?? '');

$page    = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

function buildActivityFilter($filterAction, $filterUser, $filterFrom, $filterTo, $filterSource) {
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
    if ($filterSource !== '' && in_array($filterSource, ['activity_log', 'activity_logs'], true)) {
        $where[]  = "source_table = ?";
        $params[] = $filterSource;
    }

    $sql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
    return [$sql, $params];
}

// ============================================
// CSV EXPORT
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        if (!$unionSql) {
            throw new Exception('No log tables available');
        }

        list($whereSql, $params) = buildActivityFilter(
            $filterAction, $filterUser, $filterFrom, $filterTo, $filterSource
        );

        $sql = "SELECT * FROM {$unionSql}{$whereSql} ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filename = 'activity_logs_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'User ID', 'User', 'Action', 'Description', 'IP Address', 'User Agent', 'Date', 'Source']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'] ?? '',
                $r['user_id'] ?? '',
                $r['user_name'] ?? 'System',
                $r['action'] ?? '',
                $r['description'] ?? '',
                $r['ip_address'] ?? '',
                $r['user_agent'] ?? '',
                $r['created_at'] ?? '',
                $r['source_table'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    } catch (PDOException $e) {
        error_log('Activity logs CSV export error: ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('Activity logs CSV export: ' . $e->getMessage());
    }
}

// ============================================
// PAGINATED FETCH
// ============================================
list($whereSql, $params) = buildActivityFilter(
    $filterAction, $filterUser, $filterFrom, $filterTo, $filterSource
);

$offset = ($page - 1) * $perPage;
$logs = [];
$total = 0;
$totalPages = 1;

if ($unionSql) {
    try {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$unionSql}{$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));

        $listSql = "SELECT * FROM {$unionSql}{$whereSql}
                    ORDER BY created_at DESC
                    LIMIT ? OFFSET ?";

        $listStmt = $pdo->prepare($listSql);
        $listStmt->execute(array_merge($params, [$perPage, $offset]));
        $logs = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log('Activity logs fetch error: ' . $e->getMessage());
    }
}

// ============================================
// PRINT-ALL
// ============================================
$printLogs = [];
if ($unionSql) {
    try {
        $printSql = "SELECT * FROM {$unionSql}{$whereSql} ORDER BY created_at DESC";
        $printStmt = $pdo->prepare($printSql);
        $printStmt->execute($params);
        $printLogs = $printStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Activity logs print fetch error: ' . $e->getMessage());
        $printLogs = $logs;
    }
}

// ============================================
// CLEAR OLD LOGS — clears from BOTH tables
// Reports per-table counts and never fails silently
// ============================================
$message = '';
$messageType = '';
$clearDetails = [];

if (isset($_GET['clear']) && $_GET['clear'] === 'true') {
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;

    if ($days < 0) {
        $message = 'Please enter a valid number of days (0 or greater).';
        $messageType = 'error';
    } else {
        // Helper: safely check if a table exists (in `public` schema)
        $tableExists = function ($pdo, $tbl) {
            try {
                $stmt = $pdo->prepare("
                    SELECT 1
                    FROM information_schema.tables
                    WHERE table_schema = 'public' AND table_name = ?
                    LIMIT 1
                ");
                $stmt->execute([$tbl]);
                return (bool)$stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log("tableExists({$tbl}) error: " . $e->getMessage());
                return false;
            }
        };

        $totalDeleted = 0;
        $tablesToClear = ['activity_logs', 'activity_log']; // plural first, singular second

        foreach ($tablesToClear as $tbl) {
            // Skip tables that don't exist
            if (!$tableExists($pdo, $tbl)) {
                $clearDetails[] = "⚠ Table `{$tbl}` does not exist — skipped.";
                continue;
            }

            try {
                // 1. Count how many rows will be deleted
                $countStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM {$tbl}
                    WHERE created_at < NOW() - make_interval(days => ?)
                ");
                $countStmt->execute([$days]);
                $toDelete = (int)$countStmt->fetchColumn();

                // 2. Also count TOTAL rows in the table (for context)
                $totalInTable = (int)$pdo->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();

                if ($toDelete === 0) {
                    $clearDetails[] = "ℹ `{$tbl}`: 0 row(s) older than {$days} day(s) (table has {$totalInTable} total).";
                    continue;
                }

                // 3. Actually delete
                $delStmt = $pdo->prepare("
                    DELETE FROM {$tbl}
                    WHERE created_at < NOW() - make_interval(days => ?)
                ");
                $delStmt->execute([$days]);
                $deleted = $delStmt->rowCount();
                $totalDeleted += $deleted;

                $remaining = $totalInTable - $deleted;
                $clearDetails[] = "✓ `{$tbl}`: deleted {$deleted} row(s), {$remaining} remaining.";
            } catch (PDOException $e) {
                error_log("Clear {$tbl} error: " . $e->getMessage());
                $clearDetails[] = "✗ `{$tbl}`: FAILED — " . htmlspecialchars($e->getMessage());
            }
        }

        if ($totalDeleted > 0) {
            $dayLabel = $days === 1 ? '1 day' : "{$days} days";
            $message = "Cleared {$totalDeleted} log(s) older than {$dayLabel}.";
            $messageType = 'success';

            if (function_exists('logActivity')) {
                logActivity(
                    'clear_logs',
                    "Cleared {$totalDeleted} log(s) across both tables (older than {$dayLabel})",
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['user_name'] ?? 'Admin'
                );
            }
        } else {
            $message = "Nothing to delete — no logs older than {$days} day(s) in either table.";
            $messageType = 'error';
        }
    }
}

// ============================================
// DISTINCT ACTIONS (across both tables)
// ============================================
$actionsList = [];
if ($unionSql) {
    try {
        $actionsList = $pdo->query("
            SELECT DISTINCT action FROM {$unionSql}
            WHERE action IS NOT NULL AND action <> ''
            ORDER BY action
        ")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('Fetch distinct actions error: ' . $e->getMessage());
    }
}

/** Preserve filters on URL build */
function buildQueryString($overrides = []) {
    $params = array_merge([
        'filter_action' => $_GET['filter_action'] ?? '',
        'filter_user'   => $_GET['filter_user']   ?? '',
        'filter_from'   => $_GET['filter_from']   ?? '',
        'filter_to'     => $_GET['filter_to']     ?? '',
        'filter_source' => $_GET['filter_source'] ?? '',
        'page'          => $_GET['page']          ?? 1,
    ], $overrides);
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
                    <?php if ($filterAction || $filterUser || $filterFrom || $filterTo || $filterSource): ?>
                        <span style="color:#888;">(filtered)</span>
                    <?php endif; ?>
                    <?php if ($hasLogSingular && $hasLogPlural): ?>
                        <span style="color:#888;"> · merged from 2 tables</span>
                    <?php endif; ?>
                </span>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn-sm btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="<?php echo htmlspecialchars(buildQueryString(['export' => 'csv', 'page' => null])); ?>"
                       class="btn-sm btn-success">
                        <i class="fas fa-file-csv"></i> Download CSV
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

                    <?php if (!empty($clearDetails)): ?>
                        <ul style="margin:10px 0 0 20px; font-size:13px;">
                            <?php foreach ($clearDetails as $line): ?>
                                <li><?php echo $line; /* already escaped where needed */ ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
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

                        <?php if ($hasLogSingular && $hasLogPlural): ?>
                            <select name="filter_source" class="filter-select">
                                <option value="">Both Tables</option>
                                <option value="activity_logs" <?php echo $filterSource === 'activity_logs' ? 'selected' : ''; ?>>activity_logs (plural)</option>
                                <option value="activity_log"  <?php echo $filterSource === 'activity_log'  ? 'selected' : ''; ?>>activity_log (singular)</option>
                            </select>
                        <?php endif; ?>

                        <input type="date" name="filter_from" class="filter-select"
                               value="<?php echo htmlspecialchars($filterFrom); ?>"
                               title="From date">

                        <input type="date" name="filter_to" class="filter-select"
                               value="<?php echo htmlspecialchars($filterTo); ?>"
                               title="To date">

                        <button type="submit" class="btn-sm btn-primary">
                            <i class="fas fa-filter"></i> Apply
                        </button>

                        <?php if ($filterAction || $filterUser || $filterFrom || $filterTo || $filterSource): ?>
                            <a href="activity_logs.php" class="btn-sm" style="background:#f0f0f0; color:#333; text-decoration:none; padding:8px 14px; border-radius:4px; font-size:12px; font-weight:600;">
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
                                    <th>Source</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($log['id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo getActivityBadge($log['action'] ?? ''); ?>">
                                                <?php echo ucfirst(htmlspecialchars($log['action'] ?? '')); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if (($log['source_table'] ?? '') === 'activity_log'): ?>
                                                <span class="badge badge-system" style="font-size:10px;" title="Legacy table">old</span>
                                            <?php else: ?>
                                                <span class="badge badge-create" style="font-size:10px;">new</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $created = $log['created_at'] ?? null;
                                                echo $created ? date('M d, Y H:i', strtotime($created)) : '—';
                                            ?>
                                        </td>
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
                            <?php if ($filterAction || $filterUser || $filterFrom || $filterTo || $filterSource): ?>
                                <br><small style="color:#999;">(Filters are applied — try clearing them)</small>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- ============================================
         HIDDEN PRINT TABLE (all filtered rows)
         ============================================ -->
    <div class="print-only-table">
        <div class="print-header">
            <h1>WittyMart — Activity Logs</h1>
            <div class="print-meta">
                <?php if ($filterAction || $filterUser || $filterFrom || $filterTo || $filterSource): ?>
                    <span class="print-filter">
                        Filtered:
                        <?php if ($filterAction): ?> action = <strong><?php echo htmlspecialchars($filterAction); ?></strong><?php endif; ?>
                        <?php if ($filterUser): ?> user contains <strong><?php echo htmlspecialchars($filterUser); ?></strong><?php endif; ?>
                        <?php if ($filterSource): ?> source = <strong><?php echo htmlspecialchars($filterSource); ?></strong><?php endif; ?>
                        <?php if ($filterFrom): ?> from <strong><?php echo htmlspecialchars($filterFrom); ?></strong><?php endif; ?>
                        <?php if ($filterTo): ?> to <strong><?php echo htmlspecialchars($filterTo); ?></strong><?php endif; ?>
                    </span>
                <?php else: ?>
                    <span class="print-filter">All activity logs (merged)</span>
                <?php endif; ?>
                <span class="print-count">Total: <strong><?php echo count($printLogs); ?></strong> log(s)</span>
                <span class="print-date">Printed on <?php echo date('d M Y, H:i'); ?></span>
            </div>
        </div>

        <?php if (count($printLogs) > 0): ?>
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th style="width:120px;">User</th>
                        <th style="width:100px;">Action</th>
                        <th>Description</th>
                        <th style="width:110px;">IP Address</th>
                        <th style="width:60px;">Src</th>
                        <th style="width:130px;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($printLogs as $log): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($log['id'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($log['action'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($log['description'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                            <td><?php echo ($log['source_table'] ?? '') === 'activity_log' ? 'old' : 'new'; ?></td>
                            <td>
                                <?php
                                    $created = $log['created_at'] ?? null;
                                    echo $created ? date('M d, Y H:i', strtotime($created)) : '—';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align:center; color:#666; padding:20px;">No activity logs found.</p>
        <?php endif; ?>
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
            const input = prompt(
                'Delete activity logs older than how many days?\n\n' +
                'Examples: 1 = older than 1 day, 7 = older than 1 week, 30 = older than 1 month, 0 = delete ALL logs',
                '30'
            );

            if (input === null) return;

            const days = parseInt(input, 10);

            if (isNaN(days) || days < 0) {
                alert('Please enter a valid number of days (0 or greater).');
                return;
            }

            let confirmMsg;
            if (days === 0) {
                confirmMsg = '⚠️ This will DELETE ALL activity logs from BOTH tables. Are you sure?';
            } else {
                confirmMsg = `Delete all logs older than ${days} day(s) from both tables?`;
            }

            if (confirm(confirmMsg)) {
                window.location.href = '?clear=true&days=' + days;
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

        .pagination { display:flex; justify-content:center; gap:5px; margin-top:15px; flex-wrap:wrap; align-items:center; }
        .page-link { display:inline-block; padding:6px 12px; background:var(--bg); color:var(--text); text-decoration:none; border-radius:4px; border:1px solid var(--border); transition:all 0.3s ease; font-size:13px; }
        .page-link:hover { background:var(--primary); color:#fff; }
        .page-link.active { background:var(--primary); color:#fff; border-color:var(--primary); }

        .filter-select { padding:8px 12px; border-radius:6px; border:1px solid #ddd; background:#fff; font-size:13px; font-family:inherit; cursor:pointer; }
        .filter-select:focus { outline:none; border-color:#05573c; box-shadow:0 0 0 3px rgba(5,87,60,0.1); }

        .search-box { display:flex; align-items:center; gap:10px; background:#fff; padding:6px 14px; border-radius:8px; border:1px solid #ddd; }
        .search-box input { border:none; background:transparent; padding:6px 0; outline:none; width:100%; font-size:13px; }

        .btn-sm.btn-primary { background:#05573c; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:600; font-family:inherit; }
        .btn-sm.btn-primary:hover { background:#03402c; }
        .btn-sm.btn-danger { background:#dc3545; color:#fff; border:none; padding:8px 14px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:600; font-family:inherit; }
        .btn-sm.btn-danger:hover { background:#c82333; }
        .btn-sm.btn-success { background:#28a745; color:#fff; text-decoration:none; padding:8px 14px; border-radius:4px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
        .btn-sm.btn-success:hover { background:#218838; }

        @media screen { .print-only-table { display:none; } }

        @media print {
            @page { margin:12mm 10mm; size:A4 portrait; }
            body > * { display:none !important; }
            body > .print-only-table { display:block !important; }
            header, .header, nav, .sidebar, .admin-sidebar, .admin-header,
            .table-toolbar, .logs-filter, .pagination, .alert, .btn-sm, form,
            .admin-main, .admin-card, .admin-wrapper { display:none !important; }

            .print-only-table { display:block !important; color:#000 !important; background:#fff !important; font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif; font-size:9.5pt; line-height:1.4; }
            .print-only-table .print-header { border-bottom:3px solid #05573c; padding-bottom:10px; margin-bottom:14px; }
            .print-only-table .print-header h1 { font-size:18pt; font-weight:800; color:#05573c; margin:0 0 6px; letter-spacing:0.3px; }
            .print-only-table .print-meta { font-size:9pt; color:#444; display:flex; flex-wrap:wrap; gap:6px 18px; align-items:center; }
            .print-only-table .print-meta strong { color:#000; }
            .print-only-table .print-filter { background:#f0faf5; border-left:3px solid #05573c; padding:3px 10px; border-radius:4px; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .print-only-table .print-count { background:#eef; padding:3px 10px; border-radius:4px; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .print-only-table .print-date { margin-left:auto; font-style:italic; color:#666; }
            .print-only-table .print-table { width:100%; border-collapse:collapse; }
            .print-only-table .print-table thead { display:table-header-group; }
            .print-only-table .print-table th { background:#05573c !important; color:#fff !important; text-align:left; font-weight:700; padding:7px 8px; border:1px solid #03402c; font-size:9pt; text-transform:uppercase; letter-spacing:0.4px; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .print-only-table .print-table td { padding:6px 8px; border:1px solid #bbb; color:#000; font-size:9pt; vertical-align:top; word-break:break-word; }
            .print-only-table .print-table tbody tr:nth-child(even) td { background:#f7f9f8 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .print-only-table .print-table tbody tr { page-break-inside:avoid; }
        }
    </style>
</body>
</html>
