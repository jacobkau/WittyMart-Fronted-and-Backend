<?php
// ============================================
// FINAL RETRY: Fix the last 3 insertions
// cart.php → remove_item
// admin/manage_products.php → add_product, update_product
// Run: https://your-site/apply_logging_patch_v3.php?dry=1   (preview)
//      https://your-site/apply_logging_patch_v3.php         (apply)
// ============================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

$ROOT   = __DIR__;
$dryRun = isset($_GET['dry']) && $_GET['dry'] === '1';

$backupDir = $ROOT . '/_patch_backups_' . date('Ymd_His');
if (!$dryRun) @mkdir($backupDir, 0755, true);

function insertAfterMarker($filePath, $marker, $block, $idTag, $dryRun, $backupDir) {
    if (!file_exists($filePath)) return ['skip', "File not found: " . basename($filePath)];
    $content = file_get_contents($filePath);
    if (strpos($content, "[PATCH:{$idTag}]") !== false) return ['skip', "Already patched ({$idTag})"];

    $pos = strpos($content, $marker);
    if ($pos === false) return ['fail', "Marker not found: " . substr($marker, 0, 70)];

    $lineEnd  = strpos($content, "\n", $pos);
    if ($lineEnd === false) return ['fail', "No newline after marker"];
    $insertAt = $lineEnd + 1;

    $newContent = substr($content, 0, $insertAt)
                . "\n    // [PATCH:{$idTag}] Added by apply_logging_patch_v3.php\n"
                . $block . "\n"
                . substr($content, $insertAt);

    if (!$dryRun) {
        $backup = $backupDir . '/' . str_replace(['/', '\\'], '_', str_replace($GLOBALS['ROOT'], '', $filePath));
        copy($filePath, $backup);
        if (file_put_contents($filePath, $newContent) === false) return ['fail', "Could not write"];
    }
    return ['ok', "Inserted after: " . substr($marker, 0, 55) . "..."];
}

function insertBeforeMarker($filePath, $marker, $block, $idTag, $dryRun, $backupDir) {
    if (!file_exists($filePath)) return ['skip', "File not found: " . basename($filePath)];
    $content = file_get_contents($filePath);
    if (strpos($content, "[PATCH:{$idTag}]") !== false) return ['skip', "Already patched ({$idTag})"];

    $pos = strpos($content, $marker);
    if ($pos === false) return ['fail', "Marker not found: " . substr($marker, 0, 70)];

    $lineStart = strrpos(substr($content, 0, $pos), "\n");
    $lineStart = ($lineStart === false) ? 0 : $lineStart + 1;

    $newContent = substr($content, 0, $lineStart)
                . "    // [PATCH:{$idTag}] Added by apply_logging_patch_v3.php\n"
                . $block . "\n\n"
                . substr($content, $lineStart);

    if (!$dryRun) {
        $backup = $backupDir . '/' . str_replace(['/', '\\'], '_', str_replace($GLOBALS['ROOT'], '', $filePath));
        copy($filePath, $backup);
        if (file_put_contents($filePath, $newContent) === false) return ['fail', "Could not write"];
    }
    return ['ok', "Inserted before: " . substr($marker, 0, 55) . "..."];
}

