<?php


// ===== IMPORTANT: No output before this point =====

require_once 'config.php';

// ===== REDIRECT FUNCTION =====
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
        // Get user by email - case insensitive
        $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Log the attempt for debugging
        error_log('Login attempt for email: ' . $email . ' - User found: ' . ($user ? 'Yes' : 'No'));
        
        // Verify password
        if ($user && password_verify($password, $user['password'])) {
            // Start session if not already started
            startSession();
            
            // Regenerate session ID to prevent session fixation
            // Check if session is active before regenerating
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            
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
             logActivity(
                'login',
                'User logged in successfully',
                $user['id'],
                $user['name']
            );
            error_log('Login successful for: ' . $email);
            return true;
        }
         logActivity(
            'failed_login',
            'Failed login attempt for email: ' . $email,
            null,
            null
        );
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
    // Start session if not already started
    startSession();
    
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
    // Start session if not already started
    startSession();
    if (isset($_SESSION['user_id'])) {
        logActivity(
            'logout',
            'User logged out',
            $_SESSION['user_id'],
            $_SESSION['user_name'] ?? null
        );
    }
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
 * Log admin actions automatically
 */
function logAdminAction($action, $description = '') {
    if (isset($_SESSION['user_id'])) {
        logActivity(
            $action,
            $description,
            $_SESSION['user_id'],
            $_SESSION['user_name'] ?? null
        );
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
