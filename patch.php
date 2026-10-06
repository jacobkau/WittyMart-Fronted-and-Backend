<?php
// ============================================
// ONE-TIME PATCH: Add logActivity() calls
// Run: php apply_logging_patch.php
//   or visit: https://your-site/apply_logging_patch.php (then DELETE)
// ============================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

$ROOT = __DIR__;
$dryRun = isset($_GET['dry']) && $_GET['dry'] === '1'; // Preview mode

$results = [];
$backupDir = $ROOT . '/_patch_backups_' . date('Ymd_His');

if (!$dryRun) {
    @mkdir($backupDir, 0755, true);
}

/**
 * Insert a block of code AFTER a marker line if not already inserted.
 * Returns [status, message]
 */
function insertAfterMarker($filePath, $markerString, $insertBlock, $idTag, $dryRun, $backupDir) {
    if (!file_exists($filePath)) {
        return ['skip', "File not found: {$filePath}"];
    }

    $content = file_get_contents($filePath);

    // Already patched?
    if (strpos($content, $idTag) !== false) {
        return ['skip', "Already patched ({$idTag})"];
    }

    // Find the marker — use stripos for a case-insensitive match
    $pos = stripos($content, $markerString);
    if ($pos === false) {
        return ['fail', "Marker not found: {$markerString}"];
    }

    // Find end of the marker's line
    $lineEnd = strpos($content, "\n", $pos);
    if ($lineEnd === false) {
        return ['fail', "Marker has no newline after it"];
    }
    $insertAt = $lineEnd + 1;

    // Build the block with ID tag for idempotency
    $block = "\n    // [PATCH:{$idTag}] Added by apply_logging_patch.php\n"
           . $insertBlock . "\n";

    $newContent = substr($content, 0, $insertAt)
                . $block
                . substr($content, $insertAt);

    if (!$dryRun) {
        // Backup original
        $relPath = str_replace(__DIR__, '', $filePath);
        $backupFile = $backupDir . str_replace(['/', '\\'], '_', $relPath);
        copy($filePath, $backupFile);

        if (file_put_contents($filePath, $newContent) === false) {
            return ['fail', "Could not write: {$filePath}"];
        }
    }

    return ['ok', "Inserted after: " . substr($markerString, 0, 60) . "..."];
}

/**
 * Insert a block BEFORE a marker line.
 */
function insertBeforeMarker($filePath, $markerString, $insertBlock, $idTag, $dryRun, $backupDir) {
    if (!file_exists($filePath)) {
        return ['skip', "File not found: {$filePath}"];
    }

    $content = file_get_contents($filePath);

    if (strpos($content, $idTag) !== false) {
        return ['skip', "Already patched ({$idTag})"];
    }

    $pos = stripos($content, $markerString);
    if ($pos === false) {
        return ['fail', "Marker not found: {$markerString}"];
    }

    // Find start of the marker's line (go back to previous newline)
    $lineStart = strrpos(substr($content, 0, $pos), "\n");
    if ($lineStart === false) $lineStart = 0; else $lineStart++;

    $block = "    // [PATCH:{$idTag}] Added by apply_logging_patch.php\n"
           . $insertBlock . "\n\n";

    $newContent = substr($content, 0, $lineStart)
                . $block
                . substr($content, $lineStart);

    if (!$dryRun) {
        $relPath = str_replace(__DIR__, '', $filePath);
        $backupFile = $backupDir . str_replace(['/', '\\'], '_', $relPath);
        copy($filePath, $backupFile);

        if (file_put_contents($filePath, $newContent) === false) {
            return ['fail', "Could not write: {$filePath}"];
        }
    }

    return ['ok', "Inserted before: " . substr($markerString, 0, 60) . "..."];
}