// ============================================
// PATCHES
// ============================================
$patches = [

    // ---------------------------------------------
    // cart.php — remove_item (using the exact success line)
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/cart.php',
        'marker'   => "\$response = ['success'=>true, 'cart_count'=>getCartCount()];\n                break;\n\n            case 'clear_cart':",
        'block'    => <<<'PHP'
                // [PATCH:remove_item_log] Log the removal
                if (function_exists('logActivity')) {
                    logActivity('remove_from_cart', "Cart item {$cart_id}", $user_id, $_SESSION['user_name'] ?? null);
                }
PHP,
        'id'       => 'remove_item_log',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // admin/manage_products.php — ADD (marker: the full success line including .=)
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/manage_products.php',
        'marker'   => "\$message = 'Product added successfully! ' . \$upload_message;",
        'block'    => <<<'PHP'
                        if (function_exists('logActivity')) {
                            logActivity('add_product', "Added product: {$name} (SKU: {$sku})", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                        }
PHP,
        'id'       => 'add_product_v3',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // admin/manage_products.php — UPDATE (marker: the full success line including .=)
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/manage_products.php',
        'marker'   => "\$message = 'Product updated successfully! ' . \$upload_message;",
        'block'    => <<<'PHP'
                        if (function_exists('logActivity')) {
                            logActivity('update_product', "Updated product: {$name} (ID: {$id})", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                        }
PHP,
        'id'       => 'update_product_v3',
        'position' => 'before',
    ],
];

// ============================================
// RUN
// ============================================
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>logActivity Patch v3</title>
<style>
    body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; max-width: 900px; margin: 30px auto; padding: 20px; background: #f8f9fa; line-height: 1.6; }
    h1 { color: #05573c; }
    .box { background: #fff; padding: 18px 22px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; }
    .ok   { color: #28a745; font-weight: 600; }
    .fail { color: #dc3545; font-weight: 600; }
    .skip { color: #6c757d; font-weight: 600; }
    code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: top; }
    th { background: #05573c; color: #fff; }
    .actions a.btn { display:inline-block; padding: 10px 20px; background:#05573c; color:#fff; border-radius:6px; text-decoration:none; font-weight:600; margin-right:8px; }
    .actions a.btn.secondary { background:#6c757d; }
    .actions a.btn.danger { background:#dc3545; }
</style>
</head><body>

<h1>🔧 logActivity Patch — v3 (Final)</h1>

<?php if ($dryRun): ?>
    <p style="background:#fff3cd; padding:14px 18px; border-radius:8px; color:#856404;"><strong>DRY-RUN</strong> — no files changed. <a href="?">Run for real</a></p>
<?php else: ?>
    <p style="background:#d4edda; padding:14px 18px; border-radius:8px; color:#155724;"><strong>LIVE</strong> — files patched. Backups: <code><?php echo htmlspecialchars(basename($backupDir)); ?></code> <a href="?dry=1">Preview only</a></p>
<?php endif; ?>

<div class="box">
<h2>Patch Results</h2>
<table><thead><tr><th>#</th><th>File</th><th>Tag</th><th>Status</th><th>Message</th></tr></thead><tbody>
<?php
$stats = ['ok' => 0, 'fail' => 0, 'skip' => 0];
$i = 0;
foreach ($patches as $p) {
    $i++;
    $rel = str_replace($ROOT, '', $p['file']);
    $fn  = $p['position'] === 'before' ? 'insertBeforeMarker' : 'insertAfterMarker';
    list($status, $msg) = $fn($p['file'], $p['marker'], $p['block'], $p['id'], $dryRun, $backupDir);
    $stats[$status] = ($stats[$status] ?? 0) + 1;
    $class = $status === 'ok' ? 'ok' : ($status === 'fail' ? 'fail' : 'skip');
    $icon  = $status === 'ok' ? '✅' : ($status === 'fail' ? '❌' : '⏭️');
    echo "<tr>";
    echo "<td>{$i}</td>";
    echo "<td><code>" . htmlspecialchars($rel) . "</code></td>";
    echo "<td><code>" . htmlspecialchars($p['id']) . "</code></td>";
    echo "<td class='{$class}'>{$icon} " . strtoupper($status) . "</td>";
    echo "<td>" . htmlspecialchars($msg) . "</td>";
    echo "</tr>";
}
?>
</tbody></table>
</div>

<div class="box">
<h2>Summary</h2>
<p><span class="ok">✅ Patched: </span><?php echo $stats['ok']; ?></p>
<p><span class="skip">⏭️ Skipped: </span><?php echo $stats['skip']; ?></p>
<p><span class="fail">❌ Failed: </span><?php echo $stats['fail']; ?></p>
</div>

<div class="box actions">
<h2>Next Steps</h2>
<ol>
    <li>Remove item from cart → check <code>activity_logs</code></li>
    <li>Add a product → check <code>activity_logs</code></li>
    <li>Update a product → check <code>activity_logs</code></li>
    <li><strong>Delete this file and the backup folder</strong></li>
</ol>
<?php if ($dryRun): ?>
    <a class="btn" href="?">▶ Apply for real</a>
    <a class="btn secondary" href="../index.php">Cancel</a>
<?php else: ?>
    <a class="btn secondary" href="../index.php">← Back to site</a>
    <a class="btn danger" href="?" onclick="return confirm('Run again?');">Run again</a>
<?php endif; ?>
</div>

</body></html>
