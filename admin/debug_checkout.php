<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

echo "<!DOCTYPE html><html><head><title>Checkout Debug</title>";
echo "<style>body{font-family:monospace;background:#111;color:#0f0;padding:20px;line-height:1.6;font-size:14px}";
echo "h2{color:#ff0;} h3{color:#0ff;margin-top:24px;} .err{color:#f66;} .ok{color:#0f0;} .warn{color:#fa0;}";
echo "pre{background:#000;padding:12px;border-radius:6px;overflow-x:auto;border:1px solid #333;}";
echo "table{border-collapse:collapse;margin-top:8px;} td,th{border:1px solid #333;padding:4px 10px;text-align:left;}";
echo "th{background:#222;color:#0ff;}</style></head><body>";

echo "<h2>🔍 Checkout Schema Debug</h2>";
echo "<p>Server time: " . date('Y-m-d H:i:s') . "</p>";

// ============================================
// 1. Test DB connection
// ============================================
echo "<h3>1. Database Connection</h3>";
try {
    $test = $pdo->query("SELECT 1")->fetchColumn();
    echo "<span class='ok'>✅ Connected (result: $test)</span>";
} catch (Exception $e) {
    echo "<span class='err'>❌ Connection failed: " . htmlspecialchars($e->getMessage()) . "</span>";
    exit();
}

// ============================================
// 2. Get columns for each critical table
// ============================================
$tables = ['orders', 'order_items', 'products', 'cart', 'user_addresses', 'coupons'];

