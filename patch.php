<?php
require_once 'includes/config.php';

header('Content-Type: text/plain');

echo "=== DIAGNOSTIC ===\n\n";

// 1. Is PDO loaded?
echo "1. PDO set: " . (isset($pdo) ? 'YES' : 'NO') . "\n";

// 2. Is logActivity defined?
echo "2. logActivity defined: " . (function_exists('logActivity') ? 'YES' : 'NO') . "\n\n";

// 3. Column check
echo "3. activity_logs columns:\n";
try {
    $stmt = $pdo->query("
        SELECT column_name, data_type, character_maximum_length
        FROM information_schema.columns
        WHERE table_name = 'activity_logs'
        ORDER BY ordinal_position
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $len = $col['character_maximum_length'] ? "({$col['character_maximum_length']})" : "";
        echo "   - {$col['column_name']} {$col['data_type']}{$len}\n";
    }
} catch (PDOException $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

// 4. Try manual insert
echo "\n4. Manual INSERT test:\n";
try {
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, user_name, action, description, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $result = $stmt->execute([999, 'DiagUser', 'diag_test', 'Diagnostic test row', '127.0.0.1', 'DiagAgent']);
    echo "   Execute: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   New ID: " . $pdo->lastInsertId() . "\n";
} catch (PDOException $e) {
    echo "   FAILED: " . $e->getMessage() . "\n";
}

// 5. Try logActivity
echo "\n5. logActivity() test:\n";
if (function_exists('logActivity')) {
    $result = logActivity('logactivity_test', 'Testing logActivity function', 999, 'DiagUser');
    echo "   Returned: " . var_export($result, true) . "\n";
} else {
    echo "   Function NOT defined!\n";
}

// 6. Count rows
echo "\n6. Total rows in activity_logs:\n";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
    echo "   Count: {$count}\n";
} catch (PDOException $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
