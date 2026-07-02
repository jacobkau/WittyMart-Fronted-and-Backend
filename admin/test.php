<?php
require_once 'includes/config.php'; 

try {
    $sql = "
        SELECT tablename
        FROM pg_tables
        WHERE schemaname = 'public'
        ORDER BY tablename;
    ";

    $stmt = $pdo->query($sql);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($tables) {
        echo "<h2>Database Tables</h2>";
        echo "<ul>";

        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table['tablename']) . "</li>";
        }

        echo "</ul>";
    } else {
        echo "No tables found.";
    }

} catch (PDOException $e) {
    echo "Database Error: " . htmlspecialchars($e->getMessage());
}
?>
