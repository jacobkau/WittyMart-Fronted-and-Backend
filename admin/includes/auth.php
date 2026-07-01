<?php
require_once 'config.php';

// ===== LOGIN FUNCTION =====
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

// ===== LOGOUT FUNCTION =====
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



// ===== CHECK IF USER IS ADMIN =====
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// ===== REQUIRE LOGIN =====
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
        exit;
    }
}

// ===== REQUIRE ADMIN =====
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        redirect('../index.php');
        exit;
    }
}

// ===== GET CURRENT USER =====
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

// ===== REDIRECT FUNCTION =====
function redirect($url) {
    header("Location: " . $url);
    exit;
}

// ===== SANITIZE INPUT =====
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// ===== GENERATE CSRF TOKEN =====
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ===== VERIFY CSRF TOKEN =====
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===== CHECK USER PERMISSION =====
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Admin has all permissions
    if (isAdmin()) {
        return true;
    }
    
    // Check specific permissions (for future use)
    $permissions = $_SESSION['permissions'] ?? [];
    return in_array($permission, $permissions);
}

// ===== GET USER BY ID =====
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

// ===== UPDATE USER LAST LOGIN =====
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

// ===== REGISTER NEW USER =====
function registerUser($name, $email, $password, $phone = null) {
    try {
        $db = getDB();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
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
