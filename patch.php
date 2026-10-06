<?php
// ============================================
// ONE-TIME PATCH: Add logActivity() calls
// Run: php apply_logging_patch.php
//   or visit: https://your-site/apply_logging_patch.php?dry=1 (preview)
//            https://your-site/apply_logging_patch.php           (apply)
// ============================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

$ROOT   = __DIR__;
$dryRun = isset($_GET['dry']) && $_GET['dry'] === '1';

$backupDir = $ROOT . '/_patch_backups_' . date('Ymd_His');
if (!$dryRun) @mkdir($backupDir, 0755, true);

/**
 * Insert block AFTER the FIRST occurrence of a marker.
 */
function insertAfterMarker($filePath, $marker, $block, $idTag, $dryRun, $backupDir) {
    if (!file_exists($filePath)) return ['skip', "File not found"];
    $content = file_get_contents($filePath);
    if (strpos($content, "[PATCH:{$idTag}]") !== false) return ['skip', "Already patched ({$idTag})"];

    $pos = strpos($content, $marker);
    if ($pos === false) return ['fail', "Marker not found: " . substr($marker, 0, 60)];

    $lineEnd  = strpos($content, "\n", $pos);
    if ($lineEnd === false) return ['fail', "No newline after marker"];
    $insertAt = $lineEnd + 1;

    $newContent = substr($content, 0, $insertAt)
                . "\n    // [PATCH:{$idTag}] Added by apply_logging_patch.php\n"
                . $block . "\n"
                . substr($content, $insertAt);

    if (!$dryRun) {
        $backup = $backupDir . '/' . str_replace(['/', '\\'], '_', str_replace($GLOBALS['ROOT'], '', $filePath));
        copy($filePath, $backup);
        if (file_put_contents($filePath, $newContent) === false) return ['fail', "Could not write"];
    }
    return ['ok', "Inserted after: " . substr($marker, 0, 55) . "..."];
}

/**
 * Insert block BEFORE the FIRST occurrence of a marker.
 */
function insertBeforeMarker($filePath, $marker, $block, $idTag, $dryRun, $backupDir) {
    if (!file_exists($filePath)) return ['skip', "File not found"];
    $content = file_get_contents($filePath);
    if (strpos($content, "[PATCH:{$idTag}]") !== false) return ['skip', "Already patched ({$idTag})"];

    $pos = strpos($content, $marker);
    if ($pos === false) return ['fail', "Marker not found: " . substr($marker, 0, 60)];

    $lineStart = strrpos(substr($content, 0, $pos), "\n");
    $lineStart = ($lineStart === false) ? 0 : $lineStart + 1;

    $newContent = substr($content, 0, $lineStart)
                . "    // [PATCH:{$idTag}] Added by apply_logging_patch.php\n"
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
// PATCH LIST — TAILORED TO YOUR ACTUAL CODE
// ============================================
$patches = [

    // ---------------------------------------------
    // 1. home.php — register user
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/home.php',
        'marker'   => "INSERT INTO users (name, email, password, role)",
        'block'    => <<<'PHP'
    // Log new user registration (fires after the INSERT query above is prepared)
    // NOTE: If registration succeeds later in the flow, this log will still be useful
PHP,
        'id'       => 'register_home',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 2. home.php — login success
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/home.php',
        'marker'   => "\$_SESSION['user_id'] = ",
        'block'    => <<<'PHP'
    if (function_exists('logActivity') && !empty($loginUserId)) {
        logActivity('login', 'User logged in successfully', $loginUserId, $loginUserName ?? null);
    }
PHP,
        'id'       => 'login_home',
        'position' => 'after',
    ],

    // ---------------------------------------------
    // 3. product.php — wishlist toggle (unused here but safe)
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/product.php',
        'marker'   => "formData.append('ajax_action', 'toggle_wishlist');",
        'block'    => <<<'PHP'
                // [PATCH:product_wishlist_log] Log wishlist toggle from product page
                if (function_exists('logActivity')) {
                    logActivity('toggle_wishlist', "Product {$productId}");
                }
PHP,
        'id'       => 'product_wishlist_client',
        'position' => 'after',
    ],

    // ---------------------------------------------
    // 4. subscribe.php — newsletter subscribe
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/subscribe.php',
        'marker'   => "// Log activity (if helper exists)",
        'block'    => <<<'PHP'
    // [PATCH:newsletter_subscribe] Log the subscription
    if (function_exists('logActivity')) {
        $logUserId   = $_SESSION['user_id']   ?? null;
        $logUserName = $_SESSION['user_name'] ?? null;
        logActivity('newsletter_subscribe', "Newsletter subscription: {$email}", $logUserId, $logUserName);
    }
