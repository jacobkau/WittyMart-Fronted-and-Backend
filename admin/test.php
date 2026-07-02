<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php'; // Your PDO connection

try {

    echo "<h1>Database Schema</h1>";

    // Get all user tables
    $tables = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema='public'
        AND table_type='BASE TABLE'
        ORDER BY table_name
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {

        echo "<hr>";
        echo "<h2>Table: {$table}</h2>";

        $stmt = $pdo->prepare("
            SELECT
                c.column_name,
                c.ordinal_position,
                c.data_type,
                c.character_maximum_length,
                c.numeric_precision,
                c.numeric_scale,
                c.is_nullable,
                c.column_default,

                CASE
                    WHEN tc.constraint_type='PRIMARY KEY'
                    THEN 'YES'
                    ELSE ''
                END AS primary_key

            FROM information_schema.columns c

            LEFT JOIN information_schema.key_column_usage kcu
                ON c.table_name = kcu.table_name
                AND c.column_name = kcu.column_name

            LEFT JOIN information_schema.table_constraints tc
                ON kcu.constraint_name = tc.constraint_name
                AND tc.constraint_type='PRIMARY KEY'

            WHERE c.table_schema='public'
            AND c.table_name=?

            ORDER BY c.ordinal_position
        ");

        $stmt->execute([$table]);

        echo "<table border='1' cellpadding='8' cellspacing='0'>";
        echo "<tr>
                <th>#</th>
                <th>Column</th>
                <th>Type</th>
                <th>Length</th>
                <th>Nullable</th>
                <th>Default</th>
                <th>Primary Key</th>
              </tr>";

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $length = $row['character_maximum_length'];

            if (!$length && $row['numeric_precision']) {
                $length = $row['numeric_precision'];

                if ($row['numeric_scale']) {
                    $length .= "," . $row['numeric_scale'];
                }
            }

            echo "<tr>";
            echo "<td>{$row['ordinal_position']}</td>";
            echo "<td>{$row['column_name']}</td>";
            echo "<td>{$row['data_type']}</td>";
            echo "<td>{$length}</td>";
            echo "<td>{$row['is_nullable']}</td>";
            echo "<td>" . htmlspecialchars($row['column_default'] ?? '') . "</td>";
            echo "<td>{$row['primary_key']}</td>";
            echo "</tr>";
        }

        echo "</table>";

        // Foreign Keys
        $fk = $pdo->prepare("
            SELECT
                kcu.column_name,
                ccu.table_name AS references_table,
                ccu.column_name AS references_column

            FROM information_schema.table_constraints tc

            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name

            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name

            WHERE tc.constraint_type='FOREIGN KEY'
            AND tc.table_name=?
        ");

        $fk->execute([$table]);

        $foreignKeys = $fk->fetchAll(PDO::FETCH_ASSOC);

        if ($foreignKeys) {

            echo "<br><strong>Foreign Keys</strong>";

            echo "<ul>";

            foreach ($foreignKeys as $key) {

                echo "<li>
                        {$key['column_name']}
                        →
                        {$key['references_table']}.{$key['references_column']}
                      </li>";
            }

            echo "</ul>";
        }

    }

} catch (PDOException $e) {

    echo "<h3>Database Error</h3>";
    echo $e->getMessage();
}
