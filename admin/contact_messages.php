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
        if ($action === 'mark_read' && $id) {
            $pdo->prepare("UPDATE contact_us SET status = 'read' WHERE id = ?")->execute([$id]);
            $message = 'Message marked as read.';
            $messageType = 'success';
        } elseif ($action === 'mark_unread' && $id) {
            $pdo->prepare("UPDATE contact_us SET status = 'unread' WHERE id = ?")->execute([$id]);
            $message = 'Message marked as unread.';
            $messageType = 'success';
        } elseif ($action === 'delete' && $id) {
            $pdo->prepare("DELETE FROM contact_us WHERE id = ?")->execute([$id]);
            $message = 'Message deleted.';
            $messageType = 'success';
        } elseif ($action === 'mark_all_read') {
            $stmt = $pdo->exec("UPDATE contact_us SET status = 'read' WHERE status = 'unread'");
            $message = "Marked {$stmt} message(s) as read.";
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        error_log('Contact action error: ' . $e->getMessage());
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
            SELECT id, name, email, message, status, created_at
            FROM contact_us
            ORDER BY created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="contact_messages_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Name', 'Email', 'Message', 'Status', 'Received']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'], $r['name'], $r['email'], $r['message'], $r['status'], $r['created_at']]);
        }
        fclose($out);
        exit;
    } catch (PDOException $e) {
        error_log('Contact CSV export error: ' . $e->getMessage());
    }
}

