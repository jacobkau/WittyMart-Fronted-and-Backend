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
        } elseif ($action === 'delete_selected') {
            $ids = $_POST['ids'] ?? [];
            $ids = array_filter(array_map('intval', (array)$ids));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $message = "Deleted {$stmt->rowCount()} subscriber(s).";
                $messageType = 'success';
            }
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
            border: none; cursor: pointer; font-family: inherit;
        }
        .export-btn:hover { background: #03402c; }
        .export-btn.gold { background: #d4a017; }
        .export-btn.gold:hover { background: #b8860b; }
        .export-btn:disabled { opacity: 0.6; cursor: not-allowed; }

        .email-cell { font-family: 'SF Mono', 'Courier New', monospace; font-size: 12.5px; }
        .date-cell { font-size: 12px; color: #666; white-space: nowrap; }

        /* ===== SELECTION ===== */
        .select-cell { width: 36px; text-align: center; }
        .select-cell input[type="checkbox"] {
            width: 16px; height: 16px; cursor: pointer; accent-color: #05573c;
        }

        /* ===== BULK ACTION BAR ===== */
        .bulk-bar {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            background: #fff9e6;
            border-top: 1px solid #f0e2b6;
            border-bottom: 1px solid #f0e2b6;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .bulk-bar.active { display: flex; }
        .bulk-bar .left {
            display: flex; align-items: center; gap: 12px;
            font-size: 14px; font-weight: 600; color: #7a5b00;
        }
        .bulk-bar .left i { color: #d4a017; }
        .bulk-bar .right { display: flex; gap: 8px; flex-wrap: wrap; }
        .bulk-bar button {
            padding: 8px 14px; border-radius: 6px; border: none;
            font-weight: 600; font-size: 13px; cursor: pointer;
            font-family: inherit; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-send   { background: #05573c; color: #fff; }
        .btn-send:hover { background: #03402c; }
        .btn-clear  { background: #f0f0f0; color: #333; }
        .btn-clear:hover { background: #e0e0e0; }
        .btn-del    { background: #dc3545; color: #fff; }
        .btn-del:hover { background: #c82333; }

        /* ===== COMPOSE MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 9999;
            justify-content: center; align-items: flex-start;
            padding: 30px 16px;
            overflow-y: auto;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 14px;
            width: 100%; max-width: 680px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            margin: auto;
        }
        .modal-head {
            padding: 20px 26px;
            border-bottom: 1px solid #eee;
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-head h2 {
            margin: 0; font-size: 19px; color: #05573c;
            display: flex; align-items: center; gap: 10px;
        }
        .modal-head .close-x {
            background: none; border: none; font-size: 24px;
            cursor: pointer; color: #888; line-height: 1;
        }
        .modal-head .close-x:hover { color: #333; }

        .modal-body { padding: 24px 26px; }
        .modal-foot {
            padding: 16px 26px;
            border-top: 1px solid #eee;
            display: flex; justify-content: flex-end; gap: 10px;
            background: #fafafa;
            border-radius: 0 0 14px 14px;
        }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block; font-weight: 600; font-size: 13px;
            color: #555; margin-bottom: 6px;
        }
        .form-group .hint {
            font-size: 12px; color: #888; margin-top: 4px;
        }
        .form-group input[type="text"],
        .form-group input[type="file"],
        .form-group textarea {
            width: 100%; padding: 10px 14px;
            border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: 14px; font-family: inherit;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none; border-color: #05573c;
            box-shadow: 0 0 0 3px rgba(5,87,60,0.1);
        }
        .form-group textarea {
            min-height: 180px; resize: vertical; line-height: 1.55;
        }

        .recipient-summary {
            background: #e8f5f0;
            border-left: 4px solid #05573c;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            color: #155724;
            margin-bottom: 20px;
        }
        .recipient-summary strong { color: #05573c; }

        /* ===== PROGRESS ===== */
        .progress-wrap {
            display: none;
            padding: 20px 26px;
            border-top: 1px solid #eee;
            background: #fafafa;
        }
        .progress-wrap.active { display: block; }
        .progress-bar {
            height: 10px;
            background: #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #05573c, #0a7a54);
            width: 0%;
            transition: width 0.3s ease;
        }
        .progress-text {
            font-size: 13px;
            color: #555;
            text-align: center;
        }
        .progress-text .count {
            font-weight: 700;
            color: #05573c;
        }

        .send-result {
            display: none;
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .send-result.active { display: block; }
        .send-result.success { background: #d4edda; color: #155724; }
        .send-result.error   { background: #f8d7da; color: #721c24; }

        @media (max-width: 640px) {
            .modal-body { padding: 18px; }
            .modal-head, .modal-foot { padding: 14px 18px; }
            .modal-head h2 { font-size: 16px; }
            .bulk-bar { flex-direction: column; align-items: stretch; }
            .bulk-bar .right { justify-content: stretch; }
            .bulk-bar button { flex: 1; justify-content: center; }
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
                    <?php if ($activeCount > 0): ?>
                        <button type="button" class="export-btn gold" onclick="openComposeAll()">
                            <i class="fas fa-paper-plane"></i> Email All Active
                        </button>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>" style="padding:12px 18px; border-radius:6px; margin-bottom:15px; background:<?php echo $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color:<?php echo $messageType === 'success' ? '#155724' : '#721c24'; ?>;">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="send-result" id="sendResult"></div>

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

                <!-- ===== BULK ACTION BAR ===== -->
                <div class="bulk-bar" id="bulkBar">
                    <div class="left">
                        <i class="fas fa-check-square"></i>
                        <span><span id="selectedCount">0</span> subscriber(s) selected</span>
                    </div>
                    <div class="right">
                        <button type="button" class="btn-send" onclick="openComposeSelected()">
                            <i class="fas fa-paper-plane"></i> Send Newsletter
                        </button>
                        <button type="button" class="btn-clear" onclick="clearSelection()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                        <button type="button" class="btn-del" onclick="deleteSelected()">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <!-- Hidden form used to delete selected -->
                <form id="deleteSelectedForm" method="POST" style="display:none;">
                    <input type="hidden" name="action" value="delete_selected">
                    <div id="deleteSelectedIds"></div>
                </form>

                <div style="padding:0;">
                    <?php if ($total > 0): ?>
                        <div style="overflow-x:auto;">
                            <table class="admin-table" id="subsTable">
                                <thead>
                                    <tr>
                                        <th class="select-cell">
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" title="Select all">
                                        </th>
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
                                        <tr data-id="<?php echo (int)$s['id']; ?>"
                                            data-email="<?php echo htmlspecialchars($s['email']); ?>"
                                            data-status="<?php echo htmlspecialchars($s['status'] ?? 'active'); ?>">
                                            <td class="select-cell">
                                                <input type="checkbox" class="row-check" value="<?php echo (int)$s['id']; ?>"
                                                       onchange="updateSelection()">
                                            </td>
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

    <!-- ===== COMPOSE NEWSLETTER MODAL ===== -->
    <div class="modal-overlay" id="composeModal" onclick="if(event.target===this) closeCompose()">
        <div class="modal-box">
            <div class="modal-head">
                <h2><i class="fas fa-paper-plane"></i> Compose Newsletter</h2>
                <button class="close-x" onclick="closeCompose()">&times;</button>
            </div>

            <div class="modal-body">
                <div class="recipient-summary" id="recipientSummary">
                    Sending to <strong id="recipientCount">0</strong> recipient(s).
                </div>

                <div class="form-group">
                    <label>Subject <span style="color:#dc3545;">*</span></label>
                    <input type="text" id="nlSubject" maxlength="150" placeholder="e.g. New Arrivals at WittyMart!">
                </div>

                <div class="form-group">
                    <label>Message <span style="color:#dc3545;">*</span></label>
                    <textarea id="nlBody" placeholder="Write your newsletter message here..."></textarea>
                    <div class="hint">Plain text and simple line breaks. HTML is not supported in the body.</div>
                </div>

                <div class="form-group">
                    <label>Attach Image (optional)</label>
                    <input type="file" id="nlAttachment" accept="image/*">
                    <div class="hint">
                        If provided, the image is uploaded to Cloudinary and included in the email as an inline banner image.
                    </div>
                    <div id="attachmentPreview" style="margin-top:10px;"></div>
                </div>
            </div>

            <div class="progress-wrap" id="progressWrap">
                <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
                <div class="progress-text">
                    Sending… <span class="count"><span id="progressSent">0</span> / <span id="progressTotal">0</span></span>
                </div>
            </div>

            <div class="modal-foot">
                <button type="button" class="export-btn" style="background:#6c757d;" onclick="closeCompose()" id="cancelComposeBtn">Cancel</button>
                <button type="button" class="export-btn" onclick="sendNewsletter()" id="sendBtn">
                    <i class="fas fa-paper-plane"></i> Send Newsletter
                </button>
            </div>
        </div>
    </div>

    <script>
        // ============================================
        // SELECTION LOGIC
        // ============================================
        let currentRecipients = [];  

        function getVisibleCheckboxes() {
            return Array.from(document.querySelectorAll('#subsTable tbody tr'))
                .filter(tr => tr.style.display !== 'none')
                .map(tr => tr.querySelector('.row-check'))
                .filter(Boolean);
        }

        function toggleSelectAll(master) {
            getVisibleCheckboxes().forEach(cb => cb.checked = master.checked);
            updateSelection();
        }

        function updateSelection() {
            const allBoxes = document.querySelectorAll('#subsTable .row-check');
            const checked  = document.querySelectorAll('#subsTable .row-check:checked');
            const bulkBar  = document.getElementById('bulkBar');
            const countEl  = document.getElementById('selectedCount');
            const master   = document.getElementById('selectAll');

            countEl.textContent = checked.length;
            bulkBar.classList.toggle('active', checked.length > 0);

            // Update master checkbox state
            if (master) {
                const visible = getVisibleCheckboxes();
                const visibleChecked = visible.filter(cb => cb.checked).length;
                master.checked = visible.length > 0 && visibleChecked === visible.length;
                master.indeterminate = visibleChecked > 0 && visibleChecked < visible.length;
            }

            // Precompute recipients
            currentRecipients = Array.from(checked).map(cb => {
                const tr = cb.closest('tr');
                return {
                    id: tr.dataset.id,
                    email: tr.dataset.email
                };
            });
        }

        function clearSelection() {
            document.querySelectorAll('#subsTable .row-check').forEach(cb => cb.checked = false);
            const master = document.getElementById('selectAll');
            if (master) {
                master.checked = false;
                master.indeterminate = false;
            }
            updateSelection();
        }

        function deleteSelected() {
            if (currentRecipients.length === 0) return;
            if (!confirm('Delete ' + currentRecipients.length + ' subscriber(s)? This cannot be undone.')) return;

            const form = document.getElementById('deleteSelectedForm');
            const holder = document.getElementById('deleteSelectedIds');
            holder.innerHTML = '';
            currentRecipients.forEach(r => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'ids[]';
                inp.value = r.id;
                holder.appendChild(inp);
            });
            form.submit();
        }

        // ============================================
        // COMPOSE MODAL
        // ============================================
        function openCompose(recipients) {
            if (!recipients || recipients.length === 0) {
                alert('No recipients selected.');
                return;
            }
            currentRecipients = recipients;
            document.getElementById('recipientCount').textContent = recipients.length;
            document.getElementById('composeModal').classList.add('active');
            document.getElementById('nlSubject').focus();
            document.getElementById('nlSubject').value = '';
            document.getElementById('nlBody').value = '';
            document.getElementById('nlAttachment').value = '';
            document.getElementById('attachmentPreview').innerHTML = '';
            document.getElementById('progressWrap').classList.remove('active');
            document.getElementById('progressFill').style.width = '0%';
            document.getElementById('progressSent').textContent = '0';
            document.getElementById('progressTotal').textContent = recipients.length;
            document.getElementById('sendBtn').disabled = false;
            document.getElementById('sendBtn').innerHTML = '<i class="fas fa-paper-plane"></i> Send Newsletter';
        }

        function openComposeSelected() {
            if (currentRecipients.length === 0) {
                alert('Select at least one subscriber.');
                return;
            }
            openCompose(currentRecipients);
        }

        function openComposeAll() {
            // Collect all active subscribers
            const all = Array.from(document.querySelectorAll('#subsTable tbody tr'))
                .filter(tr => (tr.dataset.status || '').toLowerCase() === 'active')
                .map(tr => ({ id: tr.dataset.id, email: tr.dataset.email }));

            if (all.length === 0) {
                alert('No active subscribers to email.');
                return;
            }
            openCompose(all);
        }

        function closeCompose() {
            document.getElementById('composeModal').classList.remove('active');
        }

        // ============================================
        // SEND
        // ============================================
        async function sendNewsletter() {
            const subject = document.getElementById('nlSubject').value.trim();
            const body    = document.getElementById('nlBody').value.trim();
            const fileIn  = document.getElementById('nlAttachment');
            const sendBtn = document.getElementById('sendBtn');

            if (!subject) { alert('Please enter a subject.'); return; }
            if (!body)    { alert('Please enter a message body.'); return; }
            if (currentRecipients.length === 0) { alert('No recipients.'); return; }

            // 1) Upload attachment first (if any)
            let attachmentUrl = '';
            if (fileIn.files && fileIn.files[0]) {
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading image…';
                try {
                    const fd = new FormData();
                    fd.append('attachment', fileIn.files[0]);
                    const upRes = await fetch('upload_newsletter_image.php', { method: 'POST', body: fd });
                    const upData = await upRes.json();
                    if (!upData.success) {
                        throw new Error(upData.message || 'Upload failed');
                    }
                    attachmentUrl = upData.url;
                } catch (err) {
                    alert('Image upload failed: ' + err.message);
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Newsletter';
                    return;
                }
            }

            // 2) Send in batches
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
            document.getElementById('progressWrap').classList.add('active');

            const total = currentRecipients.length;
            document.getElementById('progressTotal').textContent = total;
            document.getElementById('progressSent').textContent = '0';
            document.getElementById('progressFill').style.width = '0%';

            const BATCH = 5;   // send 5 emails per request
            let sent = 0;
            let failed = 0;

            for (let i = 0; i < total; i += BATCH) {
                const batch = currentRecipients.slice(i, i + BATCH);
                try {
                    const res = await fetch('send_newsletter.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            recipients: batch.map(r => r.email),
                            subject: subject,
                            body: body,
                            attachment_url: attachmentUrl
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        sent += (data.sent || 0);
                        failed += (data.failed || 0);
                    } else {
                        failed += batch.length;
                    }
                } catch (err) {
                    console.error('Batch error:', err);
                    failed += batch.length;
                }

                const done = Math.min(i + BATCH, total);
                document.getElementById('progressSent').textContent = done;
                document.getElementById('progressFill').style.width = ((done / total) * 100) + '%';
            }

            // 3) Show result
            document.getElementById('progressWrap').classList.remove('active');

            const resultEl = document.getElementById('sendResult');
            resultEl.className = 'send-result active ' + (failed === 0 ? 'success' : 'error');
            resultEl.innerHTML = '<i class="fas fa-' + (failed === 0 ? 'check-circle' : 'exclamation-triangle') + '"></i> '
                + 'Sent <strong>' + sent + '</strong> of <strong>' + total + '</strong> email(s).'
                + (failed > 0 ? ' <strong>' + failed + '</strong> failed.' : '');

            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-check"></i> Done';

            setTimeout(() => {
                closeCompose();
                resultEl.classList.remove('active');
            }, 4000);

            // Reset selection after send
            clearSelection();
        }

        // ============================================
        // FILTERS
        // ============================================
        function applyFilters() {
            const search = (document.getElementById('searchSubs').value || '').toLowerCase().trim();
            const status = document.getElementById('statusFilter').value.toLowerCase();
            const rows   = document.querySelectorAll('#subsTable tbody tr');
            let visible = 0;

            rows.forEach(row => {
                const rowEmail  = (row.dataset.email || '').toLowerCase();
                const rowStatus = (row.dataset.status || '').toLowerCase();
                let show = true;
                if (search && rowEmail.indexOf(search) === -1) show = false;
                if (status && rowStatus !== status) show = false;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            document.getElementById('visibleCount').textContent = visible;
            updateSelection(); // recompute master checkbox based on visible
        }

        // Preview attachment
        document.getElementById('nlAttachment').addEventListener('change', function () {
            const box = document.getElementById('attachmentPreview');
            box.innerHTML = '';
            if (this.files && this.files[0]) {
                const url = URL.createObjectURL(this.files[0]);
                box.innerHTML = '<img src="' + url + '" style="max-height:120px; border-radius:8px; border:1px solid #eee;">';
            }
        });
    </script>
</body>
</html>
