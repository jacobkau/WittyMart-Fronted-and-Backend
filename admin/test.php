<?php
require_once 'includes/config.php';

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Admin access required');
}

// Get all tables
$tables = [];
try {
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_type = 'BASE TABLE'
        ORDER BY table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('Get tables error: ' . $e->getMessage());
    die('Error fetching tables: ' . $e->getMessage());
}

// Get table information
$tableInfo = [];
foreach ($tables as $table) {
    try {
        // Get columns
        $stmt = $pdo->prepare("
            SELECT 
                column_name,
                data_type,
                character_maximum_length,
                is_nullable,
                column_default,
                data_type,
                udt_name
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
            AND table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$table]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get primary key
        $stmt = $pdo->prepare("
            SELECT c.column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.constraint_column_usage ccu 
                ON tc.constraint_name = ccu.constraint_name
            WHERE tc.constraint_type = 'PRIMARY KEY'
            AND tc.table_schema = 'public'
            AND tc.table_name = ?
        ");
        $stmt->execute([$table]);
        $primaryKey = $stmt->fetch(PDO::FETCH_COLUMN);
        
        // Get row count
        $stmt = $pdo->query("SELECT COUNT(*) FROM \"$table\"");
        $rowCount = $stmt->fetchColumn();
        
        $tableInfo[$table] = [
            'columns' => $columns,
            'primary_key' => $primaryKey,
            'row_count' => $rowCount
        ];
    } catch (PDOException $e) {
        error_log('Error getting table info for ' . $table . ': ' . $e->getMessage());
        $tableInfo[$table] = [
            'columns' => [],
            'primary_key' => null,
            'row_count' => 0,
            'error' => $e->getMessage()
        ];
    }
}

// Get table relationships (foreign keys)
$relationships = [];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                kcu.column_name,
                ccu.table_name AS foreign_table_name,
                ccu.column_name AS foreign_column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_schema = 'public'
            AND tc.table_name = ?
        ");
        $stmt->execute([$table]);
        $rels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rels)) {
            $relationships[$table] = $rels;
        }
    } catch (PDOException $e) {
        // Skip foreign key errors
    }
}

// Helper function to format data type
function formatDataType($column) {
    $type = $column['data_type'] ?? $column['udt_name'] ?? 'unknown';
    $length = $column['character_maximum_length'] ?? null;
    
    if ($length) {
        return $type . '(' . $length . ')';
    }
    return $type;
}

