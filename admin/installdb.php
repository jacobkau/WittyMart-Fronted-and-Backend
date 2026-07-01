<?php
// install.php - Database Update Script

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';

global $pdo;

// ============================================
// SQL QUERIES - FIXED
// ============================================

// Add status column to users table (PostgreSQL syntax)
$sql_add_status = "
ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';
";

// Update existing users with NULL status to 'active'
$sql_update_status = "
UPDATE users SET status = 'active' WHERE status IS NULL;
";

// Update super admin role
$sql_super_admin = "
UPDATE users SET role = 'super_admin' WHERE email = 'admin@wittymart.com';
";

// Optional: Create or update update_updated_at function
$sql_update_function = "
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
";

// ============================================
// EXECUTE QUERIES
// ============================================

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Update - WittyMart</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            padding: 40px; 
            background: #f0f2f5; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            max-width: 900px; 
            width: 100%;
            margin: 0 auto; 
            background: #fff; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.1); 
        }
        h1 { 
            color: #05573c; 
            border-bottom: 4px solid #05573c; 
            padding-bottom: 15px; 
            margin-bottom: 20px;
            font-size: 28px;
        }
        h1 i { margin-right: 10px; }
        h3 { margin: 20px 0 10px; color: #05573c; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .log { 
            background: #f8f9fa; 
            padding: 15px 20px; 
            border-radius: 8px; 
            margin: 15px 0; 
            font-family: 'Courier New', monospace; 
            font-size: 14px; 
            max-height: 300px;
            overflow-y: auto;
        }
        .btn { 
            display: inline-block; 
            padding: 12px 30px; 
            background: #05573c; 
            color: #fff; 
            text-decoration: none; 
            border-radius: 8px; 
            margin-top: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover { 
            background: #03402c; 
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(5,87,60,0.3);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        ul { list-style: none; padding: 0; margin: 15px 0; }
        ul li { 
            padding: 10px 15px; 
            border-bottom: 1px solid #f0f0f0; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        ul li:last-child { border-bottom: none; }
        ul li .icon { font-size: 18px; }
        ul li .icon.success { color: #28a745; }
        ul li .icon.warning { color: #ffc107; }
        ul li .icon.error { color: #dc3545; }
        .warning { 
            background: #fff3cd; 
            padding: 15px 20px; 
            border-radius: 8px; 
            border-left: 5px solid #ffc107; 
            margin: 15px 0; 
        }
        .warning strong { color: #856404; }
        .success-box {
            background: #d4edda;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 5px solid #28a745;
            margin: 15px 0;
        }
        .success-box strong { color: #155724; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 24px;
            font-weight: 700;
            color: #05573c;
        }
        .stat-card .label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .security-notice {
            background: #cce5ff;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 5px solid #007bff;
            margin: 15px 0;
        }
        .security-notice strong { color: #004085; }
        .security-notice a { color: #05573c; }
    </style>
</head>
<body>
    <div class='container'>
        <h1><i class='fas fa-database' style='color:#05573c;'></i> WittyMart Database Update</h1>
        <p class='info'>Running database updates...</p>
        <div class='log'>";

try {
    $results = [];
    $errors = [];
    $warnings = [];
    
    // Check database connection
    try {
        $stmt = $pdo->query("SELECT 1");
        $results[] = ['type' => 'success', 'msg' => '✅ Database connection successful'];
    } catch (PDOException $e) {
        $errors[] = '❌ Database connection failed: ' . $e->getMessage();
    }
    
    // ===== 1. Create update_updated_at function =====
    if (empty($errors)) {
        try {
            $pdo->exec($sql_update_function);
            $results[] = ['type' => 'success', 'msg' => '✅ update_updated_at function created/updated'];
        } catch (PDOException $e) {
            $warnings[] = '⚠️ update_updated_at function: ' . $e->getMessage();
        }
    }
    
    // ===== 2. Add status column =====
    if (empty($errors)) {
        try {
            $pdo->exec($sql_add_status);
            $results[] = ['type' => 'success', 'msg' => '✅ Status column added to users table'];
        } catch (PDOException $e) {
            $warnings[] = '⚠️ Status column: ' . $e->getMessage();
        }
    }
    
    // ===== 3. Update NULL status values =====
    if (empty($errors)) {
        try {
            $pdo->exec($sql_update_status);
            $results[] = ['type' => 'success', 'msg' => '✅ NULL status values updated to "active"'];
        } catch (PDOException $e) {
            $warnings[] = '⚠️ Update status: ' . $e->getMessage();
        }
    }
    
    // ===== 4. Set super_admin role =====
    if (empty($errors)) {
        try {
            $pdo->exec($sql_super_admin);
            $results[] = ['type' => 'success', 'msg' => '✅ Super admin role assigned to admin@wittymart.com'];
        } catch (PDOException $e) {
            $warnings[] = '⚠️ Super admin: ' . $e->getMessage();
        }
    }
    
    // ===== 5. Check if settings table exists and add missing columns =====
    if (empty($errors)) {
        try {
            $stmt = $pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'settings')");
            if ($stmt->fetchColumn()) {
                $results[] = ['type' => 'success', 'msg' => '✅ Settings table exists'];
            } else {
                // Create settings table if not exists
                $pdo->exec("
                    CREATE TABLE settings (
                        id SERIAL PRIMARY KEY,
                        setting_key VARCHAR(100) UNIQUE NOT NULL,
                        setting_value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $results[] = ['type' => 'success', 'msg' => '✅ Settings table created'];
            }
        } catch (PDOException $e) {
            $warnings[] = '⚠️ Settings table: ' . $e->getMessage();
        }
    }
    
    // ===== 6. Check if activity_logs table exists =====
    if (empty($errors)) {
        try {
            $stmt = $pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'activity_logs')");
            if ($stmt->fetchColumn()) {
                $results[] = ['type' => 'success', 'msg' => '✅ Activity logs table exists'];
            } else {
                // Create activity_logs table if not exists
                $pdo->exec("
                    CREATE TABLE activity_logs (
                        id SERIAL PRIMARY KEY,
                        user_id INTEGER,
                        user_name VARCHAR(100),
                        action VARCHAR(100) NOT NULL,
                        description TEXT,
                        ip_address VARCHAR(45),
                        user_agent TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $results[] = ['type' => 'success', 'msg' => '✅ Activity logs table created'];
            }
        } catch (PDOException $e) {
            $warnings[] = '⚠️ Activity logs table: ' . $e->getMessage();
        }
    }
    
    // ===== SHOW RESULTS =====
    if (empty($errors)) {
        echo "<div class='success-box'>";
        echo "<strong>✅ Update Complete!</strong> All database updates have been applied successfully.";
        echo "</div>";
    }
    
    // Show results
    echo "<ul>";
    foreach ($results as $result) {
        $icon = $result['type'] === 'success' ? '✅' : 'ℹ️';
        echo "<li><span class='icon " . $result['type'] . "'>" . $icon . "</span> " . htmlspecialchars($result['msg']) . "</li>";
    }
    foreach ($warnings as $warning) {
        echo "<li><span class='icon warning'>⚠️</span> " . htmlspecialchars($warning) . "</li>";
    }
    foreach ($errors as $error) {
        echo "<li><span class='icon error'>❌</span> " . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
    
    // ===== SHOW TABLE LIST =====
    echo "<h3>📋 Tables in Database</h3>";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tables = $stmt->fetchAll();
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            // Get row count for each table
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM " . $table['table_name']);
            $count = $countStmt->fetch()['count'];
            echo "<li>📄 " . htmlspecialchars($table['table_name']) . " <span style='color: #666; font-size: 12px;'>(" . $count . " rows)</span></li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='warning'>No tables found.</p>";
    }
    
    // ===== SHOW ADMIN USERS =====
    echo "<h3>👤 Admin Users</h3>";
    $stmt = $pdo->query("SELECT id, name, email, role, status FROM users WHERE role IN ('admin', 'super_admin')");
    $admins = $stmt->fetchAll();
    if (count($admins) > 0) {
        echo "<ul>";
        foreach ($admins as $admin) {
            $status = $admin['status'] ?? 'active';
            $statusIcon = $status === 'active' ? '🟢' : '🔴';
            echo "<li>" . $statusIcon . " " . htmlspecialchars($admin['name']) . " (" . htmlspecialchars($admin['email']) . ") - " . htmlspecialchars($admin['role']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='warning'>No admin users found.</p>";
    }
    
    // ===== STATS =====
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $total_users = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM settings");
    $settings_count = $stmt->fetch()['count'];
    
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'><div class='number'>" . count($tables) . "</div><div class='label'>Tables</div></div>";
    echo "<div class='stat-card'><div class='number'>" . $total_users . "</div><div class='label'>Users</div></div>";
    echo "<div class='stat-card'><div class='number'>" . $settings_count . "</div><div class='label'>Settings</div></div>";
    echo "</div>";
    
    // ===== SECURITY NOTICE =====
    echo "<div class='security-notice'>";
    echo "<strong>🔒 Security Notice:</strong> ";
    echo "For security, please <strong>delete or rename</strong> this file (install.php) after installation is complete.";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='warning'>";
    echo "<p><strong>❌ Installation Failed</strong></p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Code:</strong> " . htmlspecialchars($e->getCode()) . "</p>";
    echo "</div>";
}

echo "
        </div>
        <div style='display: flex; gap: 15px; flex-wrap: wrap;'>
            <a href='admin/login.php' class='btn'><i class='fas fa-sign-in-alt'></i> Admin Login</a>
            <a href='admin/dashboard.php' class='btn btn-secondary'><i class='fas fa-tachometer-alt'></i> Dashboard</a>
            <a href='index.php' class='btn btn-secondary'><i class='fas fa-home'></i> Homepage</a>
        </div>
    </div>
</body>
</html>";
?>
