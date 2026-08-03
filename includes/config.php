<?php

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
    error_log('Database connection failed: ' . $e->getMessage());
    error_log('Connection details - Host: ' . $db_config['host'] . ', DB: ' . $db_config['dbname']);
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
// SITE CONFIGURATION
// ============================================
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

/**
 * Get product image URL
 */
function getProductImage($image_name) {
    if (empty($image_name) || !file_exists(UPLOAD_DIR . $image_name)) {
        return UPLOAD_URL . 'no-image.png';
    }
    return UPLOAD_URL . $image_name;
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