foreach ($tables as $table) {
    echo "<h3>2. Table: <code>$table</code></h3>";
    try {
        $stmt = $pdo->prepare("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$table]);
        $cols = $stmt->fetchAll();

        if (empty($cols)) {
            echo "<span class='err'>❌ Table does not exist!</span>";
            continue;
        }

        echo "<table><tr><th>Column</th><th>Type</th><th>Nullable</th><th>Default</th></tr>";
        foreach ($cols as $c) {
            echo "<tr><td>{$c['column_name']}</td><td>{$c['data_type']}</td><td>{$c['is_nullable']}</td><td>" . htmlspecialchars($c['column_default'] ?? '—') . "</td></tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<span class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
}

// ============================================
// 3. Test the exact INSERT that checkout.php runs
// ============================================
echo "<h3>3. Test INSERT into orders (rollback, no data saved)</h3>";

// Required columns from your checkout.php INSERT
$required_order_columns = [
    'user_id', 'order_number', 'total', 'shipping_fee', 'status',
    'payment_method', 'payment_status', 'shipping_address', 'shipping_city',
    'delivery_instructions', 'delivery_county', 'delivery_phone',
    'delivery_recipient', 'address_id', 'mpesa_phone', 'created_at',
];

// Get actual columns
$stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'orders'");
$actual_order_columns = array_column($stmt->fetchAll(), 'column_name');

$missing = array_diff($required_order_columns, $actual_order_columns);
$extra   = array_diff($actual_order_columns, $required_order_columns);

if (empty($missing)) {
    echo "<span class='ok'>✅ All required columns present on orders.</span>";
} else {
    echo "<span class='err'>❌ MISSING columns on orders: " . implode(', ', $missing) . "</span>";
    echo "<p>Run:</p><pre>";
    foreach ($missing as $col) {
        echo "ALTER TABLE orders ADD COLUMN IF NOT EXISTS $col " . guess_type($col) . ";\n";
    }
    echo "</pre>";
}

if (!empty($extra)) {
    echo "<span class='warn'>ℹ️ Extra columns on orders (not used by checkout): " . implode(', ', $extra) . "</span>";
}

// ============================================
// 4. Test order_items required columns
// ============================================
echo "<h3>4. Test INSERT into order_items</h3>";

$required_items_columns = ['order_id', 'product_id', 'product_name', 'quantity', 'price', 'total'];
$stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'order_items'");
$actual_items_columns = array_column($stmt->fetchAll(), 'column_name');

$missing_items = array_diff($required_items_columns, $actual_items_columns);

if (empty($missing_items)) {
    echo "<span class='ok'>✅ All required columns present on order_items.</span>";
} else {
    echo "<span class='err'>❌ MISSING columns on order_items: " . implode(', ', $missing_items) . "</span>";
    echo "<p>Run:</p><pre>";
    foreach ($missing_items as $col) {
        echo "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS $col " . guess_type($col) . ";\n";
    }
    echo "</pre>";
}

// ============================================
// 5. Dry-run the INSERT (rollback)
// ============================================
echo "<h3>5. Dry-run the checkout INSERT</h3>";
try {
    $pdo->beginTransaction();

    // Use fake data
    $stmt = $pdo->prepare("
        INSERT INTO orders
        (user_id, order_number, total, shipping_fee, status,
         payment_method, payment_status, shipping_address, shipping_city,
         delivery_instructions, delivery_county, delivery_phone,
         delivery_recipient, address_id, mpesa_phone, created_at)
        VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        RETURNING id
    ");
    $stmt->execute([
        1, 'DRY-RUN-' . time(), 100, 100,
        'pay_on_delivery', 'pending',
        'Test Address', 'Nairobi',
        'Test instructions', 'Nairobi', '0700000000',
        'Test Recipient', null,
        null
    ]);
    $fake_order_id = $stmt->fetchColumn();

    echo "<span class='ok'>✅ Order INSERT succeeded. Fake order_id = $fake_order_id</span>";

    // Now try order_items
    $stmt = $pdo->prepare("
        INSERT INTO order_items
        (order_id, product_id, product_name, quantity, price, total)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$fake_order_id, 1, 'Test Product', 1, 100, 100]);

    echo "<br><span class='ok'>✅ order_items INSERT succeeded.</span>";

    $pdo->rollBack();
    echo "<br><span class='warn'>⚠️ Rolled back — no data was actually saved.</span>";
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<span class='err'>❌ INSERT FAILED: " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<p><strong>Code:</strong> " . $e->getCode() . "</p>";
    echo "<p><strong>This is exactly what happens when you click Place Order.</strong></p>";
}

// ============================================
// 6. Session state
// ============================================
echo "<h3>6. Session State</h3>";
echo "<pre>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NOT LOGGED IN') . "\n";
echo "user_name: " . ($_SESSION['user_name'] ?? '—') . "\n";
echo "selected_address_id: " . ($_SESSION['selected_address_id'] ?? '—') . "\n";
if (!empty($_SESSION['coupon'])) {
    echo "coupon: " . print_r($_SESSION['coupon'], true);
}
echo "</pre>";

// ============================================
// 7. User addresses
// ============================================
echo "<h3>7. User Addresses for logged-in user</h3>";
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, label, county, recipient_name FROM user_addresses WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $addrs = $stmt->fetchAll();
        if (empty($addrs)) {
            echo "<span class='warn'>⚠️ No addresses saved for this user. Cart checkout will redirect to cart.php.</span>";
        } else {
            echo "<table><tr><th>ID</th><th>Label</th><th>County</th><th>Recipient</th></tr>";
            foreach ($addrs as $a) {
                echo "<tr><td>{$a['id']}</td><td>{$a['label']}</td><td>{$a['county']}</td><td>{$a['recipient_name']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<span class='err'>Error: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
} else {
    echo "<span class='warn'>Not logged in — log into your site first, then reload this page.</span>";
}

// ============================================
// 8. Recent orders
// ============================================
echo "<h3>8. Recent Orders (last 5)</h3>";
try {
    $stmt = $pdo->query("SELECT id, order_number, user_id, status, payment_method, payment_status, total, created_at FROM orders ORDER BY id DESC LIMIT 5");
    $orders = $stmt->fetchAll();
    if (empty($orders)) {
        echo "<span class='warn'>No orders yet.</span>";
    } else {
        echo "<table><tr><th>ID</th><th>Order #</th><th>User</th><th>Status</th><th>Pay Method</th><th>Pay Status</th><th>Total</th><th>Created</th></tr>";
        foreach ($orders as $o) {
            echo "<tr>";
            echo "<td>{$o['id']}</td>";
            echo "<td>" . htmlspecialchars($o['order_number']) . "</td>";
            echo "<td>{$o['user_id']}</td>";
            echo "<td>" . htmlspecialchars($o['status'] ?? '—') . "</td>";
            echo "<td>" . htmlspecialchars($o['payment_method'] ?? '—') . "</td>";
            echo "<td>" . htmlspecialchars($o['payment_status'] ?? '—') . "</td>";
            echo "<td>" . number_format($o['total'] ?? 0) . "</td>";
            echo "<td>" . ($o['created_at'] ?? '—') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<span class='err'>Error: " . htmlspecialchars($e->getMessage()) . "</span>";
}

// ============================================
// 9. Latest PHP error log
// ============================================
echo "<h3>9. Latest PHP Error Log</h3>";
$logFiles = [
    ini_get('error_log'),
    '/var/log/php/error.log',
    '/tmp/php_errors.log',
    __DIR__ . '/php_errors.log',
];
$found = false;
foreach ($logFiles as $lf) {
    if ($lf && file_exists($lf) && is_readable($lf)) {
        $lines = array_slice(file($lf), -30);
        echo "<p>From: <code>" . htmlspecialchars($lf) . "</code></p>";
        echo "<pre>" . htmlspecialchars(implode('', $lines)) . "</pre>";
        $found = true;
        break;
    }
}
if (!$found) {
    echo "<span class='warn'>No readable error log found. Check Render → Logs tab.</span>";
}

// ============================================
// Helper
// ============================================
function guess_type($col) {
    $map = [
        'total' => 'NUMERIC(12,2)',
        'shipping_fee' => 'NUMERIC(12,2)',
        'price' => 'NUMERIC(12,2)',
        'quantity' => 'INTEGER',
        'product_id' => 'INTEGER',
        'order_id' => 'INTEGER',
        'address_id' => 'INTEGER',
        'user_id' => 'INTEGER',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];
    return $map[$col] ?? 'VARCHAR(255)';
}

echo "</body></html>";
