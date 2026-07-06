<?php
// config.php

error_reporting(E_ALL);
ini_set('display_errors', 0);

// ===== SESSION CONFIGURATION =====
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// Get database URL from environment variable (Render)
$database_url = getenv('DATABASE_URL');

if (!$database_url && isset($_ENV['DATABASE_URL'])) {
    $database_url = $_ENV['DATABASE_URL'];
}

error_log('DATABASE_URL exists: ' . ($database_url ? 'Yes' : 'No'));

if (!$database_url) {
    die('DATABASE_URL environment variable is not set');
}

// Parse the database URL
$db_parts = parse_url($database_url);

$db_config = [
    'host' => $db_parts['host'] ?? 'localhost',
    'port' => $db_parts['port'] ?? '5432',
    'dbname' => ltrim($db_parts['path'] ?? '', '/'),
    'user' => $db_parts['user'] ?? '',
    'password' => $db_parts['pass'] ?? '',
];

// ============================================
// PDO DATABASE CONNECTION - MAKE $pdo GLOBAL
// ============================================

try {
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $db_config['host'],
        $db_config['port'],
        $db_config['dbname']
    );
    
    $pdo = new PDO($dsn, $db_config['user'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    error_log('Connection details - Host: ' . $db_config['host'] . ', DB: ' . $db_config['dbname']);
    die('Database connection error. Please try again later.');
}

// ============================================
// APPLICATION CONFIGURATION
// ============================================

// Site URL (auto-detect for development)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $protocol . $host);

// Admin panel URL
define('ADMIN_URL', SITE_URL . '/admin');

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Security settings
define('PASSWORD_BCRYPT_COST', 12);

// ============================================
// HELPER FUNCTIONS
// ============================================

// ===== SITE CONFIGURATION =====
define('BASE_URL', 'https://wittymart.onrender.com/'); 
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/');

// Upload directory configuration
define('UPLOAD_DIR', BASE_PATH . 'uploads/products/');
define('UPLOAD_URL', BASE_URL . 'uploads/products/');

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Create no-image placeholder if it doesn't exist
$no_image_path = UPLOAD_DIR . 'no-image.png';
if (!file_exists($no_image_path)) {
    if (function_exists('imagecreate')) {
        $image = imagecreate(50, 50);
        $bg = imagecolorallocate($image, 5, 87, 60);
        $text_color = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 20, 15, '?', $text_color);
        imagepng($image, $no_image_path);
        imagedestroy($image);
        error_log('Created no-image placeholder at: ' . $no_image_path);
    }
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    
    $base_dir = str_replace('/admin', '', $script_dir);
    
    return $protocol . $host . $base_dir . '/';
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// ... rest of your functions ...