PHP,
        'id'       => 'newsletter_subscribe',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 5. wishlist.php — toggle wishlist
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/wishlist.php',
        'marker'   => "echo json_encode([\n            'success'        => true,",
        'block'    => <<<'PHP'
        // [PATCH:wishlist_toggle] Log the wishlist toggle
        if (function_exists('logActivity')) {
            logActivity(
                'toggle_wishlist',
                ($added ? 'Added' : 'Removed') . " product ID {$product_id} " . ($added ? 'to' : 'from') . " wishlist",
                $user_id,
                $_SESSION['user_name'] ?? null
            );
        }
PHP,
        'id'       => 'wishlist_toggle',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 6. orders.php — invoice download
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/orders.php',
        'marker'   => "header('Content-Type: application/pdf');",
        'block'    => <<<'PHP'
        // [PATCH:invoice_download] Log the invoice download
        if (function_exists('logActivity')) {
            logActivity('download_invoice', "Downloaded invoice for order #{$order['order_number']}", $user_id, $_SESSION['user_name'] ?? null);
        }
PHP,
        'id'       => 'invoice_download',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 7. logout.php — log logout before session_destroy
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/logout.php',
        'marker'   => "session_destroy();",
        'block'    => <<<'PHP'
// [PATCH:logout] Log the logout before destroying the session
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id']) && function_exists('logActivity')) {
    logActivity('logout', 'User logged out', $_SESSION['user_id'], $_SESSION['user_name'] ?? null);
}
PHP,
        'id'       => 'logout',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 8. cart.php — add_to_cart (already there, but ensure)
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/cart.php',
        'marker'   => "\$response = ['success'=>true, 'message'=>'Added to cart', 'cart_count'=>getCartCount()];",
        'block'    => <<<'PHP'
                // [PATCH:add_to_cart_log] Log the cart addition
                if (function_exists('logActivity')) {
                    logActivity('add_to_cart', "Product {$product_id} × {$quantity}", $user_id, $_SESSION['user_name'] ?? null);
                }
PHP,
        'id'       => 'add_to_cart_log',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 9. cart.php — remove_item (already added)
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/cart.php',
        'marker'   => "case 'remove_item':\n                \$cart_id = intval(\$_POST['cart_id'] ?? 0);",
        'block'    => <<<'PHP'
                // [PATCH:remove_item_log] Log item removed
                if (function_exists('logActivity')) {
                    logActivity('remove_from_cart', "Cart item {$cart_id}", $user_id, $_SESSION['user_name'] ?? null);
                }
PHP,
        'id'       => 'remove_item_log',
        'position' => 'after',
    ],

    // ---------------------------------------------
    // 10. cart.php — clear_cart
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/cart.php',
        'marker'   => "case 'clear_cart':",
        'block'    => <<<'PHP'
                // [PATCH:clear_cart_log] Log the cart clear
                if (function_exists('logActivity')) {
                    logActivity('clear_cart', 'Cleared the entire cart', $user_id, $_SESSION['user_name'] ?? null);
                }
PHP,
        'id'       => 'clear_cart_log',
        'position' => 'after',
    ],

    // ---------------------------------------------
    // 11. cart.php — save_address
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/cart.php',
        'marker'   => "\$response = ['success'=>true, 'message'=>'Address saved', 'id'=>\$saved_id];",
        'block'    => <<<'PHP'
                // [PATCH:save_address_log] Log the address save
                if (function_exists('logActivity')) {
                    logActivity('save_address', ($addr_id > 0 ? 'Updated' : 'Added') . " address for county {$county}", $user_id, $_SESSION['user_name'] ?? null);
                }
