<?php
// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

require_once 'config.php';

/**
 * Login user
 */
function login($email, $password) {
    global $pdo;
    
    try {
        // Get user by email
        $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Verify password
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Update last login timestamp
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return true;
        }
        
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
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    // Check session timeout
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
    }
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

/**
 * Logout user
 */
function logout() {
    // Unset all session variables
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy session
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
 * Check if email already exists
 */
function emailExists($email, $excludeId = null) {
    global $pdo;
    
    try {
        $sql = "SELECT id FROM users WHERE email = ?";
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
 * Create user (for registration)
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
 * Log login attempt (for security monitoring)
 */
function logLoginAttempt($email, $success) {
    // You can implement logging to a table if needed
    error_log(sprintf(
        'Login attempt - Email: %s, Success: %s, IP: %s, Time: %s',
        $email,
        $success ? 'Yes' : 'No',
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        date('Y-m-d H:i:s')
    ));
}

/**
 * Check if user has permission
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Define role permissions
    $permissions = [
        'admin' => ['all'],
        'user' => ['view_products', 'view_orders', 'create_orders'],
    ];
    
    $role = $_SESSION['user_role'] ?? 'user';
    
    return isset($permissions[$role]) && 
           (in_array('all', $permissions[$role]) || in_array($permission, $permissions[$role]));
}
?>
