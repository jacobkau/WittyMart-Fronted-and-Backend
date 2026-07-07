<?php
// admin/test_database.php - Complete Database Diagnostic
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .pass { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #05573c; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #05573c; color: white; }
        tr:hover { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>🔍 Database Diagnostic Tool</h1>
    <p>Testing database: " . date('Y-m-d H:i:s') . "</p>";

// ============================================
// STEP 1: Load Configuration
// ============================================
echo "<div class='section'>";
echo "<h2>Step 1: Loading Configuration</h2>";

$config_path = __DIR__ . '/includes/config.php';
if (!file_exists($config_path)) {
    die("<span class='fail'>❌ config.php not found at: " . $config_path . "</span>");
}

require_once $config_path;
echo "<span class='pass'>✅ config.php loaded</span><br>";

// Check if PDO exists
if (!isset($pdo)) {
    die("<span class='fail'>❌ PDO not defined in config.php</span>");
}
echo "<span class='pass'>✅ PDO exists</span><br>";
echo "</div>";

// ============================================
// STEP 2: Test Database Connection
// ============================================
echo "<div class='section'>";
echo "<h2>Step 2: Database Connection</h2>";

try {
    // Test basic connection
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "<span class='pass'>✅ Database connection successful</span><br>";
    
    // Get database info
    $db_info = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    echo "Connection Status: " . $db_info . "<br>";
    echo "Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "<br>";
    
} catch (PDOException $e) {
    die("<span class='fail'>❌ Database connection failed: " . $e->getMessage() . "</span>");
}
echo "</div>";

// ============================================
// STEP 3: Check Tables
// ============================================
echo "<div class='section'>";
echo "<h2>Step 3: Check Tables</h2>";

$required_tables = [
    'users',
    'products',
    'categories',
    'orders',
    'order_items',
    'cart',
    'activity_logs',
    'featured_products',
    'testimonials',
    'newsletter_subscribers'
];

$existing_tables = [];
$missing_tables = [];

try {
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($existing_tables) . " tables<br>";
    
    foreach ($required_tables as $table) {
        if (in_array($table, $existing_tables)) {
            echo "  - " . $table . ": <span class='pass'>✅ Exists</span><br>";
        } else {
            echo "  - " . $table . ": <span class='fail'>❌ Missing</span><br>";
            $missing_tables[] = $table;
        }
    }
} catch (PDOException $e) {
    echo "<span class='fail'>❌ Failed to get tables: " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// STEP 4: Check Table Structure
// ============================================
echo "<div class='section'>";
echo "<h2>Step 4: Table Structures</h2>";

$tables_to_check = ['users', 'products', 'categories', 'orders'];

foreach ($tables_to_check as $table) {
    if (!in_array($table, $existing_tables)) {
        echo "<span class='fail'>❌ Table '$table' doesn't exist, skipping...</span><br>";
        continue;
    }
    
    echo "<h3>Table: " . $table . "</h3>";
    try {
        $stmt = $pdo->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = '" . $table . "'");
        $columns = $stmt->fetchAll();
        
        if (empty($columns)) {
            echo "  <span class='warning'>⚠️ No columns found</span><br>";
        } else {
            echo "<table>";
            echo "<tr><th>Column</th><th>Type</th><th>Nullable</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($col['column_name']) . "</td>";
                echo "<td>" . htmlspecialchars($col['data_type']) . "</td>";
                echo "<td>" . htmlspecialchars($col['is_nullable']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<span class='fail'>❌ Failed to get columns: " . $e->getMessage() . "</span><br>";
    }
}
echo "</div>";

// ============================================
// STEP 5: Check Data
// ============================================
echo "<div class='section'>";
echo "<h2>Step 5: Data Counts</h2>";

$tables_count = ['users', 'products', 'categories', 'orders', 'featured_products', 'testimonials'];

foreach ($tables_count as $table) {
    if (!in_array($table, $existing_tables)) {
        echo $table . ": <span class='fail'>❌ Table doesn't exist</span><br>";
        continue;
    }
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM " . $table);
        $count = $stmt->fetch()['count'];
        echo $table . ": <span class='" . ($count > 0 ? 'pass' : 'warning') . "'>" . $count . " records</span><br>";
    } catch (PDOException $e) {
        echo $table . ": <span class='fail'>❌ Error: " . $e->getMessage() . "</span><br>";
    }
}
echo "</div>";

// ============================================
// STEP 6: Test User Authentication
// ============================================
echo "<div class='section'>";
echo "<h2>Step 6: User Authentication Test</h2>";

try {
    // Check if users table exists
    if (!in_array('users', $existing_tables)) {
        echo "<span class='fail'>❌ Users table doesn't exist</span><br>";
    } else {
        // Count users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $user_count = $stmt->fetch()['count'];
        echo "Total users: " . $user_count . "<br>";
        
        // Test if we can query users
        $stmt = $pdo->query("SELECT id, name, email, role FROM users LIMIT 3");
        $users = $stmt->fetchAll();
        
        if (!empty($users)) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                echo "<td>" . htmlspecialchars($user['name']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Check if admin user exists
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' OR role = 'super_admin'");
            $admin_count = $stmt->fetch()['count'];
            echo "Admin users: " . $admin_count . "<br>";
            
            if ($admin_count == 0) {
                echo "<span class='warning'>⚠️ No admin users found! You need to create one.</span><br>";
            }
        } else {
            echo "<span class='warning'>⚠️ No users found in the database</span><br>";
        }
    }
} catch (PDOException $e) {
    echo "<span class='fail'>❌ User query failed: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// ============================================
// STEP 7: Test Featured Products
// ============================================
echo "<div class='section'>";
echo "<h2>Step 7: Featured Products Test</h2>";

try {
    if (!in_array('featured_products', $existing_tables)) {
        echo "<span class='warning'>⚠️ Featured products table doesn't exist</span><br>";
        echo "Create it with:<br>";
        echo "<pre>
CREATE TABLE featured_products (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
        </pre>";
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM featured_products");
        $count = $stmt->fetch()['count'];
        echo "Featured products: " . $count . "<br>";
    }
} catch (PDOException $e) {
    echo "<span class='fail'>❌ Error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// ============================================
// STEP 8: Test Testimonials
// ============================================
echo "<div class='section'>";
echo "<h2>Step 8: Testimonials Test</h2>";

try {
    if (!in_array('testimonials', $existing_tables)) {
        echo "<span class='warning'>⚠️ Testimonials table doesn't exist</span><br>";
        echo "Create it with:<br>";
        echo "<pre>
CREATE TABLE testimonials (
    id SERIAL PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_image VARCHAR(255),
    content TEXT NOT NULL,
    rating INTEGER DEFAULT 5,
    status VARCHAR(20) DEFAULT 'active',
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
        </pre>";
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM testimonials WHERE status = 'active'");
        $count = $stmt->fetch()['count'];
        echo "Active testimonials: " . $count . "<br>";
    }
} catch (PDOException $e) {
    echo "<span class='fail'>❌ Error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// ============================================
// STEP 9: Session Test
// ============================================
echo "<div class='section'>";
echo "<h2>Step 9: Session Test</h2>";

echo "Session Status: ";
$status = session_status();
if ($status === PHP_SESSION_ACTIVE) {
    echo "<span class='pass'>✅ Active</span><br>";
    echo "Session ID: " . session_id() . "<br>";
    echo "Session Data:<br>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
} else {
    echo "<span class='fail'>❌ Not Active</span><br>";
}
echo "</div>";

// ============================================
// STEP 10: SQL Error Log
// ============================================
echo "<div class='section'>";
echo "<h2>Step 10: Recent Database Errors</h2>";

try {
    // Check if activity_logs table exists
    if (in_array('activity_logs', $existing_tables)) {
        $stmt = $pdo->query("SELECT * FROM activity_logs WHERE action = 'failed_login' OR action LIKE '%error%' ORDER BY created_at DESC LIMIT 5");
        $errors = $stmt->fetchAll();
        
        if (!empty($errors)) {
            echo "<table>";
            echo "<tr><th>Time</th><th>User</th><th>Action</th><th>Description</th></tr>";
            foreach ($errors as $error) {
                echo "<tr>";
                echo "<td>" . date('Y-m-d H:i:s', strtotime($error['created_at'])) . "</td>";
                echo "<td>" . htmlspecialchars($error['user_name'] ?? 'System') . "</td>";
                echo "<td>" . htmlspecialchars($error['action']) . "</td>";
                echo "<td>" . htmlspecialchars($error['description']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No recent errors logged.<br>";
        }
    } else {
        echo "Activity logs table doesn't exist.<br>";
    }
} catch (PDOException $e) {
    echo "Could not check error logs: " . $e->getMessage() . "<br>";
}
echo "</div>";

// ============================================
// SUMMARY
// ============================================
echo "<div class='section'>";
echo "<h2>Summary</h2>";

$issues = 0;
$warnings = 0;

// Check users
if (!in_array('users', $existing_tables)) {
    $issues++;
    echo "<span class='fail'>❌ Users table missing</span><br>";
}

if (!in_array('products', $existing_tables)) {
    $issues++;
    echo "<span class='fail'>❌ Products table missing</span><br>";
}

if (!in_array('categories', $existing_tables)) {
    $issues++;
    echo "<span class='fail'>❌ Categories table missing</span><br>";
}

// Check featured products
if (!in_array('featured_products', $existing_tables)) {
    $warnings++;
    echo "<span class='warning'>⚠️ Featured products table missing (optional)</span><br>";
}

if (!in_array('testimonials', $existing_tables)) {
    $warnings++;
    echo "<span class='warning'>⚠️ Testimonials table missing (optional)</span><br>";
}

if ($issues == 0 && $warnings == 0) {
    echo "<h3 style='color: green;'>✅ All database tables are present!</h3>";
} elseif ($issues == 0) {
    echo "<h3 style='color: orange;'>⚠️ Database is functional but missing optional tables</h3>";
} else {
    echo "<h3 style='color: red;'>❌ Database has " . $issues . " critical issues that need fixing</h3>";
}

echo "</div>";

// ============================================
// FINAL
// ============================================
echo "<p><strong>✅ Database diagnostic complete!</strong></p>";
echo "</body></html>";
?>
