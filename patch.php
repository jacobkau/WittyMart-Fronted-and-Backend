<?php
require_once 'includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "════════════════════════════════════════════════════════════\n";
echo " COMPARE activity_log vs activity_logs\n";
echo "════════════════════════════════════════════════════════════\n\n";

// ────────────────────────────────────────────────────────────
// 1. TABLE A: activity_log (singular)
// ────────────────────────────────────────────────────────────
echo "┌─ TABLE A: activity_log (singular) ───────────────────┐\n";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
    echo "│ Row count: {$count}\n";

    $latest = $pdo->query("SELECT MAX(created_at) FROM activity_log")->fetchColumn();
    echo "│ Latest row: " . ($latest ?? 'NONE') . "\n";

    $oldest = $pdo->query("SELECT MIN(created_at) FROM activity_log")->fetchColumn();
    echo "│ Oldest row: " . ($oldest ?? 'NONE') . "\n";

    echo "│\n";
    echo "│ Distinct actions:\n";
    $stmt = $pdo->query("
        SELECT action, COUNT(*) AS n
        FROM activity_log
        GROUP BY action
        ORDER BY n DESC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        printf("│   %-25s %d\n", $r['action'], $r['n']);
    }

    echo "│\n";
    echo "│ Last 5 rows:\n";
    $stmt = $pdo->query("
        SELECT id, user_id, user_name, action, LEFT(COALESCE(details,''),40) AS d, created_at
        FROM activity_log
        ORDER BY id DESC
        LIMIT 5
    ");
    printf("│ %-5s %-8s %-15s %-20s %-25s %-20s\n", 'ID', 'UserID', 'UserName', 'Action', 'Details preview', 'Created');
    echo "├──────────────────────────────────────────────────────┤\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        printf("│ %-5s %-8s %-15s %-20s %-25s %-20s\n",
            $r['id'],
            $r['user_id'] ?? 'NULL',
            substr($r['user_name'] ?? 'NULL', 0, 14),
            substr($r['action'] ?? 'NULL', 0, 19),
            substr($r['d'] ?? '', 0, 24),
            $r['created_at'] ?? 'NULL'
        );
    }
} catch (PDOException $e) {
    echo "│ ERROR: " . $e->getMessage() . "\n";
}
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// 2. TABLE B: activity_logs (plural)
// ────────────────────────────────────────────────────────────
echo "┌─ TABLE B: activity_logs (plural) ────────────────────┐\n";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
    echo "│ Row count: {$count}\n";

    $latest = $pdo->query("SELECT MAX(created_at) FROM activity_logs")->fetchColumn();
    echo "│ Latest row: " . ($latest ?? 'NONE') . "\n";

    $oldest = $pdo->query("SELECT MIN(created_at) FROM activity_logs")->fetchColumn();
    echo "│ Oldest row: " . ($oldest ?? 'NONE') . "\n";

    echo "│\n";
    echo "│ Distinct actions:\n";
    $stmt = $pdo->query("
        SELECT action, COUNT(*) AS n
        FROM activity_logs
        GROUP BY action
        ORDER BY n DESC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        printf("│   %-25s %d\n", $r['action'], $r['n']);
    }

    echo "│\n";
    echo "│ Last 5 rows:\n";
    $stmt = $pdo->query("
        SELECT id, user_id, user_name, action, LEFT(COALESCE(description,''),40) AS d, created_at
        FROM activity_logs
        ORDER BY id DESC
        LIMIT 5
    ");
    printf("│ %-5s %-8s %-15s %-20s %-25s %-20s\n", 'ID', 'UserID', 'UserName', 'Action', 'Description preview', 'Created');
    echo "├──────────────────────────────────────────────────────┤\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        printf("│ %-5s %-8s %-15s %-20s %-25s %-20s\n",
            $r['id'],
            $r['user_id'] ?? 'NULL',
            substr($r['user_name'] ?? 'NULL', 0, 14),
            substr($r['action'] ?? 'NULL', 0, 19),
            substr($r['d'] ?? '', 0, 24),
            $r['created_at'] ?? 'NULL'
        );
    }
} catch (PDOException $e) {
    echo "│ ERROR: " . $e->getMessage() . "\n";
}
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// 3. WHICH TABLE IS config.php WRITING TO?
// ────────────────────────────────────────────────────────────
echo "┌─ WHICH TABLE DOES logActivity() TARGET? ─────────────┐\n";
$reflection = new ReflectionFunction('logActivity');
$file = $reflection->getFileName();
$startLine = $reflection->getStartLine();
$endLine = $reflection->getEndLine();

echo "│ logActivity() is defined in:\n";
echo "│   File: {$file}\n";
echo "│   Lines: {$startLine} - {$endLine}\n";

// Read the function body
$lines = file($file);
$body = '';
for ($i = $startLine - 1; $i < $endLine; $i++) {
    $body .= $lines[$i] ?? '';
}

echo "│\n";
echo "│ Function body references:\n";
if (strpos($body, 'INSERT INTO activity_log ') !== false) {
    echo "│   ⚠️  activity_log  (SINGULAR)  ← wrong table!\n";
}
if (strpos($body, 'INSERT INTO activity_logs ') !== false) {
    echo "│   ✅ activity_logs (PLURAL)    ← correct table\n";
}
if (strpos($body, ' details ') !== false) {
    echo "│   ⚠️  uses 'details' column\n";
}
if (strpos($body, ' description ') !== false) {
    echo "│   ✅ uses 'description' column\n";
}
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// 4. VERDICT
// ────────────────────────────────────────────────────────────
echo "┌─ VERDICT ────────────────────────────────────────────┐\n";

try {
    $cntA = (int)$pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
    $cntB = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

    echo "│ activity_log  (singular): {$cntA} rows\n";
    echo "│ activity_logs (plural):   {$cntB} rows\n";
    echo "│\n";

    if ($cntB >= $cntA) {
        echo "│ 👉 KEEP:    activity_logs (plural)   [more rows]\n";
        echo "│ 👉 DELETE:  activity_log  (singular)\n";
    } else {
        echo "│ 👉 KEEP:    activity_log  (singular) [more rows]\n";
        echo "│ 👉 DELETE:  activity_logs (plural)\n";
    }
} catch (PDOException $e) {
    echo "│ ERROR: " . $e->getMessage() . "\n";
}
echo "└──────────────────────────────────────────────────────┘\n\n";

// ────────────────────────────────────────────────────────────
// 5. CLEANUP SQL (commented, ready to run)
// ────────────────────────────────────────────────────────────
echo "┌─ CLEANUP SQL (copy into pgAdmin / Render shell) ─────┐\n";
echo "│\n";
echo "│ -- Verify config.php targets activity_logs (plural).\n";
echo "│ -- Then drop the unused table:\n";
echo "│\n";
echo "│ DROP TABLE IF EXISTS activity_log;\n";
echo "│\n";
echo "│ -- Optional: verify afterward\n";
echo "│ SELECT COUNT(*) FROM activity_logs;\n";
echo "└──────────────────────────────────────────────────────┘\n\n";

echo "════════════════════════════════════════════════════════════\n";
echo " DONE. Share this output before deleting anything.\n";
echo "════════════════════════════════════════════════════════════\n";