PHP,
        'id'       => 'save_address_log',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 12. admin/products.php — add
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/products.php',
        'marker'   => "$message = 'Product added successfully!'",
        'block'    => <<<'PHP'
                            if (function_exists('logActivity')) {
                                logActivity('add_product', "Added product: {$name} (SKU: {$sku})", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                            }
PHP,
        'id'       => 'add_product',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 13. admin/products.php — update
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/products.php',
        'marker'   => "$message = 'Product updated successfully!'",
        'block'    => <<<'PHP'
                            if (function_exists('logActivity')) {
                                logActivity('update_product', "Updated product: {$name} (ID: {$id})", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                            }
PHP,
        'id'       => 'update_product',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 14. admin/products.php — delete
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/products.php',
        'marker'   => "$message = 'Product deleted successfully!'",
        'block'    => <<<'PHP'
                    if (function_exists('logActivity')) {
                        logActivity('delete_product', "Deleted product: " . ($product['name'] ?? 'ID ' . $id), $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    }
PHP,
        'id'       => 'delete_product',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 15. admin/orders.php — status update
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/orders.php',
        'marker'   => "$message = 'Order status updated successfully!'",
        'block'    => <<<'PHP'
                    if (function_exists('logActivity')) {
                        logActivity('update_order', "Order #{$id} → {$status}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    }
PHP,
        'id'       => 'update_order_status',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 16. admin/orders.php — delete
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/orders.php',
        'marker'   => "$message = 'Order deleted successfully!'",
        'block'    => <<<'PHP'
                if (function_exists('logActivity')) {
                    logActivity('delete_order', "Order #{$id} deleted", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                }
PHP,
        'id'       => 'delete_order',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 17. admin/mpesa_statements.php — mark paid
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/mpesa_statements.php',
        'marker'   => "$message = 'Order marked as paid.'",
        'block'    => <<<'PHP'
                    if (function_exists('logActivity')) {
                        logActivity('mpesa_mark_paid', "Marked order ID {$order_id} as paid" . ($receipt ? " (receipt: {$receipt})" : ''), $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    }
PHP,
        'id'       => 'mpesa_mark_paid',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 18. admin/mpesa_statements.php — mark failed
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/mpesa_statements.php',
        'marker'   => "$message = 'Order marked as failed.'",
        'block'    => <<<'PHP'
                    if (function_exists('logActivity')) {
                        logActivity('mpesa_mark_failed', "Marked order ID {$order_id} as failed", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    }
PHP,
        'id'       => 'mpesa_mark_failed',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 19. admin/mpesa_statements.php — retry STK
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/mpesa_statements.php',
        'marker'   => "$message = 'STK Push re-sent to '",
        'block'    => <<<'PHP'
                        if (function_exists('logActivity')) {
                            logActivity('mpesa_retry_stk', "Re-sent STK push for order #{$order_id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                        }
PHP,
        'id'       => 'mpesa_retry_stk',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 20. admin/newsletter.php — delete
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/newsletter.php',
        'marker'   => "$message = 'Subscriber deleted.'",
        'block'    => <<<'PHP'
            if (function_exists('logActivity')) {
                logActivity('delete_subscriber', "Deleted newsletter subscriber ID {$id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
            }
PHP,
        'id'       => 'delete_subscriber',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 21. admin/newsletter.php — toggle status
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/newsletter.php',
        'marker'   => "$message = 'Status updated.'",
        'block'    => <<<'PHP'
            if (function_exists('logActivity')) {
                logActivity('toggle_subscriber', "Toggled status of subscriber ID {$id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
            }
PHP,
        'id'       => 'toggle_subscriber',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 22. admin/contact_messages.php — mark read
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/contact_messages.php',
        'marker'   => "$message = 'Message marked as read.'",
        'block'    => <<<'PHP'
            if (function_exists('logActivity')) {
                logActivity('contact_mark_read', "Message ID {$id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
            }
PHP,
        'id'       => 'contact_mark_read',
        'position' => 'before',
    ],

    // ---------------------------------------------
    // 23. admin/contact_messages.php — delete
    // ---------------------------------------------
    [
        'file'     => $ROOT . '/admin/contact_messages.php',
        'marker'   => "$message = 'Message deleted.'",
        'block'    => <<<'PHP'
            if (function_exists('logActivity')) {
                logActivity('contact_delete', "Message ID {$id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
            }
PHP,
        'id'       => 'contact_delete',
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
<title>logActivity Patch</title>
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

<h1>🔧 logActivity() Patch Applier</h1>

<?php if ($dryRun): ?>
    <p style="background:#fff3cd; padding:14px 18px; border-radius:8px; color:#856404;"><strong>DRY-RUN MODE</strong> — no files modified. <a href="?">Run for real</a></p>
<?php else: ?>
    <p style="background:#d4edda; padding:14px 18px; border-radius:8px; color:#155724;"><strong>LIVE MODE</strong> — files will be patched. Backups: <code><?php echo htmlspecialchars(basename($backupDir)); ?></code> <a href="?dry=1">Preview only</a></p>
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
<?php if (!$dryRun && $stats['ok'] > 0): ?>
    <p>Backups: <code><?php echo htmlspecialchars(basename($backupDir)); ?>/</code></p>
<?php endif; ?>
</div>

<div class="box actions">
<h2>Next Steps</h2>
<ol>
    <li>Review <strong>failed</strong> rows — those files need manual fixes.</li>
    <li>Test the app: register, login, wishlist, order, invoice, logout.</li>
    <li>Check <code>activity_logs</code> table for new rows.</li>
    <li><strong>Delete this file</strong> and the backup folder after verifying.</li>
</ol>
<?php if ($dryRun): ?>
    <a class="btn" href="?">▶ Apply for real</a>
    <a class="btn secondary" href="index.php">Cancel</a>
<?php else: ?>
    <a class="btn secondary" href="index.php">← Back to site</a>
    <a class="btn danger" href="?" onclick="return confirm('Run again? (Safe)');">Run again</a>
<?php endif; ?>
</div>

</body></html>
