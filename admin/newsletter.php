<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
requireAdmin();

global $pdo;

$message = '';
$messageType = '';

// ============================================
// ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id     = intval($_POST['id'] ?? 0);

    try {
        if ($action === 'delete' && $id) {
            $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id = ?")->execute([$id]);
            $message = 'Subscriber deleted.';
            $messageType = 'success';
        } elseif ($action === 'toggle_status' && $id) {
            $pdo->prepare("
                UPDATE newsletter_subscribers
                SET status = CASE
                    WHEN status = 'active' THEN 'inactive'
                    ELSE 'active'
                END
                WHERE id = ?
            ")->execute([$id]);
            $message = 'Status updated.';
            $messageType = 'success';
        } elseif ($action === 'delete_inactive') {
            $stmt = $pdo->exec("DELETE FROM newsletter_subscribers WHERE status <> 'active'");
            $message = "Deleted {$stmt} inactive subscriber(s).";
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        error_log('Newsletter action error: ' . $e->getMessage());
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ============================================
// EXPORT CSV
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $rows = $pdo->query("
            SELECT email, status, source, ip_address, subscribed_at, created_at
            FROM newsletter_subscribers
            ORDER BY created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="newsletter_subscribers_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Email', 'Status', 'Source', 'IP', 'Subscribed At', 'Created At']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['email'], $r['status'], $r['source'] ?? '',
                $r['ip_address'] ?? '', $r['subscribed_at'] ?? '', $r['created_at']
            ]);
        }
        fclose($out);
        exit;
    } catch (PDOException $e) {
        error_log('Newsletter CSV export error: ' . $e->getMessage());
    }
}

