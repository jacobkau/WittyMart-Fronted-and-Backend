<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

// ===== SESSION CONFIGURATION =====
// Only set session ini settings if session is not already active
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    // Start session
    session_start();
}

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
        $bg = imagecolorallocate($image, 5, 87, 60); // #05573c
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
 * Generate URL-friendly slug
 */
function generateSlug($string) {
    // Convert to lowercase
    $string = strtolower($string);
    
    // Replace spaces with hyphens
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    
    // Remove multiple hyphens
    $string = preg_replace('/-+/', '-', $string);
    
    // Trim hyphens from ends
    return trim($string, '-');
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

/**
 * Log an activity
 */
function logActivity($action, $description = '', $user_id = null, $user_name = null) {
    global $pdo;
    
    // Get current user if not provided
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['user_name'] ?? null;
    }
    
    // Get IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Get user agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $user_name, $action, $description, $ip_address, $user_agent]);
    } catch (PDOException $e) {
        error_log('Log activity error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get recent activities
 */
function getRecentActivities($limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM activity_logs 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get activities error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get activity logs with pagination
 */
function getActivityLogs($page = 1, $perPage = 20) {
    global $pdo;
    
    $offset = ($page - 1) * $perPage;
    
    try {
        // Get total count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM activity_logs");
        $total = $stmt->fetch()['count'];
        
        // Get logs
        $stmt = $pdo->prepare("
            SELECT * FROM activity_logs 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        $logs = $stmt->fetchAll();
        
        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    } catch (PDOException $e) {
        error_log('Get activity logs error: ' . $e->getMessage());
        return ['logs' => [], 'total' => 0, 'totalPages' => 0];
    }
}

/**
 * Clear old activity logs
 */
function clearActivityLogs($days = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < NOW() - INTERVAL ? DAY");
        return $stmt->execute([$days]);
    } catch (PDOException $e) {
        error_log('Clear activity logs error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get user activities
 */
function getUserActivities($user_id, $limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get user activities error: ' . $e->getMessage());
        return [];
    }
}

// ============================================
// FEATURED PRODUCTS FUNCTIONS
// ============================================

/**
 * Get featured products
 */
function getFeaturedProducts($limit = 8) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p
            INNER JOIN featured_products fp ON p.id = fp.product_id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active' OR p.status IS NULL
            ORDER BY fp.display_order ASC, p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get featured products error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get testimonials
 */
function getTestimonials($limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM testimonials 
            WHERE status = 'active' 
            ORDER BY display_order ASC, created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get testimonials error: ' . $e->getMessage());
        return [];
    }
}

// ============================================
// PRODUCT IMAGE HELPER FUNCTIONS
// ============================================

/**
 * Get product image URL
 */
function getProductImageUrl($image_path) {
    if (empty($image_path)) {
        return UPLOAD_URL . 'no-image.png';
    }
    
    // If it's already a full URL, return it
    if (strpos($image_path, 'http://') === 0 || strpos($image_path, 'https://') === 0) {
        return $image_path;
    }
    
    // Clean the path
    $image_path = ltrim($image_path, '/');
    $image_path = str_replace('../', '', $image_path);
    
    return UPLOAD_URL . basename($image_path);
}

/**
 * Render star rating
 */
function renderStars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="fas fa-star" style="color: #ffc107;"></i>';
        } else {
            $html .= '<i class="far fa-star" style="color: #ddd;"></i>';
        }
    }
    return $html;
}

// ============================================
// CART FUNCTIONS
// ============================================

/**
 * Get cart items for current user
 */
function getCartItems() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.image 
            FROM cart c
            INNER JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get cart items error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Add item to cart
 */
function addToCart($product_id, $quantity = 1) {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        // Check if product already in cart
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update quantity
            $new_quantity = $existing['quantity'] + $quantity;
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            return $stmt->execute([$new_quantity, $existing['id']]);
        } else {
            // Insert new item
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            return $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
        }
    } catch (PDOException $e) {
        error_log('Add to cart error: ' . $e->getMessage());
        return false;
    }
}

// ============================================
// NEWSLETTER FUNCTIONS
// ============================================

/**
 * Subscribe to newsletter
 */
function subscribeNewsletter($email) {
    global $pdo;
    
    try {
        // Check if already subscribed
        $stmt = $pdo->prepare("SELECT id FROM newsletter_subscribers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already subscribed'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO newsletter_subscribers (email, status) VALUES (?, 'pending')");
        if ($stmt->execute([$email])) {
            return ['success' => true, 'message' => 'Subscription successful!'];
        }
        return ['success' => false, 'message' => 'Subscription failed'];
    } catch (PDOException $e) {
        error_log('Subscribe newsletter error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}
?>