// ============================================
// FETCH MESSAGES
// ============================================
$messages = [];
try {
    $messages = $pdo->query("
        SELECT *
        FROM contact_us
        ORDER BY 
            CASE WHEN status = 'unread' THEN 0 ELSE 1 END,
            created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Contact fetch error: ' . $e->getMessage());
}

$total       = count($messages);
$unreadCount = 0;
$readCount   = 0;
$todayCount  = 0;
$today       = date('Y-m-d');

foreach ($messages as $m) {
    if (($m['status'] ?? 'unread') === 'unread') $unreadCount++;
    else                                          $readCount++;

    if (!empty($m['created_at']) && substr($m['created_at'], 0, 10) === $today) {
        $todayCount++;
    }
}

$page_title = 'Contact Messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - WittyMart Admin</title>
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
        .status-unread { background: #fd7e14; }
        .status-read   { background: #6c757d; }

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
        .stat-card.warning { border-left-color: #fd7e14; }
        .stat-card.muted   { border-left-color: #6c757d; }
        .stat-card.info    { border-left-color: #17a2b8; }

        .results-info {
            padding: 10px 14px;
            background: #fafafa;
            border-bottom: 1px solid #eee;
            font-size: 13px; color: #666;
        }
        .results-info strong { color: #05573c; }

        .msg-row { cursor: pointer; transition: background .15s ease; }
        .msg-row:hover { background: #f8f9fa; }
        .msg-row.unread { font-weight: 600; }
        .msg-row.unread td:nth-child(2)::before {
            content: '●';
            color: #fd7e14;
            margin-right: 6px;
            font-size: 10px;
        }
        .msg-row td { vertical-align: top; }

        .msg-preview {
            color: #555;
            font-size: 13px;
            max-width: 380px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .msg-name { color: #222; }
        .msg-email { font-size: 12px; color: #666; font-family: 'SF Mono', 'Courier New', monospace; }
        .msg-time  { font-size: 12px; color: #888; white-space: nowrap; }

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
        .btn-sm.success { background: #28a745; color: #fff; }
        .btn-sm.success:hover { background: #218838; }

        .export-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 16px; background: #05573c; color: #fff;
            border-radius: 6px; text-decoration: none;
            font-weight: 600; font-size: 13px;
            border: none; cursor: pointer; font-family: inherit;
        }
        .export-btn:hover { background: #03402c; }
        .export-btn.secondary { background: #6c757d; }
        .export-btn.secondary:hover { background: #5a6268; }

        /* ===== MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            justify-content: center; align-items: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 12px;
            max-width: 620px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 28px;
            position: relative;
        }
        .modal-box .close-x {
            position: absolute; top: 14px; right: 16px;
            background: none; border: none;
            font-size: 24px; cursor: pointer; color: #888;
        }
        .modal-box h2 { margin: 0 0 6px; font-size: 20px; color: #222; }
        .modal-box .from-email { color: #666; font-size: 13px; margin-bottom: 16px; }
        .modal-box .msg-body {
            background: #f8f9fa; padding: 16px 18px;
            border-radius: 10px; font-size: 14px;
            line-height: 1.6; color: #333;
            white-space: pre-wrap;
            margin-bottom: 20px;
        }
        .modal-actions {
            display: flex; gap: 10px;
            justify-content: flex-end; flex-wrap: wrap;
        }
        .btn-reply {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px; background: #05573c; color: #fff;
            border-radius: 8px; text-decoration: none;
            font-weight: 600; font-size: 13px;
        }
        .btn-reply:hover { background: #03402c; }
        .btn-secondary-modal {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px; background: #f0f0f0; color: #333;
            border-radius: 8px; border: none;
            font-weight: 600; font-size: 13px; cursor: pointer;
            font-family: inherit;
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <div class="admin-wrapper">
        <?php include "sidebar.php"; ?>

        <main class="admin-main">
            <header class="admin-header" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <span class="badge badge-info" style="padding:8px 16px; background:#e8f5f0; color:#05573c; border-radius:20px; font-weight:600;">
                    <i class="fas fa-envelope"></i> <?php echo $unreadCount; ?> unread / <?php echo $total; ?> total
                </span>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php if ($unreadCount > 0): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Mark all messages as read?');">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="export-btn secondary">
                                <i class="fas fa-check-double"></i> Mark All Read
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="?export=csv" class="export-btn">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </header>

            <?php if ($message): ?>
                <div style="padding:12px 18px; border-radius:6px; margin-bottom:15px; background:<?php echo $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color:<?php echo $messageType === 'success' ? '#155724' : '#721c24'; ?>;">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="admin-card" style="padding:0; overflow:hidden;">
                <div class="table-toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchMsgs" placeholder="Search by name, email, or message..." oninput="applyFilters()">
                    </div>
                    <div class="filter-controls">
                        <select id="statusFilter" onchange="applyFilters()">
                            <option value="">All Messages</option>
                            <option value="unread">Unread only</option>
                            <option value="read">Read only</option>
                        </select>
                    </div>
                </div>

                <div class="stats-row">
                    <div class="stat-card warning">
                        <div class="stat-label">Unread</div>
                        <div class="stat-value"><?php echo $unreadCount; ?></div>
                    </div>
                    <div class="stat-card muted">
                        <div class="stat-label">Read</div>
                        <div class="stat-value"><?php echo $readCount; ?></div>
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
                    Showing <strong id="visibleCount"><?php echo $total; ?></strong> of <strong><?php echo $total; ?></strong> messages
                </div>

                <div style="padding:0;">
                    <?php if ($total > 0): ?>
                        <div style="overflow-x:auto;">
                            <table class="admin-table" id="msgsTable">
                                <thead>
                                    <tr>
                                        <th style="width:60px;">Status</th>
                                        <th>From</th>
                                        <th>Message</th>
                                        <th style="width:140px;">Received</th>
                                        <th style="width:180px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $m): ?>
                                        <?php
                                            $isUnread = ($m['status'] ?? 'unread') === 'unread';
                                            $searchText = strtolower(($m['name'] ?? '') . ' ' . ($m['email'] ?? '') . ' ' . ($m['message'] ?? ''));
                                        ?>
                                        <tr class="msg-row <?php echo $isUnread ? 'unread' : ''; ?>"
                                            data-status="<?php echo htmlspecialchars($m['status'] ?? 'unread'); ?>"
                                            data-search="<?php echo htmlspecialchars($searchText); ?>">
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars($m['status'] ?? 'unread'); ?>">
                                                    <?php echo htmlspecialchars($m['status'] ?? 'unread'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="msg-name"><?php echo htmlspecialchars($m['name']); ?></div>
                                                <div class="msg-email"><?php echo htmlspecialchars($m['email']); ?></div>
                                            </td>
                                            <td>
                                                <div class="msg-preview"><?php echo htmlspecialchars($m['message']); ?></div>
                                            </td>
                                            <td class="msg-time">
                                                <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($m['created_at']))); ?>
                                            </td>
                                            <td>
                                                <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                                    <button class="btn-sm info" title="View" onclick="viewMessage(<?php echo (int)$m['id']; ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <?php if ($isUnread): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action" value="mark_read">
                                                            <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                                                            <button type="submit" class="btn-sm success" title="Mark read">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                                                        <button type="submit" class="btn-sm danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Hidden data for the modal -->
                                        <script type="application/json" id="msg-data-<?php echo (int)$m['id']; ?>">
                                            <?php
                                                echo json_encode([
                                                    'id'      => $m['id'],
                                                    'name'    => $m['name'],
                                                    'email'   => $m['email'],
                                                    'message' => $m['message'],
                                                    'status'  => $m['status'] ?? 'unread',
                                                    'created' => date('d M Y, H:i', strtotime($m['created_at']))
                                                ]);
                                            ?>
                                        </script>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align:center; padding:60px 20px; color:#888;">
                            <i class="fas fa-inbox" style="font-size:48px; display:block; margin-bottom:10px; opacity:.3;"></i>
                            No contact messages yet.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Message View Modal -->
    <div class="modal-overlay" id="msgModal" onclick="if(event.target===this) closeModal()">
        <div class="modal-box">
            <button class="close-x" onclick="closeModal()">&times;</button>
            <h2 id="modalName">—</h2>
            <div class="from-email" id="modalEmail">—</div>
            <div class="msg-body" id="modalBody">—</div>
            <div class="modal-actions">
                <a id="modalReply" href="#" class="btn-reply">
                    <i class="fas fa-reply"></i> Reply by Email
                </a>
                <button class="btn-secondary-modal" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function viewMessage(id) {
            const scriptTag = document.getElementById('msg-data-' + id);
            if (!scriptTag) return;
            let data;
            try { data = JSON.parse(scriptTag.textContent); } catch (e) { return; }

            document.getElementById('modalName').textContent  = data.name || '(no name)';
            document.getElementById('modalEmail').textContent = data.email + ' · ' + data.created;
            document.getElementById('modalBody').textContent  = data.message;
            document.getElementById('modalReply').href = 'mailto:' + data.email
                + '?subject=' + encodeURIComponent('Re: Your message to WittyMart')
                + '&body=' + encodeURIComponent('\n\n---\nOriginal message:\n' + data.message);

            document.getElementById('msgModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('msgModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });

        function applyFilters() {
            const search = (document.getElementById('searchMsgs').value || '').toLowerCase().trim();
            const status = document.getElementById('statusFilter').value.toLowerCase();
            const rows   = document.querySelectorAll('#msgsTable tbody .msg-row');
            let visible = 0;

            rows.forEach(row => {
                const rowSearch = row.dataset.search || '';
                const rowStatus = (row.dataset.status || '').toLowerCase();
                let show = true;
                if (search && rowSearch.indexOf(search) === -1) show = false;
                if (status && rowStatus !== status) show = false;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            document.getElementById('visibleCount').textContent = visible;
        }
    </script>
</body>
</html>
