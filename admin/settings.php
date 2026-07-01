<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

requireAdmin();

$message = '';
$messageType = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = sanitize($_POST['site_name'] ?? SITE_NAME);
    $admin_email = sanitize($_POST['admin_email'] ?? ADMIN_EMAIL);
    
    // Update settings (in a real app, save to database)
    // For now, we'll just show a success message
    $message = 'Settings updated successfully!';
    $messageType = 'success';
}

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
    <div class="admin-wrapper">
        <?php include "sidebar.php" ?>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1><i class="fas fa-cog"></i> System Settings</h1>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-persistent">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Settings Form -->
            <div class="admin-card">
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-store"></i> Site Name</label>
                            <input type="text" name="site_name" value="<?php echo SITE_NAME; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Admin Email</label>
                            <input type="email" name="admin_email" value="<?php echo ADMIN_EMAIL; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Contact Phone</label>
                            <input type="text" name="phone" value="+254 768 374 497">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <input type="text" name="address" value="Nairobi, Kenya">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-money-bill-wave"></i> Currency</label>
                            <select name="currency">
                                <option value="KES">Kenyan Shilling (KES)</option>
                                <option value="USD">US Dollar (USD)</option>
                                <option value="EUR">Euro (EUR)</option>
                                <option value="GBP">British Pound (GBP)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-palette"></i> Theme Color</label>
                            <input type="color" name="theme_color" value="#05573c">
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- System Info -->
            <div class="admin-card" style="margin-top: 20px;">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> System Information</h2>
                </div>
                <div class="card-body">
                    <table class="admin-table">
                        <tbody>
                            <tr>
                                <td><strong>PHP Version</strong></td>
                                <td><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Server Software</strong></td>
                                <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Database</strong></td>
                                <td>MySQL</td>
                            </tr>
                            <tr>
                                <td><strong>Upload Max Size</strong></td>
                                <td><?php echo ini_get('upload_max_filesize'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Memory Limit</strong></td>
                                <td><?php echo ini_get('memory_limit'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Max Execution Time</strong></td>
                                <td><?php echo ini_get('max_execution_time'); ?> seconds</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