// Helper function to get badge color for data type
function getDataTypeBadge($type) {
    $badges = [
        'integer' => 'badge-primary',
        'bigint' => 'badge-primary',
        'smallint' => 'badge-primary',
        'serial' => 'badge-primary',
        'bigserial' => 'badge-primary',
        'varchar' => 'badge-success',
        'text' => 'badge-success',
        'char' => 'badge-success',
        'boolean' => 'badge-warning',
        'bool' => 'badge-warning',
        'decimal' => 'badge-info',
        'numeric' => 'badge-info',
        'float' => 'badge-info',
        'double' => 'badge-info',
        'date' => 'badge-secondary',
        'time' => 'badge-secondary',
        'timestamp' => 'badge-secondary',
        'json' => 'badge-dark',
        'jsonb' => 'badge-dark',
        'uuid' => 'badge-purple'
    ];
    
    $type = strtolower($type);
    foreach ($badges as $key => $badge) {
        if (strpos($type, $key) !== false) {
            return $badge;
        }
    }
    return 'badge-light';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Structure Checker - WittyMart</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f5f6fa;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #05573c, #0a7a54);
            color: #fff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .header .stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .header .stats .stat {
            background: rgba(255,255,255,0.15);
            padding: 10px 20px;
            border-radius: 8px;
        }
        
        .header .stats .stat strong {
            font-size: 22px;
            display: block;
        }
        
        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 2px solid #e9ecef;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }
        
        .table-header:hover {
            background: #e9ecef;
        }
        
        .table-header h2 {
            font-size: 18px;
            color: #05573c;
        }
        
        .table-header .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-primary { background: #007bff; color: #fff; }
        .badge-success { background: #28a745; color: #fff; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; color: #fff; }
        .badge-secondary { background: #6c757d; color: #fff; }
        .badge-dark { background: #343a40; color: #fff; }
        .badge-purple { background: #6f42c1; color: #fff; }
        .badge-light { background: #f8f9fa; color: #333; }
        .badge-danger { background: #dc3545; color: #fff; }
        
        .table-content {
            padding: 20px;
            display: none;
        }
        
        .table-content.open {
            display: block;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        table th {
            background: #f8f9fa;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }
        
        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .column-name {
            font-weight: 600;
            color: #05573c;
        }
        
        .primary-key {
            display: inline-block;
            background: #ffd700;
            color: #333;
            font-size: 9px;
            padding: 1px 6px;
            border-radius: 10px;
            font-weight: 700;
            margin-left: 5px;
        }
        
        .nullable {
            color: #6c757d;
            font-size: 12px;
        }
        
        .not-null {
            color: #dc3545;
            font-size: 12px;
            font-weight: 600;
        }
        
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #05573c;
        }
        
        .search-box .btn {
            padding: 10px 25px;
            background: #05573c;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .search-box .btn:hover {
            background: #03402c;
        }
        
        .row-count {
            font-size: 13px;
            color: #6c757d;
            margin-left: 10px;
        }
        
        .relationship {
            background: #e7f3ff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-top: 10px;
        }
        
        .relationship i {
            color: #007bff;
            margin-right: 5px;
        }
        
        .export-btn {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.3s;
        }
        
        .export-btn:hover {
            background: #1e7e34;
        }
        
        @media (max-width: 768px) {
            .header .stats {
                flex-direction: column;
                gap: 10px;
            }
            
            table {
                font-size: 12px;
            }
            
            table th, table td {
                padding: 6px 8px;
            }
            
            .table-header h2 {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-database"></i> Database Structure Checker</h1>
            <p>View all tables, columns, and relationships in your WittyMart database</p>
            <div class="stats">
                <div class="stat">
                    <strong><?php echo count($tables); ?></strong>
                    Tables
                </div>
                <div class="stat">
                    <strong><?php 
                        $totalColumns = 0;
                        foreach ($tableInfo as $info) {
                            $totalColumns += count($info['columns']);
                        }
                        echo $totalColumns;
                    ?></strong>
                    Columns
                </div>
                <div class="stat">
                    <strong><?php 
                        $totalRows = 0;
                        foreach ($tableInfo as $info) {
                            $totalRows += $info['row_count'];
                        }
                        echo number_format($totalRows);
                    ?></strong>
                    Total Records
                </div>
            </div>
        </div>
        
        <!-- Search -->
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search for tables or columns..." onkeyup="searchTables()">
            <button class="btn" onclick="expandAll()"><i class="fas fa-expand"></i> Expand All</button>
            <button class="btn" onclick="collapseAll()"><i class="fas fa-compress"></i> Collapse All</button>
            <button class="export-btn" onclick="exportData()"><i class="fas fa-download"></i> Export JSON</button>
        </div>
        
        <!-- Tables -->
        <?php foreach ($tables as $table): ?>
            <?php 
            $info = $tableInfo[$table] ?? ['columns' => [], 'row_count' => 0];
            $columns = $info['columns'] ?? [];
            $primaryKey = $info['primary_key'] ?? null;
            $rowCount = $info['row_count'] ?? 0;
            $hasError = isset($info['error']);
            ?>
            
            <div class="table-container" data-table="<?php echo strtolower($table); ?>">
                <div class="table-header" onclick="toggleTable(this)">
                    <div>
                        <h2>
                            <i class="fas fa-table"></i> 
                            <?php echo htmlspecialchars($table); ?>
                            <span class="row-count">(<?php echo number_format($rowCount); ?> records)</span>
                        </h2>
                    </div>
                    <div>
                        <span class="badge badge-secondary"><?php echo count($columns); ?> columns</span>
                        <i class="fas fa-chevron-down" style="margin-left:10px;color:#6c757d;"></i>
                    </div>
                </div>
                
                <div class="table-content">
                    <?php if ($hasError): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            Error: <?php echo htmlspecialchars($info['error']); ?>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Column</th>
                                    <th>Type</th>
                                    <th>Nullable</th>
                                    <th>Default</th>
                                    <th>PK</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($columns as $column): ?>
                                    <tr>
                                        <td class="column-name">
                                            <?php echo htmlspecialchars($column['column_name']); ?>
                                            <?php if ($primaryKey && $column['column_name'] === $primaryKey): ?>
                                                <span class="primary-key">PK</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getDataTypeBadge(formatDataType($column)); ?>">
                                                <?php echo htmlspecialchars(formatDataType($column)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($column['is_nullable'] === 'YES'): ?>
                                                <span class="nullable"><i class="fas fa-circle" style="color:#6c757d;font-size:8px;"></i> Nullable</span>
                                            <?php else: ?>
                                                <span class="not-null"><i class="fas fa-circle" style="color:#dc3545;font-size:8px;"></i> NOT NULL</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $default = $column['column_default'] ?? '';
                                            echo !empty($default) ? htmlspecialchars($default) : '<span style="color:#999;">—</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($primaryKey && $column['column_name'] === $primaryKey): ?>
                                                <i class="fas fa-key" style="color:#ffd700;"></i>
                                            <?php else: ?>
                                                <span style="color:#999;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Relationships -->
                        <?php if (isset($relationships[$table])): ?>
                            <div class="relationship">
                                <i class="fas fa-link"></i>
                                <strong>Foreign Keys:</strong>
                                <?php foreach ($relationships[$table] as $rel): ?>
                                    <?php echo htmlspecialchars($rel['column_name']); ?> 
                                    → 
                                    <strong><?php echo htmlspecialchars($rel['foreign_table_name']); ?></strong>.
                                    <?php echo htmlspecialchars($rel['foreign_column_name']); ?>
                                    <?php if (next($relationships[$table])): ?>, <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        // ============================================
        // TOGGLE TABLE EXPAND/COLLAPSE
        // ============================================
        function toggleTable(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.fa-chevron-down');
            
            if (content.classList.contains('open')) {
                content.classList.remove('open');
                icon.style.transform = 'rotate(0deg)';
            } else {
                content.classList.add('open');
                icon.style.transform = 'rotate(180deg)';
            }
        }
        
        function expandAll() {
            document.querySelectorAll('.table-content').forEach(content => {
                content.classList.add('open');
                const icon = content.previousElementSibling.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(180deg)';
            });
        }
        
        function collapseAll() {
            document.querySelectorAll('.table-content').forEach(content => {
                content.classList.remove('open');
                const icon = content.previousElementSibling.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(0deg)';
            });
        }
        
        // ============================================
        // SEARCH TABLES
        // ============================================
        function searchTables() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const containers = document.querySelectorAll('.table-container');
            
            containers.forEach(container => {
                const tableName = container.dataset.table;
                const columns = container.querySelectorAll('.column-name');
                let found = false;
                
                // Check table name
                if (tableName.includes(searchTerm)) {
                    found = true;
                }
                
                // Check column names
                columns.forEach(col => {
                    if (col.textContent.toLowerCase().includes(searchTerm)) {
                        found = true;
                    }
                });
                
                container.style.display = found ? '' : 'none';
                
                // Auto-expand if found
                if (found && searchTerm.length > 1) {
                    const content = container.querySelector('.table-content');
                    const icon = container.querySelector('.fa-chevron-down');
                    if (content) content.classList.add('open');
                    if (icon) icon.style.transform = 'rotate(180deg)';
                }
            });
        }
        
        // ============================================
        // EXPORT DATA AS JSON
        // ============================================
        function exportData() {
            const data = {
                generated: new Date().toISOString(),
                tables: <?php echo json_encode($tableInfo, JSON_PRETTY_PRINT); ?>,
                relationships: <?php echo json_encode($relationships, JSON_PRETTY_PRINT); ?>
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'database_structure_' + new Date().toISOString().slice(0,10) + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        // ============================================
        // EXPAND ALL ON LOAD
        // ============================================
        // Expand the first table by default
        document.addEventListener('DOMContentLoaded', function() {
            const firstTable = document.querySelector('.table-content');
            if (firstTable) {
                firstTable.classList.add('open');
                const icon = firstTable.previousElementSibling.querySelector('.fa-chevron-down');
                if (icon) icon.style.transform = 'rotate(180deg)';
            }
        });
    </script>
</body>
</html>
