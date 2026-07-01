<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================
// DATABASE CONNECTION
// ============================================

require_once 'includes/config.php';

// Check if user is admin (for security)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access denied. Admin access required.');
}

global $pdo;

// ============================================
// SETTINGS TABLE
// ============================================

$sql_settings = "
CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

// ============================================
// ACTIVITY LOGS TABLE
// ============================================

$sql_activity_logs = "
CREATE TABLE IF NOT EXISTS activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    user_name VARCHAR(100),
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

// ============================================
// INDEXES FOR PERFORMANCE
// ============================================

$sql_indexes = "
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_activity_logs_action ON activity_logs(action);
";

// ============================================
// DEFAULT SETTINGS
// ============================================

$sql_settings_insert = "
INSERT INTO settings (setting_key, setting_value) VALUES 
('site_name', 'WittyMart'),
('site_url', 'https://wittymart.onrender.com'),
('admin_email', 'admin@wittymart.com'),
('contact_phone', '+254 768 374 497'),
('address', 'Nairobi, Kenya'),
('currency', 'KES'),
('theme_color', '#05573c'),
('timezone', 'Africa/Nairobi'),
('maintenance_mode', '0'),
('allow_registration', '1'),
('site_description', 'Smart Shopping for Witty Minds!'),
('facebook_url', ''),
('twitter_url', ''),
('instagram_url', ''),
('youtube_url', ''),
('mailchimp_api_key', ''),
('mailchimp_list_id', ''),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_encryption', 'tls'),
('order_prefix', 'ORD-'),
('invoice_prefix', 'INV-'),
('tax_rate', '16.00'),
('shipping_rate', '200.00'),
('free_shipping_threshold', '5000.00'),
('enable_notifications', '1'),
('maintenance_message', 'We are currently performing maintenance. Please check back soon.')
ON CONFLICT (setting_key) DO NOTHING;
";

// ============================================
// TRIGGER FOR UPDATED_AT
// ============================================

$sql_trigger = "
DROP TRIGGER IF EXISTS update_settings_updated_at ON settings;
CREATE TRIGGER update_settings_updated_at
    BEFORE UPDATE ON settings
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
";

// ============================================
// FUNCTION FOR ACTIVITY LOG
// ============================================

$sql_activity_function = "
CREATE OR REPLACE FUNCTION log_activity(
    p_user_id INTEGER,
    p_user_name VARCHAR,
    p_action VARCHAR,
    p_description TEXT,
    p_ip_address VARCHAR,
    p_user_agent TEXT
) RETURNS VOID AS $$
BEGIN
    INSERT INTO activity_logs (user_id, user_name, action, description, ip_address, user_agent)
    VALUES (p_user_id, p_user_name, p_action, p_description, p_ip_address, p_user_agent);
END;
$$ LANGUAGE plpgsql;
";

// ============================================
// EXECUTE SQL
// ============================================

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Installation - WittyMart</title>
    <link rel='stylesheet' href='admin/admin.css'>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        h1 { color: #05573c; border-bottom: 3px solid #05573c; padding-bottom: 10px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .log { background: #f8f9fa; padding: 10px; border-radius: 6px; margin: 10px 0; font-family: monospace; font-size: 14px; }
        .btn { display: inline-block; padding: 10px 25px; background: #05573c; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .btn:hover { background: #03402c; }
        ul { list-style: none; padding: 0; }
        ul li { padding: 8px 0; border-bottom: 1px solid #eee; }
        ul li:before { content: '✓ '; color: #28a745; font-weight: bold; }
        .warning { background: #fff3cd; padding: 10px 15px; border-radius: 6px; border-left: 4px solid #ffc107; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 WittyMart Database Installation</h1>
        <p class='info'>Running database setup...</p>
        <div class='log'>";

try {
    // Execute SQL
    $results = [];
    
    // Create settings table
    if ($pdo->exec($sql_settings) !== false) {
        $results[] = "✅ Settings table created successfully";
    } else {
        $results[] = "⚠️ Settings table already exists or creation failed";
    }
    
    // Create activity logs table
    if ($pdo->exec($sql_activity_logs) !== false) {
        $results[] = "✅ Activity logs table created successfully";
    } else {
        $results[] = "⚠️ Activity logs table already exists or creation failed";
    }
    
    // Create indexes
    if ($pdo->exec($sql_indexes) !== false) {
        $results[] = "✅ Indexes created successfully";
    } else {
        $results[] = "⚠️ Indexes already exist or creation failed";
    }
    
    // Insert default settings
    if ($pdo->exec($sql_settings_insert) !== false) {
        $results[] = "✅ Default settings inserted successfully";
    } else {
        $results[] = "⚠️ Default settings already exist or insertion failed";
    }
    
    // Create trigger
    if ($pdo->exec($sql_trigger) !== false) {
        $results[] = "✅ Trigger created successfully";
    } else {
        $results[] = "⚠️ Trigger already exists or creation failed";
    }
    
    // Create activity log function
    if ($pdo->exec($sql_activity_function) !== false) {
        $results[] = "✅ Activity log function created successfully";
    } else {
        $results[] = "⚠️ Activity log function already exists or creation failed";
    }
    
    echo "<h2 class='success'>✅ Installation Complete!</h2>";
    echo "<ul>";
    foreach ($results as $result) {
        echo "<li>" . htmlspecialchars($result) . "</li>";
    }
    echo "</ul>";
    
    // Show installed tables
    echo "<h3>📋 Installed Tables</h3>";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tables = $stmt->fetchAll();
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table['table_name']) . "</li>";
    }
    echo "</ul>";
    
    // Show settings
    echo "<h3>⚙️ Current Settings</h3>";
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings ORDER BY setting_key");
    $settings = $stmt->fetchAll();
    echo "<ul>";
    foreach ($settings as $setting) {
        echo "<li><strong>" . htmlspecialchars($setting['setting_key']) . ":</strong> " . htmlspecialchars($setting['setting_value']) . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<h2 class='error'>❌ Installation Failed</h2>";
    echo "<div class='warning'>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Code:</strong> " . htmlspecialchars($e->getCode()) . "</p>";
    echo "</div>";
}

echo "
        </div>
        <p><a href='admin/dashboard.php' class='btn'>Go to Dashboard</a></p>
    </div>
</body>
</html>";
?>
