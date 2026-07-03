<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/auth.php';

requireAdmin();

global $pdo;

$message = '';
$messageType = '';

// ===== GET SETTINGS FROM DATABASE =====
function getSettings($pdo) {
    try {
        // Check if settings table exists
        $stmt = $pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'settings')");
        $tableExists = $stmt->fetchColumn();
        
        if (!$tableExists) {
            // Create settings table if it doesn't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    id SERIAL PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Insert default settings
            $defaults = [
                ['site_name', 'WittyMart'],
                ['site_url', 'https://wittymart.onrender.com'],
                ['admin_email', 'admin@wittymart.com'],
                ['contact_phone', '+254 768 374 497'],
                ['address', 'Nairobi, Kenya'],
                ['currency', 'KES'],
                ['theme_color', '#05573c'],
                ['timezone', 'Africa/Nairobi'],
                ['maintenance_mode', '0'],
                ['allow_registration', '1']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($defaults as $setting) {
                $stmt->execute([$setting[0], $setting[1]]);
            }
        }
        
        // Get all settings
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
        
    } catch (PDOException $e) {
        error_log('Get settings error: ' . $e->getMessage());
        return [];
    }
}

function updateSetting($pdo, $key, $value) {
    try {
        $stmt = $pdo->prepare("
            UPDATE settings 
            SET setting_value = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE setting_key = ?
        ");
        return $stmt->execute([$value, $key]);
    } catch (PDOException $e) {
        error_log('Update setting error: ' . $e->getMessage());
        return false;
    }
}

// ===== GET CURRENT SETTINGS =====
$settings = getSettings($pdo);

// ===== HANDLE FORM SUBMISSION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update each setting
        $setting_keys = [
            'site_name', 'site_url', 'admin_email', 'contact_phone', 
            'address', 'currency', 'theme_color', 'timezone',
            'maintenance_mode', 'allow_registration'
        ];
        
        foreach ($setting_keys as $key) {
            if (isset($_POST[$key])) {
                $value = sanitize($_POST[$key]);
                updateSetting($pdo, $key, $value);
            }
        }
        
        // Refresh settings
        $settings = getSettings($pdo);
        
        $message = 'Settings updated successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        error_log('Save settings error: ' . $e->getMessage());
        $message = 'Failed to save settings: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ===== GET SYSTEM INFO =====
$system_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database' => 'PostgreSQL',
    'upload_max_size' => ini_get('upload_max_filesize'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'post_max_size' => ini_get('post_max_size'),
    'server_time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get()
];

$page_title = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - WittyMart Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include "header.php"?>
    <div class="admin-wrapper">
        <?php include "sidebar.php" ?>
        <!-- Main Content -->
        <main class="admin-main">

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Settings Form -->
            <div class="admin-card" style="padding:14px">
                <div class="card-header">
                    <h2><i class="fas fa-sliders-h"></i> General Settings</h2>
                </div>
                <div class="card-body" style="padding:14px">
                    <form method="POST">
                        <div class="settings-grid">
                            <!-- General Section -->
                            <div class="settings-section">
                                <h3><i class="fas fa-store"></i> Store Settings</h3>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-store"></i> Site Name</label>
                                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'WittyMart'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-link"></i> Site URL</label>
                                    <input type="url" name="site_url" value="<?php echo htmlspecialchars($settings['site_url'] ?? 'https://wittymart.onrender.com'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Admin Email</label>
                                    <input type="email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email'] ?? 'admin@wittymart.com'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-phone"></i> Contact Phone</label>
                                    <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? '+254 768 374 497'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-map-marker-alt"></i> Address</label>
                                    <input type="text" name="address" value="<?php echo htmlspecialchars($settings['address'] ?? 'Nairobi, Kenya'); ?>">
                                </div>
                            </div>
                            
                            <!-- Currency & Theme Section -->
                            <div class="settings-section">
                                <h3><i class="fas fa-palette"></i> Appearance & Currency</h3>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-money-bill-wave"></i> Currency</label>
                                    <select name="currency">
                                        <?php
                                        $currencies = [
                                            'KES' => 'Kenyan Shilling (KES)',
                                            'USD' => 'US Dollar (USD)',
                                            'EUR' => 'Euro (EUR)',
                                            'GBP' => 'British Pound (GBP)',
                                            'CAD' => 'Canadian Dollar (CAD)',
                                            'AUD' => 'Australian Dollar (AUD)',
                                            'JPY' => 'Japanese Yen (JPY)',
                                            'CNY' => 'Chinese Yuan (CNY)'
                                        ];
                                        $current_currency = $settings['currency'] ?? 'KES';
                                        foreach ($currencies as $code => $name):
                                        ?>
                                            <option value="<?php echo $code; ?>" <?php echo $current_currency === $code ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-palette"></i> Theme Color</label>
                                    <div class="color-picker-wrapper">
                                        <input type="color" name="theme_color" value="<?php echo htmlspecialchars($settings['theme_color'] ?? '#05573c'); ?>">
                                        <span class="color-hex"><?php echo htmlspecialchars($settings['theme_color'] ?? '#05573c'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-globe-africa"></i> Timezone</label>
                                    <select name="timezone">
                                        <?php
                                        $timezones = [
                                            'Africa/Nairobi' => 'Africa/Nairobi (EAT)',
                                            'Africa/Lagos' => 'Africa/Lagos (WAT)',
                                            'Africa/Cairo' => 'Africa/Cairo (EET)',
                                            'Africa/Johannesburg' => 'Africa/Johannesburg (SAST)',
                                            'America/New_York' => 'America/New_York (EST)',
                                            'America/Los_Angeles' => 'America/Los_Angeles (PST)',
                                            'Europe/London' => 'Europe/London (GMT)',
                                            'Europe/Paris' => 'Europe/Paris (CET)',
                                            'Asia/Dubai' => 'Asia/Dubai (GST)',
                                            'Asia/Tokyo' => 'Asia/Tokyo (JST)',
                                            'Australia/Sydney' => 'Australia/Sydney (AEST)'
                                        ];
                                        $current_timezone = $settings['timezone'] ?? 'Africa/Nairobi';
                                        foreach ($timezones as $tz => $name):
                                        ?>
                                            <option value="<?php echo $tz; ?>" <?php echo $current_timezone === $tz ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- System Settings -->
                            <div class="settings-section">
                                <h3><i class="fas fa-cogs"></i> System Settings</h3>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-toggle-on"></i> Maintenance Mode</label>
                                    <select name="maintenance_mode">
                                        <option value="0" <?php echo ($settings['maintenance_mode'] ?? '0') === '0' ? 'selected' : ''; ?>>Disabled</option>
                                        <option value="1" <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'selected' : ''; ?>>Enabled</option>
                                    </select>
                                    <small class="form-text text-muted">When enabled, only admins can access the site.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-user-plus"></i> Allow Registration</label>
                                    <select name="allow_registration">
                                        <option value="1" <?php echo ($settings['allow_registration'] ?? '1') === '1' ? 'selected' : ''; ?>>Enabled</option>
                                        <option value="0" <?php echo ($settings['allow_registration'] ?? '1') === '0' ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                                    <small class="form-text text-muted">Allow new users to register on the site.</small>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-save"></i> Save All Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- System Information -->
            <div class="admin-card" style="margin-top: 20px;">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> System Information</h2>
                </div>
                <div class="card-body">
                    <div class="system-info-grid">
                        <div class="info-item">
                            <span class="info-label"><i class="fab fa-php"></i> PHP Version</span>
                            <span class="info-value"><?php echo $system_info['php_version']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-server"></i> Server</span>
                            <span class="info-value"><?php echo htmlspecialchars($system_info['server_software']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-database"></i> Database</span>
                            <span class="info-value"><?php echo $system_info['database']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-upload"></i> Upload Max Size</span>
                            <span class="info-value"><?php echo $system_info['upload_max_size']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-memory"></i> Memory Limit</span>
                            <span class="info-value"><?php echo $system_info['memory_limit']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-clock"></i> Max Execution Time</span>
                            <span class="info-value"><?php echo $system_info['max_execution_time']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-file-upload"></i> Post Max Size</span>
                            <span class="info-value"><?php echo $system_info['post_max_size']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-clock"></i> Server Time</span>
                            <span class="info-value"><?php echo $system_info['server_time']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-globe-africa"></i> Timezone</span>
                            <span class="info-value"><?php echo $system_info['timezone']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        /* Additional styles for settings page */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }
        
        .settings-section {
            background: var(--bg);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .settings-section h3 {
            font-size: 16px;
            color: var(--primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border);
        }
        
        .settings-section h3 i {
            margin-right: 8px;
        }
        
        .color-picker-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .color-picker-wrapper input[type="color"] {
            width: 50px;
            height: 50px;
            padding: 2px;
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
        }
        
        .color-hex {
            font-family: monospace;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }
        
        .form-text {
            display: block;
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: var(--bg);
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text);
        }
        
        .info-label i {
            margin-right: 8px;
            color: var(--primary);
            width: 20px;
        }
        
        .info-value {
            font-family: monospace;
            font-size: 13px;
            color: var(--text-muted);
            background: var(--bg);
            padding: 2px 10px;
            border-radius: 4px;
        }
        
        /* Dark mode overrides */
        body.dark-mode .settings-section {
            background: rgba(255,255,255,0.05);
        }
        
        body.dark-mode .info-item {
            background: rgba(255,255,255,0.05);
        }
        
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .system-info-grid {
                grid-template-columns: 1fr;
            }
            
            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</body>
</html>
