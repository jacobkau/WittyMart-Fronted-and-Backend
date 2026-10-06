<?php
require_once 'includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "════════════════════════════════════════════════════════════\n";
echo " WITTYMART ACTIVITY LOG DIAGNOSTIC\n";
echo "════════════════════════════════════════════════════════════\n\n";

// ────────────────────────────────────────────────────────────
// SECTION 1: ENVIRONMENT
// ────────────────────────────────────────────────────────────
echo "┌─ 1. ENVIRONMENT ─────────────────────────────────────┐\n";
echo "│ PDO set:               " . (isset($pdo) ? 'YES' : 'NO') . "\n";
echo "│ logActivity defined:   " . (function_exists('logActivity') ? 'YES' : 'NO') . "\n";
echo "│ Session user_id:       " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "│ Session user_name:     " . ($_SESSION['user_name'] ?? 'NOT SET') . "\n";
echo "│ Session is_admin:      " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'true' : 'false') : 'NOT SET') . "\n";
echo "│ Session user_role:     " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n";
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// SECTION 2: TABLE SCHEMA
// ────────────────────────────────────────────────────────────
echo "┌─ 2. TABLE SCHEMA ────────────────────────────────────┐\n";
try {
    $stmt = $pdo->query("
        SELECT column_name, data_type, character_maximum_length, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'activity_logs'
        ORDER BY ordinal_position
    ");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        $len = $c['character_maximum_length'] ? "({$c['character_maximum_length']})" : "";
        printf("│ %-20s %-25s %s\n",
            $c['column_name'],
            $c['data_type'] . $len,
            $c['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL'
        );
    }
} catch (PDOException $e) {
    echo "│ ERROR: " . $e->getMessage() . "\n";
}
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// SECTION 3: CURRENT ROWS (this is what we need!)
// ────────────────────────────────────────────────────────────
echo "┌─ 3. ALL EXISTING activity_logs ROWS ────────────────┐\n";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
    echo "│ Total rows: {$count}\n";
    echo "├──────────────────────────────────────────────────────┤\n";

    if ($count > 0) {
        $stmt = $pdo->query("
            SELECT
                id,
                user_id,
                user_name,
                action,
                LEFT(COALESCE(description, ''), 40) AS desc_preview,
                ip_address,
                created_at
            FROM activity_logs
            ORDER BY id DESC
            LIMIT 30
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        printf("│ %-5s %-8s %-15s %-20s %-25s %-20s\n",
            'ID', 'UserID', 'UserName', 'Action', 'Description', 'Created');
        echo "├──────────────────────────────────────────────────────┤\n";

        foreach ($rows as $r) {
            printf("│ %-5s %-8s %-15s %-20s %-25s %-20s\n",
                $r['id'],
                $r['user_id'] ?? 'NULL',
                substr($r['user_name'] ?? 'NULL', 0, 14),
                substr($r['action'] ?? 'NULL', 0, 19),
                substr($r['desc_preview'] ?? '', 0, 24),
                $r['created_at'] ?? 'NULL'
            );
        }
    } else {
        echo "│ (no rows)\n";
    }
} catch (PDOException $e) {
    echo "│ ERROR: " . $e->getMessage() . "\n";
}
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// SECTION 4: ACTION COUNTS
// ────────────────────────────────────────────────────────────
echo "┌─ 4. ACTION COUNTS ───────────────────────────────────┐\n";
try {
    $stmt = $pdo->query("
        SELECT action, COUNT(*) AS total, MAX(created_at) AS latest
        FROM activity_logs
        GROUP BY action
        ORDER BY total DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "│ (no actions yet)\n";
    } else {
        printf("│ %-30s %-8s %-20s\n", 'Action', 'Count', 'Latest');
        echo "├──────────────────────────────────────────────────────┤\n";
        foreach ($rows as $r) {
            printf("│ %-30s %-8s %-20s\n",
                substr($r['action'], 0, 29),
                $r['total'],
                $r['latest']
            );
        }
    }
} catch (PDOException $e) {
    echo "│ ERROR: " . $e->getMessage() . "\n";
}
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// SECTION 5: INSERT TEST
// ────────────────────────────────────────────────────────────
$before = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

echo "┌─ 5. INSERT TEST ─────────────────────────────────────┐\n";
echo "│ Rows before test: {$before}\n";

echo "│\n";
echo "│ 5a. logActivity() with explicit user_id...\n";
$r1 = logActivity('diag_test', 'Testing explicit user_id', 999, 'DiagUser');
echo "│     Result: " . var_export($r1, true) . "\n";

echo "│\n";
echo "│ 5b. logActivity() with session user_id (auto)...\n";
$r2 = logActivity('diag_test_session', 'Testing session auto user');
echo "│     Result: " . var_export($r2, true) . "\n";

echo "│\n";
echo "│ 5c. logActivity() with long action (105 chars)...\n";
$longAction = str_repeat('a', 105);
$r3 = logActivity($longAction, 'Testing long action name');
echo "│     Result: " . var_export($r3, true) . " (expected: false — exceeds varchar(100))\n";

echo "│\n";
echo "│ 5d. logActivity() with emoji/unicode in description...\n";
$r4 = logActivity('diag_unicode', 'Testing émojis 🎉 and ünïcödé');
echo "│     Result: " . var_export($r4, true) . "\n";

$after = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
echo "│\n";
echo "│ Rows after test: {$after}\n";
echo "│ Net new: " . ($after - $before) . "\n";
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// SECTION 6: RECENT 5 ROWS (to see what just got inserted)
// ────────────────────────────────────────────────────────────
echo "┌─ 6. RECENT 5 ROWS AFTER INSERT TEST ─────────────────┐\n";
try {
    $stmt = $pdo->query("
        SELECT id, user_id, user_name, action, LEFT(description, 40) AS desc_preview, created_at
        FROM activity_logs
        ORDER BY id DESC
        LIMIT 5
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        printf("│ #%-4s user=%-6s action=%-22s %s\n",
            $r['id'],
            $r['user_id'] ?? 'NULL',
            substr($r['action'], 0, 21),
            substr($r['desc_preview'] ?? '', 0, 40)
        );
    }
} catch (PDOException $e) {
    echo "│ ERROR: " . $e->getMessage() . "\n";
}
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// SECTION 7: OTHER TABLES — check what's actually logged in app
// ────────────────────────────────────────────────────────────
echo "┌─ 7. DATA IN RELATED TABLES ──────────────────────────┐\n";
$tables = ['users', 'orders', 'cart', 'wishlist', 'products', 'newsletter_subscribers', 'contact_us'];
foreach ($tables as $t) {
    try {
        $n = $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        printf("│ %-25s %d rows\n", $t, $n);
    } catch (PDOException $e) {
        printf("│ %-25s (error)\n", $t);
    }
}
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// SECTION 8: USERS TABLE — see if user_id values are valid
// ────────────────────────────────────────────────────────────
echo "┌─ 8. USERS (to cross-check activity_logs.user_id) ────┐\n";
try {
    $stmt = $pdo->query("SELECT id, username, name, email, role FROM users ORDER BY id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    printf("│ %-5s %-15s %-20s %-25s %-10s\n", 'ID', 'Username', 'Name', 'Email', 'Role');
    echo "├──────────────────────────────────────────────────────┤\n";
    foreach ($rows as $r) {
        printf("│ %-5s %-15s %-20s %-25s %-10s\n",
            $r['id'],
            substr($r['username'] ?? '', 0, 14),
            substr($r['name'] ?? '', 0, 19),
            substr($r['email'] ?? '', 0, 24),
            substr($r['role'] ?? '', 0, 9)
        );
    }
} catch (PDOException $e) {
    echo "│ ERROR: " . $e->getMessage() . "\n";
}
echo "└──────────────────────────────────────────────────────┘\n\n";

echo "════════════════════════════════════════════════════════════\n";
echo " DONE. Copy this entire output and share it.\n";
echo "════════════════════════════════════════════════════════════\n";