// ============================================
// FETCH SUBSCRIBERS
// ============================================
$subscribers = [];
try {
    $subscribers = $pdo->query("
        SELECT *
        FROM newsletter_subscribers
        ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Newsletter fetch error: ' . $e->getMessage());
}

$total       = count($subscribers);
$activeCount = 0;
$inactiveCount = 0;
$todayCount  = 0;
$today       = date('Y-m-d');

foreach ($subscribers as $s) {
    if (($s['status'] ?? 'active') === 'active') $activeCount++;
    else                                          $inactiveCount++;

    if (!empty($s['created_at']) && substr($s['created_at'], 0, 10) === $today) {
        $todayCount++;
    }
}

$page_title = 'Newsletter Subscribers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
            text-transform: capitalize;
        }
        .status-active   { background: #28a745; }
        .status-inactive { background: #6c757d; }

        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 14px;
            background: #fafafa;
            border-bottom: 1px solid #eee;
        }
        .search-box {
            display: flex; align-items: center; gap: 10px;
            background: #fff; padding: 6px 14px;
            border-radius: 8px; border: 1px solid #ddd;
            flex: 1; min-width: 240px; max-width: 420px;
        }
        .search-box input {
            border: none; background: transparent; padding: 8px 0;
            outline: none; width: 100%; font-size: 14px;
        }
        .filter-controls { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-controls select {
            padding: 8px 12px; border-radius: 6px; border: 1px solid #ddd;
            background: #fff; font-size: 13px; cursor: pointer;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            padding: 14px;
            background: #fff;
            border-bottom: 1px solid #eee;
        }
        .stat-card {
            background: #f8f9fa; padding: 12px 16px;
            border-radius: 8px; border-left: 4px solid #05573c;
        }
        .stat-card .stat-label {
            font-size: 11px; text-transform: uppercase;
            letter-spacing: .5px; color: #888; margin-bottom: 4px;
        }
        .stat-card .stat-value { font-size: 22px; font-weight: 700; color: #333; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.info    { border-left-color: #17a2b8; }
        .stat-card.muted   { border-left-color: #6c757d; }

        .results-info {
            padding: 10px 14px;
            background: #fafafa;
            border-bottom: 1px solid #eee;
            font-size: 13px; color: #666;
        }
        .results-info strong { color: #05573c; }

        .btn-sm {
            background: #f0f0f0; color: #333;
            border: none; padding: 5px 9px;
            border-radius: 4px; cursor: pointer; font-size: 11px;
        }
        .btn-sm:hover { background: #e0e0e0; }
        .btn-sm.danger { background: #dc3545; color: #fff; }
        .btn-sm.danger:hover { background: #c82333; }
        .btn-sm.info   { background: #17a2b8; color: #fff; }
        .btn-sm.info:hover { background: #138496; }

        .export-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 16px; background: #05573c; color: #fff;
            border-radius: 6px; text-decoration: none;
            font-weight: 600; font-size: 13px;
        }
        .export-btn:hover { background: #03402c; }

        .email-cell { font-family: 'SF Mono', 'Courier New', monospace; font-size: 12.5px; }
        .date-cell { font-size: 12px; color: #666; white-space: nowrap; }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>

        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <span class="badge badge-info" style="padding:8px 16px; background:#e8f5f0; color:#05573c; border-radius:20px; font-weight:600;">
                    <i class="fas fa-envelope-open-text"></i> <?php echo $total; ?> subscribers
                </span>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <form method="POST" onsubmit="return confirm('Delete all inactive subscribers?');" style="display:inline;">
                        <input type="hidden" name="action" value="delete_inactive">
                        <button type="submit" class="export-btn" style="background:#6c757d;">
                            <i class="fas fa-broom"></i> Clear Inactive
                        </button>
                    </form>
                    <a href="?export=csv" class="export-btn">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>" style="padding:12px 18px; border-radius:6px; margin-bottom:15px; background:<?php echo $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color:<?php echo $messageType === 'success' ? '#155724' : '#721c24'; ?>;">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-card" style="padding:0; overflow:hidden;">
                <div class="table-toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchSubs" placeholder="Search by email..." oninput="applyFilters()">
                    </div>
                    <div class="filter-controls">
                        <select id="statusFilter" onchange="applyFilters()">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="stats-row">
                    <div class="stat-card success">
                        <div class="stat-label">Active</div>
                        <div class="stat-value"><?php echo $activeCount; ?></div>
                    </div>
                    <div class="stat-card muted">
                        <div class="stat-label">Inactive</div>
                        <div class="stat-value"><?php echo $inactiveCount; ?></div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-label">Today</div>
                        <div class="stat-value"><?php echo $todayCount; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total</div>
                        <div class="stat-value"><?php echo $total; ?></div>
                    </div>
                </div>

                <div class="results-info">
                    Showing <strong id="visibleCount"><?php echo $total; ?></strong> of <strong><?php echo $total; ?></strong> subscribers
                </div>

                <div style="padding:0;">
                    <?php if ($total > 0): ?>
                        <div style="overflow-x:auto;">
                            <table class="admin-table" id="subsTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Source</th>
                                        <th>Subscribed</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscribers as $s): ?>
                                        <tr data-status="<?php echo htmlspecialchars($s['status'] ?? 'active'); ?>"
                                            data-email="<?php echo htmlspecialchars(strtolower($s['email'])); ?>">
                                            <td><?php echo (int)$s['id']; ?></td>
                                            <td class="email-cell"><?php echo htmlspecialchars($s['email']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars($s['status'] ?? 'active'); ?>">
                                                    <?php echo htmlspecialchars($s['status'] ?? 'active'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($s['source'] ?? '—'); ?></td>
                                            <td class="date-cell">
                                                <?php
                                                    $when = $s['subscribed_at'] ?? $s['created_at'] ?? '';
                                                    echo $when ? htmlspecialchars(date('d M Y, H:i', strtotime($when))) : '—';
                                                ?>
                                            </td>
                                            <td>
                                                <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                                    <a href="mailto:<?php echo htmlspecialchars($s['email']); ?>" class="btn-sm info" title="Email this subscriber">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </a>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                                        <button type="submit" class="btn-sm" title="Toggle status">
                                                            <i class="fas fa-toggle-on"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this subscriber?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                                        <button type="submit" class="btn-sm danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align:center; padding:60px 20px; color:#888;">
                            <i class="fas fa-envelope" style="font-size:48px; display:block; margin-bottom:10px; opacity:.3;"></i>
                            No subscribers yet.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function applyFilters() {
            const search = (document.getElementById('searchSubs').value || '').toLowerCase().trim();
            const status = document.getElementById('statusFilter').value.toLowerCase();
            const rows   = document.querySelectorAll('#subsTable tbody tr');
            let visible = 0;

            rows.forEach(row => {
                const rowEmail  = row.dataset.email || '';
                const rowStatus = (row.dataset.status || '').toLowerCase();
                let show = true;
                if (search && rowEmail.indexOf(search) === -1) show = false;
                if (status && rowStatus !== status) show = false;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            document.getElementById('visibleCount').textContent = visible;
        }
    </script>
</body>
</html>
