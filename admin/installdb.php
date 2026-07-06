<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Creating Database Tables...</h2>";

    // SQL statements to create tables
    $sqls = [
"CREATE TABLE IF NOT EXISTS featured_products (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)",
"CREATE TABLE IF NOT EXISTS testimonials (
    id SERIAL PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_image VARCHAR(255),
    content TEXT NOT NULL,
    rating INTEGER DEFAULT 5,
    status VARCHAR(20) DEFAULT 'active',
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"INSERT INTO testimonials (customer_name, content, rating, display_order) VALUES
('Janet K.', 'WittyMart has completely changed how I shop. Fast delivery, great prices!', 5, 1),
('David M.', 'High quality and fantastic customer service. Highly recommended!', 5, 2),
('Achieng\' O.', 'Nimefurahia huduma yenu! Vitu vilifika kwa wakati na ziko safi.', 5, 3),
('Brian Ochieng', 'Affordable and authentic products. Naipenda WittyMart sana!', 4, 4),
('Mercy W.', 'Customer care ilinishughulikia haraka. Siwezi nunua kwingine tena.', 5, 5),
('Kevin Mwangi', 'The variety and convenience are unmatched. Asante sana!', 5, 6)"

    ];

    // Execute using PDO
    foreach ($sqls as $sql) {
        try {
            $result = $pdo->exec($sql);
            echo "<p style='color: green;'>✓ Tables created successfully</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color: orange;'>⚠ Table already exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
            }
        }
    }

    // Verify tables
    echo "<h3>Verification:</h3>";
    $tables = ['featured_products', 'testimonials'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = '$table')");
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                // Get row count
                $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $countStmt->fetchColumn();
                echo "<p style='color: green;'>✓ Table '$table' exists ($count records)</p>";
            } else {
                echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Error checking '$table': " . $e->getMessage() . "</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
