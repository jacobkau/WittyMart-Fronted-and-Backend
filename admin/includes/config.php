<?php

// ===== ENABLE ERROR DISPLAY FOR DEBUGGING =====
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ============================================
// SESSION CONFIGURATION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_path', '/');
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// ============================================
// DATABASE CONNECTION
// ============================================

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
    die('Database connection error. Please try again later.');
}

// ============================================
// APPLICATION CONFIGURATION
// ============================================

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $protocol . $host);
define('ADMIN_URL', SITE_URL . '/admin');
define('SESSION_TIMEOUT', 1800);
define('PASSWORD_BCRYPT_COST', 12);

// Site paths
define('BASE_URL', 'https://wittymart.onrender.com/');
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/');
define('UPLOAD_DIR', BASE_PATH . 'uploads/products/');
define('UPLOAD_URL', BASE_URL . 'uploads/products/');

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Create no-image placeholder
$no_image_path = UPLOAD_DIR . 'no-image.png';
if (!file_exists($no_image_path) && function_exists('imagecreate')) {
    $image = imagecreate(50, 50);
    $bg = imagecolorallocate($image, 5, 87, 60);
    $text_color = imagecolorallocate($image, 255, 255, 255);
    imagestring($image, 5, 20, 15, '?', $text_color);
    imagepng($image, $no_image_path);
    imagedestroy($image);
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

/**
 * Redirect to a URL
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Start session if not already started
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Login user
 */
function login($email, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        error_log('Login attempt for email: ' . $email . ' - User found: ' . ($user ? 'Yes' : 'No'));
        
        if ($user && password_verify($password, $user['password'])) {
            startSession();
            
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            logActivity('login', 'User logged in successfully', $user['id'], $user['name']);
            error_log('Login successful for: ' . $email);
            return true;
        }
        
        logActivity('failed_login', 'Failed login attempt for email: ' . $email);
        error_log('Login failed for: ' . $email . ' - Invalid credentials');
        return false;
        
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    startSession();
    
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    if (isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            logout();
            return false;
        }
    }
    
    return true;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Require admin access
 */
function requireAdmin() {
    if (!isAdmin()) {
        redirect('login.php');
        exit;
    }
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
        exit;
    }
}

/**
 * Logout user
 */
function logout() {
    startSession();
    
    if (isset($_SESSION['user_id'])) {
        logActivity('logout', 'User logged out', $_SESSION['user_id'], $_SESSION['user_name'] ?? null);
    }
    
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    
    session_destroy();
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, role, phone, created_at, last_login FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get user error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Check if email exists
 */
function emailExists($email, $excludeId = null) {
    global $pdo;
    
    try {
        $sql = "SELECT id FROM users WHERE LOWER(email) = LOWER(?)";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch() !== false;
        
    } catch (PDOException $e) {
        error_log('Email check error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_BCRYPT_COST]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if password needs rehash
 */
function needsRehash($hash) {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => PASSWORD_BCRYPT_COST]);
}

/**
 * Create user
 */
function createUser($name, $email, $password, $role = 'user') {
    global $pdo;
    
    if (emailExists($email)) {
        return false;
    }
    
    try {
        $hashedPassword = hashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$name, $email, $hashedPassword, $role]);
    } catch (PDOException $e) {
        error_log('Create user error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update user password
 */
function updatePassword($userId, $newPassword) {
    global $pdo;
    
    try {
        $hashedPassword = hashPassword($newPassword);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $userId]);
    } catch (PDOException $e) {
        error_log('Update password error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has permission
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $permissions = [
        'admin' => ['all'],
        'user' => ['view_products', 'view_orders', 'create_orders'],
    ];
    
    $role = $_SESSION['user_role'] ?? 'user';
    
    return isset($permissions[$role]) && 
           (in_array('all', $permissions[$role]) || in_array($permission, $permissions[$role]));
}

// ============================================
// ACTIVITY LOG FUNCTIONS
// ============================================

/**
 * Log an activity
 */
function logActivity($action, $description = '', $user_id = null, $user_name = null) {
    global $pdo;
    
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $user_name = $_SESSION['user_name'] ?? null;
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
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
 * Get badge class for activity type
 */
function getActivityBadge($action) {
    $badges = [
        'login' => 'login',
        'logout' => 'logout',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
        'view' => 'view',
        'system' => 'system',
        'add' => 'create',
        'edit' => 'update',
        'remove' => 'delete'
    ];
    
    foreach ($badges as $key => $badge) {
        if (stripos($action, $key) !== false) {
            return $badge;
        }
    }
    return 'system';
}

/**
 * Get recent activities
 */
function getRecentActivities($limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?");
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
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM activity_logs");
        $total = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
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
        $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get user activities error: ' . $e->getMessage());
        return [];
    }
}

// ============================================
// GENERAL HELPER FUNCTIONS
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
 * Generate URL-friendly slug
 */
function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
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
// DATABASE QUERY FUNCTIONS
// ============================================

/**
 * Get statistics for dashboard
 */
function getStats() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
        $stats['products'] = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
        $stats['orders'] = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE status != 'cancelled'");
        $stats['revenue'] = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
        $stats['customers'] = $stmt->fetch()['count'] ?? 0;
        
        return $stats;
    } catch (PDOException $e) {
        error_log('Get stats error: ' . $e->getMessage());
        return ['products' => 0, 'orders' => 0, 'revenue' => 0, 'customers' => 0];
    }
}

/**
 * Get all orders
 */
function getOrders() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT o.*, u.name as customer_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get orders error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all products
 */
function getProducts() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC");
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
        $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE status = 'active' ORDER BY display_order ASC, created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get testimonials error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get cart items for current user
 */
function getCartItems() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.image FROM cart c INNER JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
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
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $new_quantity = $existing['quantity'] + $quantity;
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            return $stmt->execute([$new_quantity, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            return $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
        }
    } catch (PDOException $e) {
        error_log('Add to cart error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Subscribe to newsletter
 */
function subscribeNewsletter($email) {
    global $pdo;
    
    try {
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
