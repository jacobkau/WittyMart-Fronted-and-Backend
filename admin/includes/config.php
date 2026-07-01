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
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// DATABASE CONFIGURATION
// ============================================

// Get database URL from environment variable (Render)
$database_url = getenv('DATABASE_URL');

// If not found, try to get it from $_ENV (alternative)
if (!$database_url && isset($_ENV['DATABASE_URL'])) {
    $database_url = $_ENV['DATABASE_URL'];
}

// For debugging - check if URL exists
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

// ============================================
// DATABASE FUNCTIONS FOR DASHBOARD
// ============================================

/**
 * Get statistics for dashboard
 */
function getStats() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Total products
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
        $stats['products'] = $stmt->fetch()['count'] ?? 0;
        
        // Total orders
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
        $stats['orders'] = $stmt->fetch()['count'] ?? 0;
        
        // Total revenue
        $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE status != 'cancelled'");
        $stats['revenue'] = $stmt->fetch()['total'] ?? 0;
        
        // Total customers
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
        $stats['customers'] = $stmt->fetch()['count'] ?? 0;
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log('Get stats error: ' . $e->getMessage());
        return [
            'products' => 0,
            'orders' => 0,
            'revenue' => 0,
            'customers' => 0
        ];
    }
}

/**
 * Get all orders
 */
function getOrders() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT o.*, u.name as customer_name 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            ORDER BY o.created_at DESC
        ");
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log('Get orders error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get recent orders (limited)
 */
function getRecentOrders($limit = 5) {
    $orders = getOrders();
    return array_slice($orders, 0, $limit);
}

/**
 * Format price
 */
function formatPrice($price) {
    return 'Ksh ' . number_format($price, 2);
}

/**
 * Get status badge class
 */
function getStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'processing' => 'badge-info',
        'shipped' => 'badge-primary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $badges[$status] ?? 'badge-secondary';
}

/**
 * Get all products
 */
function getProducts() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            ORDER BY p.created_at DESC
        ");
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log('Get products error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all customers
 */
function getCustomers() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC");
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log('Get customers error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all categories
 */
function getCategories() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log('Get categories error: ' . $e->getMessage());
        return [];
    }
}
?>
