<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Updating Users Table and Creating Activity Log...</h2>";
    echo "<hr>";

    // ============================================
    // 1. ADD USERNAME COLUMN
    // ============================================
    echo "<h3>1. Adding Username Column...</h3>";
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(50) UNIQUE;");
        echo "<p style='color: green;'>✓ Username column added successfully</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || 
            strpos($e->getMessage(), 'duplicate column') !== false) {
            echo "<p style='color: orange;'>⚠ Username column already exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }

    // ============================================
    // 2. ADD STATUS COLUMN
    // ============================================
    echo "<h3>2. Adding Status Column...</h3>";
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active';");
        echo "<p style='color: green;'>✓ Status column added successfully</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || 
            strpos($e->getMessage(), 'duplicate column') !== false) {
            echo "<p style='color: orange;'>⚠ Status column already exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }

    // ============================================
    // 3. ADD PROFILE_PICTURE COLUMN
    // ============================================
    echo "<h3>3. Adding Profile Picture Column...</h3>";
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255);");
        echo "<p style='color: green;'>✓ Profile picture column added successfully</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || 
            strpos($e->getMessage(), 'duplicate column') !== false) {
            echo "<p style='color: orange;'>⚠ Profile picture column already exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
        }
    }

    // ============================================
    // 4. MODIFY PHONE COLUMN TYPE
    // ============================================
    echo "<h3>4. Modifying Phone Column Type...</h3>";
    try {
        // Check if phone column exists first
        $stmt = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'users' AND column_name = 'phone'
        ");
        
        if ($stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ALTER COLUMN phone TYPE VARCHAR(20);");
            echo "<p style='color: green;'>✓ Phone column type modified successfully</p>";
        } else {
            // If phone column doesn't exist, create it
            $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20);");
            echo "<p style='color: green;'>✓ Phone column created successfully</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Error modifying phone column: " . $e->getMessage() . "</p>";
    }

    // ============================================
    // 5. UPDATE EXISTING USERS WITH USERNAME
    // ============================================
    echo "<h3>5. Updating Existing Users with Username...</h3>";
    try {
        // Check if username column exists before updating
        $stmt = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'users' AND column_name = 'username'
        ");
        
        if ($stmt->fetch()) {
            // For PostgreSQL
            $pdo->exec("
                UPDATE users 
                SET username = SPLIT_PART(email, '@', 1) 
                WHERE username IS NULL;
            ");
            echo "<p style='color: green;'>✓ Existing users updated with username from email</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Username column not found, skipping update</p>";
        }
    } catch (PDOException $e) {
        // Fallback for MySQL
        try {
            $pdo->exec("
                UPDATE users 
                SET username = SUBSTRING_INDEX(email, '@', 1) 
                WHERE username IS NULL;
            ");
            echo "<p style='color: green;'>✓ Existing users updated with username from email (MySQL)</p>";
        } catch (PDOException $e2) {
            echo "<p style='color: red;'>✗ Error updating usernames: " . $e2->getMessage() . "</p>";
        }
    }

    // ============================================
    // 6. CREATE ACTIVITY LOG TABLE
    // ============================================
    echo "<h3>6. Creating Activity Log Table...</h3>";
    try {
        $pdo->exec("
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
        echo "<p style='color: green;'>✓ Activity log table created successfully</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p style='color: orange;'>⚠ Activity log table already exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Error creating activity log table: " . $e->getMessage() . "</p>";
        }
    }

    // ============================================
    // 7. CREATE INDEXES FOR ACTIVITY LOG
    // ============================================
    echo "<h3>7. Creating Indexes for Activity Log...</h3>";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_activity_log_user_id ON activity_log(user_id);",
        "CREATE INDEX IF NOT EXISTS idx_activity_log_action ON activity_log(action);",
        "CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log(created_at);"
    ];
    
    foreach ($indexes as $index_sql) {
        try {
            $pdo->exec($index_sql);
            $index_name = str_replace('CREATE INDEX IF NOT EXISTS ', '', $index_sql);
            $index_name = trim(explode(' ON ', $index_name)[0]);
            echo "<p style='color: green;'>✓ Index '{$index_name}' created successfully</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color: orange;'>⚠ Index already exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Error creating index: " . $e->getMessage() . "</p>";
            }
        }
    }

    // ============================================
    // 8. VERIFY ALL CHANGES
    // ============================================
    echo "<h3>8. Verification...</h3>";
    try {
        $stmt = $pdo->query("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'users' 
            ORDER BY ordinal_position
        ");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p style='color: #05573c; font-weight: bold;'>Users Table Columns:</p>";
        echo "<ul>";
        foreach ($columns as $col) {
            $status = in_array($col['column_name'], ['username', 'status', 'profile_picture', 'phone']) ? '✅' : '';
            echo "<li>{$status} {$col['column_name']} ({$col['data_type']})</li>";
        }
        echo "</ul>";
        
        // Check activity log
        $stmt = $pdo->query("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'activity_log' 
            ORDER BY ordinal_position
        ");
        $log_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($log_columns)) {
            echo "<p style='color: #05573c; font-weight: bold;'>Activity Log Columns:</p>";
            echo "<ul>";
            foreach ($log_columns as $col) {
                echo "<li>✅ {$col['column_name']} ({$col['data_type']})</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠ Activity log table not found</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Error verifying changes: " . $e->getMessage() . "</p>";
    }

    // ============================================
    // SUMMARY
    // ============================================
    echo "<hr>";
    echo "<h3 style='color: green;'>✅ Database update completed successfully!</h3>";
    echo "<ul style='margin-top: 10px;'>";
    echo "<li>✓ Username column added to users table</li>";
    echo "<li>✓ Status column added to users table</li>";
    echo "<li>✓ Profile picture column added to users table</li>";
    echo "<li>✓ Phone column modified to VARCHAR(20)</li>";
    echo "<li>✓ Existing users updated with username from email</li>";
    echo "<li>✓ Activity log table created with proper columns</li>";
    echo "<li>✓ Indexes created for activity log table</li>";
    echo "</ul>";
    
    echo "<p style='margin-top: 20px;'><a href='login-register.php' style='color: #05573c; font-weight: bold;'>← Go to Login/Register Page</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
