<?php
include 'config.php';

// ===== DATABASE FUNCTIONS =====
function getFeaturedProducts($limit = 8) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM products ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getProduct($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getCategories() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM categories ORDER BY name");
    return $stmt->fetchAll();
}

function getOrders() {
    $db = getDB();
    $stmt = $db->query("SELECT o.*, u.name as customer_name 
                         FROM orders o 
                         LEFT JOIN users u ON o.user_id = u.id 
                         ORDER BY o.created_at DESC");
    return $stmt->fetchAll();
}

function getOrder($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT o.*, u.name as customer_name 
                           FROM orders o 
                           LEFT JOIN users u ON o.user_id = u.id 
                           WHERE o.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function updateOrderStatus($id, $status) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $id]);
}

function getUsers() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function getStats() {
    $db = getDB();
    $stats = [];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM products");
    $stats['products'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM orders");
    $stats['orders'] = $stmt->fetch()['count'];
    
    $stmt = $db->query("SELECT COALESCE(SUM(total), 0) as total FROM orders WHERE status != 'cancelled'");
    $stats['revenue'] = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $stats['customers'] = $stmt->fetch()['count'];
    
    return $stats;
}

// ===== AUTHENTICATION FUNCTIONS =====
function login($email, $password) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        redirect('../index.php');
        exit;
    }
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}

function getUserById($id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, phone, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get user error: " . $e->getMessage());
        return null;
    }
}

function updateLastLogin($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Update last login error: " . $e->getMessage());
        return false;
    }
}

function registerUser($name, $email, $password, $phone = null) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'user')");
        $success = $stmt->execute([$name, $email, $hashedPassword, $phone]);
        
        if ($success) {
            return ['success' => true, 'message' => 'Registration successful', 'id' => $db->lastInsertId()];
        }
        return ['success' => false, 'message' => 'Registration failed'];
    } catch (PDOException $e) {
        error_log("Register error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

// ===== HELPER FUNCTIONS =====
function formatPrice($price) {
    return 'Ksh ' . number_format($price, 2);
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

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

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function hasPermission($permission) {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;
    $permissions = $_SESSION['permissions'] ?? [];
    return in_array($permission, $permissions);
}

function debug($data) {
    if (!IS_PRODUCTION) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }
}
