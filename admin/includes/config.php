<?php
// includes/config.php - Simplified

// ===== ENVIRONMENT =====
$environment = getenv('APP_ENV') ?: 'development';
define('APP_ENV', $environment);
define('IS_PRODUCTION', $environment === 'production');

// ===== SESSION SETTINGS (BEFORE SESSION START) =====
if (IS_PRODUCTION) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
}

// ===== START SESSION =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== ERROR REPORTING =====
if (IS_PRODUCTION) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// ===== TIMEZONE =====
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Nairobi');

// ===== DATABASE =====
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    $db_parts = parse_url($database_url);
    define('DB_HOST', $db_parts['host']);
    define('DB_PORT', $db_parts['port'] ?? 5432);
    define('DB_NAME', ltrim($db_parts['path'], '/'));
    define('DB_USER', $db_parts['user']);
    define('DB_PASS', $db_parts['pass']);
} else {
    define('DB_HOST', 'localhost');
    define('DB_PORT', 5432);
    define('DB_NAME', 'wittymart');
    define('DB_USER', 'postgres');
    define('DB_PASS', '');
}

// ===== APP SETTINGS =====
define('SITE_NAME', getenv('SITE_NAME') ?: 'WittyMart');
define('SITE_URL', getenv('SITE_URL') ?: 'https://wittymart.onrender.com/');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@wittymart.com');

// ===== DATABASE FUNCTION =====
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s;", DB_HOST, DB_PORT, DB_NAME);
            
            if (IS_PRODUCTION) {
                $dsn .= "sslmode=require";
            }
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 30,
            ]);
            
        } catch (PDOException $e) {
            if (IS_PRODUCTION) {
                error_log("DB Error: " . $e->getMessage());
                die("Database connection failed.");
            } else {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    }
    
    return $pdo;
}

function testDatabaseConnection() {
    try {
        getDB()->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