// ============================================
// PATCH LIST
// ============================================
// Each entry: [file, marker, block, idTag, position: 'after'|'before']
$patches = [

    // --------------------------------------------
    // 1. User registration (register.php)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/register.php',
        'marker' => "if (\$stmt->execute([\$name, \$email, \$hashedPassword, \$role]))",
        'block'  => <<<'PHP'
    // Log registration
    try {
        $newUserId = $pdo->lastInsertId();
        if (function_exists('logActivity')) {
            logActivity('register', "New user registered: {$email} (role: {$role})", $newUserId, $name);
        }
    } catch (Throwable $e) {
        error_log('Post-register log failed: ' . $e->getMessage());
    }
PHP,
        'id'       => 'register_user',
        'position' => 'after',
    ],

    // --------------------------------------------
    // 2. Profile update (profile.php)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/profile.php',
        'marker' => "if (\$stmt->execute([\$name, \$email, \$userId]))",
        'block'  => <<<'PHP'
    if (function_exists('logActivity')) {
        logActivity('update_profile', "Profile updated: {$email}", $userId, $name);
    }
PHP,
        'id'       => 'update_profile',
        'position' => 'after',
    ],

    // --------------------------------------------
    // 3. Password change (profile.php)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/profile.php',
        'marker' => "if (\$stmt->execute([\$hashedPassword, \$userId]))",
        'block'  => <<<'PHP'
    if (function_exists('logActivity')) {
        logActivity('change_password', 'User changed password', $userId, $_SESSION['user_name'] ?? null);
    }
