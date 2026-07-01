<?php

// ===== DATABASE SETTINGS =====
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    $db_parts = parse_url($database_url);
    
    define('DB_HOST', $db_parts['host']);
    define('DB_PORT', $db_parts['port'] ?? 5432);
    define('DB_NAME', ltrim($db_parts['path'], '/'));
    define('DB_USER', $db_parts['user']);
    define('DB_PASS', $db_parts['pass']);
} else {
    // Fallback for local development
    define('DB_HOST', 'localhost');
    define('DB_PORT', 5432);
    define('DB_NAME', 'wittymart');
    define('DB_USER', 'postgres');
    define('DB_PASS', '');
}

// ===== APPLICATION SETTINGS =====
define('SITE_NAME', getenv('SITE_NAME') ?: 'WittyMart');
define('SITE_URL', getenv('SITE_URL') ?: 'https://wittymart.onrender.com/');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@wittymart.com');

// ===== ENVIRONMENT SETTINGS =====
$environment = getenv('APP_ENV') ?: 'development';
define('APP_ENV', $environment);
define('IS_PRODUCTION', $environment === 'production');


// ===== TIMEZONE =====
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Nairobi');

// ===== DATABASE CONNECTION FUNCTION (PostgreSQL) =====
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // Build DSN for PostgreSQL
            $dsn = sprintf(
                "pgsql:host=%s;port=%s;dbname=%s;",
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
            
            // Connection options
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 30,
            ];
            
            // Simple SSL - NO CA file needed (disable verification)
            if (IS_PRODUCTION) {
                $dsn .= "sslmode=require";
                // OR use this if you have issues:
                // $dsn .= "sslmode=require&sslverify=false";
            }
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            if (IS_PRODUCTION) {
                error_log("Database connection failed: " . $e->getMessage());
                die("Unable to connect to database. Please try again later.");
            } else {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    }
    
    return $pdo;
}

// ===== DATABASE CONNECTION TEST =====
function testDatabaseConnection() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ===== HELPER FUNCTIONS =====
function env($key, $default = null) {
    $value = getenv($key);
    return ($value === false) ? $default : $value;
}

function isProduction() {
    return IS_PRODUCTION;
}

function getEnvironment() {
    return APP_ENV;
}

function debug($data) {
    if (!IS_PRODUCTION) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }
}

// ===== HEALTH CHECK =====
if (php_sapi_name() === 'cli' || (isset($_GET['health']) && $_GET['health'] === '1')) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'healthy',
        'database' => testDatabaseConnection() ? 'connected' : 'disconnected',
        'environment' => APP_ENV,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}
