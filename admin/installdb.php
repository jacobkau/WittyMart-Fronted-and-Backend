<?php
require_once 'includes/config.php';

try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<h2>Creating Database Tables...</h2>";

    // SQL statements to create tables
    $sqls = [
"ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL;"
    ];

    // Execute using PDO
    foreach ($sqls as $sql) {
        try {
            $result = $pdo->exec($sql);
            echo "<p style='color: green;'>✓ Table updated successfully</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p style='color: orange;'>⚠ Column already exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
            }
        }
    }    

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
