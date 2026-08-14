<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// ============================================
// COMPOSER AUTOLOADER
// ============================================
// Load Composer autoloader for Cloudinary and other dependencies
$autoload_paths = [
    __DIR__ . '/vendor/autoload.php',           // From root folder
    __DIR__ . '/../vendor/autoload.php',        // From subfolder
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php', // From document root
];

$autoload_loaded = false;
foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoload_loaded = true;
        error_log('Composer autoloader loaded from: ' . $path);
        break;
    }
}

if (!$autoload_loaded) {
    error_log('Composer autoloader not found. Please run: composer install');
}

// ============================================
// SESSION CONFIGURATION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
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




// ============================================
// CLOUDINARY CONFIGURATION
// ============================================

// Get Cloudinary credentials from environment variables
define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: '');
define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY') ?: '');
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: '');

// Initialize Cloudinary
$cloudinary = null;

// Check if Cloudinary class exists (autoloader should be loaded)
if (class_exists('Cloudinary\Cloudinary')) {
    // Check if credentials are set
    if (!empty(CLOUDINARY_CLOUD_NAME) && !empty(CLOUDINARY_API_KEY) && !empty(CLOUDINARY_API_SECRET)) {
        try {
            $cloudinary = new Cloudinary\Cloudinary([
                'cloud' => [
                    'cloud_name' => CLOUDINARY_CLOUD_NAME,
                    'api_key'    => CLOUDINARY_API_KEY,
                    'api_secret' => CLOUDINARY_API_SECRET,
                ],
                'url' => [
                    'secure' => true
                ]
            ]);
            error_log('Cloudinary initialized successfully with cloud: ' . CLOUDINARY_CLOUD_NAME);
        } catch (Exception $e) {
            error_log('Cloudinary initialization error: ' . $e->getMessage());
            $cloudinary = null;
        }
    } else {
        error_log('Cloudinary credentials not set. Please set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET environment variables.');
        error_log('Current values: CLOUDINARY_CLOUD_NAME=' . CLOUDINARY_CLOUD_NAME . ', API_KEY=' . (CLOUDINARY_API_KEY ? 'set' : 'not set') . ', API_SECRET=' . (CLOUDINARY_API_SECRET ? 'set' : 'not set'));
    }
} else {
    // Cloudinary not installed, set to null
    $cloudinary = null;
    error_log('Cloudinary class not found. Please run: composer require cloudinary/cloudinary_php');
}

// Log Cloudinary status for debugging
if ($cloudinary) {
    error_log('Cloudinary is ready to use');
} else {
    error_log('Cloudinary is NOT available - using local storage fallback');
}



// ============================================
// SITE CONFIGURATION
// ============================================
define('BASE_URL', 'https://wittymart.onrender.com/'); 
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/');
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

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Check if user has a specific role
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Require user to be logged in (redirect if not)
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: home.php');
        exit();
    }
}

/**
 * Require user to be admin (redirect if not)
 */
function requireAdmin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: home.php');
        exit();
    }
    
    if (!isAdmin()) {
        header('Location: welcome.php');
        exit();
    }
}

/**
 * Get current user data
 */
function getCurrentUser() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, username, role, status, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get current user error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Logout user
 */
function logoutUser() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Redirect to previous page or default
 */
function redirectAfterLogin() {
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
    } else {
        header('Location: welcome.php');
    }
    exit();
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Log activity for audit trail
 */
function logActivity($action, $details, $userId = null, $userName = null) {
    global $pdo;
    
    try {
        // Check if activity_log table exists, if not create it
        $stmt = $pdo->prepare("
            CREATE TABLE IF NOT EXISTS activity_log (
                id SERIAL PRIMARY KEY,
                user_id INT,
                user_name VARCHAR(100),
                action VARCHAR(50),
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute();
        
        // Insert log entry
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, user_name, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        return $stmt->execute([
            $userId ?? ($_SESSION['user_id'] ?? null),
            $userName ?? ($_SESSION['user_name'] ?? 'System'),
            $action,
            $details,
            $ip,
            $userAgent
        ]);
    } catch (PDOException $e) {
        error_log('Log activity error: ' . $e->getMessage());
        return false;
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

$csrf_token = generateCSRFToken();

// ============================================
// NOTE: getProductImageUrl() is now defined in cloudinary_helper.php
// ============================================

// ============================================
// SMART PICKS FUNCTIONS
// ============================================

/**
 * Get smart picks products from the database
 */
function getSmartPicks($limit = 8) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active' OR p.status IS NULL
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get smart picks error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get a specific product by ID
 */
function getProductById($product_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$product_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get product by ID error: ' . $e->getMessage());
        return null;
    }
}

// ============================================
// CART FUNCTIONS
// ============================================

function getCartItems() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.image, p.image_url 
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

// ============================================
// NEWSLETTER FUNCTIONS
// ============================================

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

// ============================================
// ADMIN PRODUCT FUNCTIONS
// ============================================

/**
 * Insert a new product into the database
 */
function insertProduct($name, $description, $price, $category_id, $image = null, $status = 'active') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO products (name, description, price, category_id, image, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$name, $description, $price, $category_id, $image, $status]);
    } catch (PDOException $e) {
        error_log('Insert product error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing product
 */
function updateProduct($id, $name, $description, $price, $category_id, $image = null, $status = 'active') {
    global $pdo;
    
    try {
        if ($image) {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, category_id = ?, image = ?, status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            return $stmt->execute([$name, $description, $price, $category_id, $image, $status, $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, category_id = ?, status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            return $stmt->execute([$name, $description, $price, $category_id, $status, $id]);
        }
    } catch (PDOException $e) {
        error_log('Update product error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a product
 */
function deleteProduct($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log('Delete product error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all products with pagination
 */
function getAllProducts($page = 1, $per_page = 20) {
    global $pdo;
    
    try {
        $offset = ($page - 1) * $per_page;
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$per_page, $offset]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get all products error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get total product count
 */
function getProductCount() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log('Get product count error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get categories for dropdown
 */
function getCategories() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get categories error: ' . $e->getMessage());
        return [];
    }
}
?>
