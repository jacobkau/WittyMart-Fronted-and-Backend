<?php
// ============================================
// CONFIGURATION FILE
// ============================================

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// DATABASE CONFIGURATION
// ============================================

// Get database URL from environment variable (Render)
$database_url = getenv('DATABASE_URL');

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

// For local development fallback (optional)
if (empty($db_config['dbname']) || empty($db_config['user'])) {
    // Fallback to local settings if not using Aiven
    $db_config = [
        'host' => 'localhost',
        'port' => '5432',
        'dbname' => 'wittymart',
        'user' => 'postgres',
        'password' => '',
    ];
}

// ============================================
// PDO DATABASE CONNECTION
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
    // Log error (don't display to user)
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection error. Please try again later.');
}

// ============================================
// APPLICATION CONFIGURATION
// ============================================

// Site URL (for redirects)
define('SITE_URL', 'https://your-site.onrender.com'); // Change this

// Admin panel URL
define('ADMIN_URL', SITE_URL . '/admin');

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Security settings
define('PASSWORD_BCRYPT_COST', 12);

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header('Location: ' . $url);
    exit();
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();
?>