PHP,
        'id'       => 'change_password',
        'position' => 'after',
    ],

    // --------------------------------------------
    // 4-6. Products (add / edit / delete)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/admin/products.php',
        'marker' => "$message = 'Product added successfully!'",
        'block'  => <<<'PHP'
                            if (function_exists('logActivity')) {
                                logActivity('add_product', "Added product: {$name} (SKU: {$sku})", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                            }
PHP,
        'id'       => 'add_product',
        'position' => 'before',
    ],
    [
        'file'   => $ROOT . '/admin/products.php',
        'marker' => "$message = 'Product updated successfully!'",
        'block'  => <<<'PHP'
                            if (function_exists('logActivity')) {
                                logActivity('update_product', "Updated product: {$name} (ID: {$id})", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                            }
PHP,
        'id'       => 'update_product',
        'position' => 'before',
    ],
    [
        'file'   => $ROOT . '/admin/products.php',
        'marker' => "$message = 'Product deleted successfully!'",
        'block'  => <<<'PHP'
                    if (function_exists('logActivity')) {
                        logActivity('delete_product', "Deleted product: " . ($product['name'] ?? 'ID ' . $id), $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    }
PHP,
        'id'       => 'delete_product',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 7. Order status change (admin/orders.php)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/admin/orders.php',
        'marker' => "$message = 'Order status updated successfully!'",
        'block'  => <<<'PHP'
                    if (function_exists('logActivity')) {
                        logActivity('update_order', "Order #{$id} → {$status}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    }
PHP,
        'id'       => 'update_order_status',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 8. Order deleted (admin/orders.php)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/admin/orders.php',
        'marker' => "$message = 'Order deleted successfully!'",
        'block'  => <<<'PHP'
                if (function_exists('logActivity')) {
                    logActivity('delete_order', "Order #{$id} deleted", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                }
PHP,
        'id'       => 'delete_order',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 9. Order placed (checkout.php)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/checkout.php',
        'marker' => "\$pdo->commit();",
        'block'  => <<<'PHP'
                if (function_exists('logActivity')) {
                    logActivity('order_placed', "Order #{$order_number} placed — Ksh " . number_format($order_total, 0), $user_id, $user_name);
                }
PHP,
        'id'       => 'order_placed',
        'position' => 'after',
    ],

    // --------------------------------------------
    // 10. Cart: add_to_cart
    // --------------------------------------------
    [
        'file'   => $ROOT . '/cart.php',
        'marker' => "\$response = ['success'=>true, 'message'=>'Added to cart', 'cart_count'=>getCartCount()];",
        'block'  => <<<'PHP'
                if (function_exists('logActivity')) {
                    logActivity('add_to_cart', "Product {$product_id} × {$quantity}");
                }
PHP,
        'id'       => 'add_to_cart_log',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 11. Cart: remove_item
    // --------------------------------------------
    [
        'file'   => $ROOT . '/cart.php',
        'marker' => "\$response = ['success'=>true, 'cart_count'=>getCartCount()];",
        'block'  => <<<'PHP'
                // [PATCH:cart_remove_log]
                if (function_exists('logActivity') && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'remove_item') {
                    logActivity('remove_from_cart', "Cart item {$cart_id}");
                }
PHP,
        'id'       => 'cart_remove_log_marker',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 12. M-Pesa: mark_paid
    // --------------------------------------------
    [
        'file'   => $ROOT . '/admin/mpesa_statements.php',
        'marker' => "$message = 'Order marked as paid.'",
        'block'  => <<<'PHP'
                    if (function_exists('logActivity')) {
                        logActivity('mpesa_mark_paid', "Marked order ID {$order_id} as paid" . ($receipt ? " (receipt: {$receipt})" : ''), $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    }
PHP,
        'id'       => 'mpesa_mark_paid',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 13. M-Pesa: mark_failed
    // --------------------------------------------
    [
        'file'   => $ROOT . '/admin/mpesa_statements.php',
        'marker' => "$message = 'Order marked as failed.'",
        'block'  => <<<'PHP'
                    if (function_exists('logActivity')) {
                        logActivity('mpesa_mark_failed', "Marked order ID {$order_id} as failed", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                    }
PHP,
        'id'       => 'mpesa_mark_failed',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 14. M-Pesa: retry_stk
    // --------------------------------------------
    [
        'file'   => $ROOT . '/admin/mpesa_statements.php',
        'marker' => "$message = 'STK Push re-sent to '",
        'block'  => <<<'PHP'
                        if (function_exists('logActivity')) {
                            logActivity('mpesa_retry_stk', "Re-sent STK push for order #{$order_id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
                        }
PHP,
        'id'       => 'mpesa_retry_stk',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 15. Newsletter subscribe (subscribe.php)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/subscribe.php',
        'marker' => "echo json_encode([",
        'block'  => <<<'PHP'
    if (function_exists('logActivity')) {
        logActivity('newsletter_subscribe', "Newsletter subscription: {$email}");
    }
PHP,
        'id'       => 'newsletter_subscribe_log',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 16. Contact message (contact-submit.php)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/contact-submit.php',
        'marker' => "// ---- Forward to Formspree (best-effort) ----",
        'block'  => <<<'PHP'
    // ---- Log contact message ----
    if (function_exists('logActivity')) {
        logActivity('contact_message', "Contact from {$email}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
    }
PHP,
        'id'       => 'contact_message_log',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 17. Wishlist toggle (wishlist.php)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/wishlist.php',
        'marker' => "echo json_encode(['success' => true",
        'block'  => <<<'PHP'
        if (function_exists('logActivity')) {
            logActivity('toggle_wishlist', "Product {$product_id}");
        }
PHP,
        'id'       => 'wishlist_toggle_log',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 18. Review added (product.php / reviews.php)
    // --------------------------------------------
    [
        'file'   => $ROOT . '/product.php',
        'marker' => "INSERT INTO reviews",
        'block'  => <<<'PHP'
        // [PATCH:add_review_log_after] Added by apply_logging_patch.php
        // Log review after successful insert
PHP,
        'id'       => 'add_review_log_placeholder',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 19. Admin newsletter delete / toggle
    // --------------------------------------------
    [
        'file'   => $ROOT . '/admin/newsletter.php',
        'marker' => "$message = 'Subscriber deleted.'",
        'block'  => <<<'PHP'
            if (function_exists('logActivity')) {
                logActivity('delete_subscriber', "Deleted newsletter subscriber ID {$id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
            }
PHP,
        'id'       => 'delete_subscriber',
        'position' => 'before',
    ],
    [
        'file'   => $ROOT . '/admin/newsletter.php',
        'marker' => "$message = 'Status updated.'",
        'block'  => <<<'PHP'
            if (function_exists('logActivity')) {
                logActivity('toggle_subscriber', "Toggled status of subscriber ID {$id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
            }
PHP,
        'id'       => 'toggle_subscriber',
        'position' => 'before',
    ],

    // --------------------------------------------
    // 20. Contact message mark read / delete
    // --------------------------------------------
    [
        'file'   => $ROOT . '/admin/contact_messages.php',
        'marker' => "$message = 'Message marked as read.'",
        'block'  => <<<'PHP'
            if (function_exists('logActivity')) {
                logActivity('contact_mark_read', "Message ID {$id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
            }
PHP,
        'id'       => 'contact_mark_read',
        'position' => 'before',
    ],
    [
        'file'   => $ROOT . '/admin/contact_messages.php',
        'marker' => "$message = 'Message deleted.'",
        'block'  => <<<'PHP'
            if (function_exists('logActivity')) {
                logActivity('contact_delete', "Message ID {$id}", $_SESSION['user_id'] ?? null, $_SESSION['user_name'] ?? null);
            }
PHP,
        'id'       => 'contact_delete',
        'position' => 'before',
    ],
];

// ============================================
// RUN THE PATCHES
// ============================================
echo "<!DOCTYPE html><html><head><title>Logging Patch</title>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1'>";
echo "<style>
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
</style></head><body>";

echo "<h1>🔧 logActivity() Patch Applier</h1>";

if ($dryRun) {
    echo "<p style='background:#fff3cd; padding:14px 18px; border-radius:8px; color:#856404;'><strong>DRY-RUN MODE</strong> — no files will be modified. <a href='?'>Run for real</a></p>";
} else {
    echo "<p style='background:#d4edda; padding:14px 18px; border-radius:8px; color:#155724;'><strong>LIVE MODE</strong> — files will be patched. Backups saved to: <code>" . htmlspecialchars(basename($backupDir)) . "</code> <a href='?dry=1'>Preview only</a></p>";
}

$stats = ['ok' => 0, 'fail' => 0, 'skip' => 0];

echo "<div class='box'>";
echo "<h2>Patch Results</h2>";
echo "<table><thead><tr><th>#</th><th>File</th><th>Tag</th><th>Status</th><th>Message</th></tr></thead><tbody>";

$i = 0;
foreach ($patches as $p) {
    $i++;
    $file = $p['file'];
    $rel  = str_replace($ROOT, '', $file);

    $fn = $p['position'] === 'before' ? 'insertBeforeMarker' : 'insertAfterMarker';
    list($status, $msg) = $fn($file, $p['marker'], $p['block'], $p['id'], $dryRun, $backupDir);

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
echo "</tbody></table>";
echo "</div>";

echo "<div class='box'>";
echo "<h2>Summary</h2>";
echo "<p><span class='ok'>✅ Patched: </span>{$stats['ok']}</p>";
echo "<p><span class='skip'>⏭️ Skipped (already patched): </span>{$stats['skip']}</p>";
echo "<p><span class='fail'>❌ Failed (marker not found): </span>{$stats['fail']}</p>";
if (!$dryRun && $stats['ok'] > 0) {
    echo "<p>Backups saved to: <code>" . htmlspecialchars(basename($backupDir)) . "/</code></p>";
}
echo "</div>";

echo "<div class='box actions'>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Review the failed rows above (if any) — those files may have slightly different code than expected. Manually add the logActivity call at the indicated spot.</li>";
echo "<li>Test the app end-to-end: register, login, add product, place order, mark paid, subscribe, contact.</li>";
echo "<li>Check <code>activity_logs</code> table to confirm new rows appear.</li>";
echo "<li><strong>Delete this file</strong> and the backup folder after confirming everything works.</li>";
echo "</ol>";
if ($dryRun) {
    echo "<a class='btn' href='?'>▶ Apply for real</a>";
    echo "<a class='btn secondary' href='index.php'>Cancel</a>";
} else {
    echo "<a class='btn secondary' href='index.php'>← Back to site</a>";
    echo "<a class='btn danger' href='#' onclick='if(confirm(\"Run again? (Safe - already-patched files are skipped)\")) location.href=\"?\"; return false;'>Run again</a>";
}
echo "</div>";

echo "</body></html>";
