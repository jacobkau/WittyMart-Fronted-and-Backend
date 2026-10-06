<?php
// ============================================
// WITTYMART DB INSTALLER — NEWSLETTER UPGRADE
// Adds missing columns to newsletter_subscribers
// Safe to run multiple times (idempotent).
// ============================================
require_once 'includes/config.php';

// Only allow admins — comment out requireAdmin() if you can't log in yet
if (function_exists('requireAdmin')) {
    requireAdmin();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>WittyMart DB Installer</title>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1'>";
echo "<style>
    body { font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; max-width: 900px; margin: 30px auto; padding: 20px; background: #f8f9fa; color: #333; line-height: 1.6; }
    h1 { color: #05573c; margin-bottom: 5px; }
    h2 { color: #333; margin-top: 35px; border-bottom: 2px solid #e0e0e0; padding-bottom: 8px; }
    h3 { color: #555; margin-top: 22px; font-size: 15px; }
    p { margin: 6px 0; }
    ul { margin: 8px 0; padding-left: 22px; }
    li { margin: 3px 0; }
    code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
    .ok { color: #28a745; font-weight: 600; }
    .warn { color: #fd7e14; font-weight: 600; }
    .err { color: #dc3545; font-weight: 600; }
    .box { background: #fff; padding: 18px 24px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .summary { background: #e8f5f0; border-left: 4px solid #05573c; padding: 16px 20px; border-radius: 6px; margin-top: 25px; }
    a.btn { display: inline-block; padding: 10px 22px; background: #05573c; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; margin: 6px 4px; }
    a.btn:hover { background: #03402c; }
    a.btn.secondary { background: #6c757d; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #fff; border-radius: 8px; overflow: hidden; }
    th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 14px; }
    th { background: #05573c; color: #fff; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
    td code { font-size: 13px; }
</style>";
echo "</head><body>";

echo "<h1>🛠️ WittyMart — Newsletter Table Upgrade</h1>";
echo "<p style='color:#666;'>Adds the missing columns to <code>newsletter_subscribers</code> so the footer form can save IP address, user agent, source, and subscribe timestamp.</p>";

$summary = [
    'created' => [],
    'skipped' => [],
    'errors'  => [],
];

// ============================================
// HELPER FUNCTIONS
// ============================================
if (!function_exists('tableExists')) {
    function tableExists($pdo, $table) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public' AND table_name = ?
        ");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('columnExists')) {
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = ? AND column_name = ?
        ");
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('columnType')) {
    function columnType($pdo, $table, $column) {
        $stmt = $pdo->prepare("
            SELECT data_type, character_maximum_length
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = ? AND column_name = ?
        ");
        $stmt->execute([$table, $column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return '—';
        return $row['data_type'] . ($row['character_maximum_length'] ? '(' . $row['character_maximum_length'] . ')' : '');
    }
}

// ============================================
// STEP 1: VERIFY TABLE EXISTS
// ============================================
echo "<div class='box'>";
echo "<h2>Step 1 — Verify table exists</h2>";

if (!tableExists($pdo, 'newsletter_subscribers')) {
    echo "<p class='err'>✗ Table <code>newsletter_subscribers</code> does not exist.</p>";
    echo "<p class='warn'>⚠ Creating it now with all needed columns...</p>";

    try {
        $pdo->exec("
            CREATE TABLE newsletter_subscribers (
                id SERIAL PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                status VARCHAR(20) DEFAULT 'active',
                ip_address VARCHAR(45),
                user_agent TEXT,
                source VARCHAR(50),
                subscribed_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "<p class='ok'>✓ Table <code>newsletter_subscribers</code> created</p>";
        $summary['created'][] = 'newsletter_subscribers (table)';
    } catch (PDOException $e) {
        echo "<p class='err'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
        $summary['errors'][] = 'newsletter_subscribers (table): ' . $e->getMessage();
    }
} else {
    echo "<p class='ok'>✓ Table <code>newsletter_subscribers</code> already exists</p>";
    $summary['skipped'][] = 'newsletter_subscribers (table)';
}

// ============================================
// STEP 2: ADD MISSING COLUMNS
// ============================================
echo "</div>";
echo "<div class='box'>";
echo "<h2>Step 2 — Add missing columns</h2>";

$columns = [
    'ip_address'    => "ALTER TABLE newsletter_subscribers ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45);",
    'user_agent'    => "ALTER TABLE newsletter_subscribers ADD COLUMN IF NOT EXISTS user_agent TEXT;",
    'source'        => "ALTER TABLE newsletter_subscribers ADD COLUMN IF NOT EXISTS source VARCHAR(50);",
    'subscribed_at' => "ALTER TABLE newsletter_subscribers ADD COLUMN IF NOT EXISTS subscribed_at TIMESTAMP;",
];

foreach ($columns as $col => $sql) {
    echo "<h3>• Column: <code>newsletter_subscribers.$col</code></h3>";

    if (columnExists($pdo, 'newsletter_subscribers', $col)) {
        echo "<p class='warn'>⚠ Already exists — skipped</p>";
        $summary['skipped'][] = "newsletter_subscribers.$col";
    } else {
        try {
            $pdo->exec($sql);
            echo "<p class='ok'>✓ Created</p>";
            $summary['created'][] = "newsletter_subscribers.$col";
        } catch (PDOException $e) {
            echo "<p class='err'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
            $summary['errors'][] = "newsletter_subscribers.$col: " . $e->getMessage();
        }
    }
}

echo "</div>";

// ============================================
// STEP 3: BACKFILL subscribed_at FROM created_at
// ============================================
echo "<div class='box'>";
echo "<h2>Step 3 — Backfill <code>subscribed_at</code></h2>";
echo "<p>For existing rows where <code>subscribed_at</code> is NULL, copy the value from <code>created_at</code>.</p>";

try {
    $stmt = $pdo->exec("
        UPDATE newsletter_subscribers
        SET subscribed_at = created_at
        WHERE subscribed_at IS NULL AND created_at IS NOT NULL
    ");
    if ($stmt > 0) {
        echo "<p class='ok'>✓ Backfilled {$stmt} row(s)</p>";
        $summary['created'][] = "{$stmt} backfilled subscribed_at value(s)";
    } else {
        echo "<p class='ok'>✓ No rows needed backfilling</p>";
    }
} catch (PDOException $e) {
    echo "<p class='err'>✗ Backfill error: " . htmlspecialchars($e->getMessage()) . "</p>";
    $summary['errors'][] = 'backfill: ' . $e->getMessage();
}

echo "</div>";

// ============================================
// STEP 4: VERIFY FINAL SCHEMA
// ============================================
echo "<div class='box'>";
echo "<h2>Step 4 — Verification</h2>";

if (tableExists($pdo, 'newsletter_subscribers')) {
    echo "<p>Current structure of <code>newsletter_subscribers</code>:</p>";

    try {
        $stmt = $pdo->query("
            SELECT column_name, data_type, character_maximum_length, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'newsletter_subscribers'
            ORDER BY ordinal_position
        ");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<thead><tr><th>Column</th><th>Type</th><th>Nullable</th><th>Default</th></tr></thead>";
        echo "<tbody>";
        foreach ($cols as $c) {
            $type = $c['data_type'] . ($c['character_maximum_length'] ? '(' . $c['character_maximum_length'] . ')' : '');
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($c['column_name']) . "</code></td>";
            echo "<td>" . htmlspecialchars($type) . "</td>";
            echo "<td>" . htmlspecialchars($c['is_nullable']) . "</td>";
            echo "<td>" . htmlspecialchars($c['column_default'] ?? '—') . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";

        // Required columns check
        echo "<h3>• Required column check</h3><ul>";
        $required = ['id', 'email', 'status', 'ip_address', 'user_agent', 'source', 'subscribed_at', 'created_at'];
        foreach ($required as $col) {
            $exists = columnExists($pdo, 'newsletter_subscribers', $col);
            $icon   = $exists ? '✅' : '❌';
            echo "<li>{$icon} <code>{$col}</code></li>";
        }
        echo "</ul>";

        // Row count
        $count = $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
        echo "<p class='ok'>✓ <strong>" . (int)$count . "</strong> subscriber row(s) in the table.</p>";

    } catch (PDOException $e) {
        echo "<p class='err'>✗ Verification error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p class='err'>✗ Table missing — cannot verify</p>";
}

echo "</div>";

// ============================================
// SUMMARY
// ============================================
echo "<div class='summary'>";
echo "<h2 style='border:none; margin-top:0;'>✅ Installation Complete</h2>";

if (!empty($summary['created'])) {
    echo "<p class='ok'>Created / changed:</p><ul>";
    foreach ($summary['created'] as $item) {
        echo "<li>✓ " . htmlspecialchars($item) . "</li>";
    }
    echo "</ul>";
}

if (!empty($summary['skipped'])) {
    echo "<p class='warn'>Skipped (already existed):</p><ul>";
    foreach ($summary['skipped'] as $item) {
        echo "<li>⚠ " . htmlspecialchars($item) . "</li>";
    }
    echo "</ul>";
}

if (!empty($summary['errors'])) {
    echo "<p class='err'>Errors:</p><ul>";
    foreach ($summary['errors'] as $item) {
        echo "<li>✗ " . htmlspecialchars($item) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p class='ok'>No errors. Newsletter table is ready.</p>";
}

echo "</div>";

echo "<p style='text-align:center; margin-top:30px;'>";
echo "<a href='admin/newsletter.php' class='btn'>← Go to Newsletter Admin</a>";
echo "<a href='admin/dashboard.php' class='btn secondary'>← Admin Dashboard</a>";
echo "<a href='index.php' class='btn secondary'>← Home</a>";
echo "</p>";

echo "<p style='text-align:center; color:#999; font-size:12px; margin-top:24px;'>";
echo "⚠ For security, delete <code>installdb.php</code> after running it in production.";
echo "</p>";

echo "</body></html>";
