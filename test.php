<?php
// Minimal DB connectivity test — no includes, no autoloader, no output buffering
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "Step 1: PHP version " . PHP_VERSION . "<br>";

echo "Step 2: pgsql extension " . (extension_loaded('pdo_pgsql') ? 'YES' : 'NO') . "<br>";
echo "Step 2b: curl extension " . (extension_loaded('curl') ? 'YES' : 'NO') . "<br>";
echo "Step 2c: gd extension " . (extension_loaded('gd') ? 'YES' : 'NO') . "<br>";

$db = getenv('DATABASE_URL');
echo "Step 3: DATABASE_URL " . ($db ? 'SET (length ' . strlen($db) . ')' : 'NOT SET') . "<br>";

if (!$db) { exit('No DB URL — check Render env vars'); }

// Parse
$parts = parse_url($db);
if (!$parts || !isset($parts['host'])) {
    // fallback regex
    preg_match('/^(?:postgres(?:ql)?):\/\/([^:]+):(.+)@([^:\/]+)(?::(\d+))?\/(.+?)(?:\?.*)?$/', $db, $m);
    $parts = [
        'user' => urldecode($m[1]),
        'pass' => urldecode($m[2]),
        'host' => $m[3],
        'port' => $m[4] ?? '5432',
        'path' => '/' . $m[5],
    ];
}
$host = $parts['host'];
$port = $parts['port'] ?? '5432';
$dbname = ltrim($parts['path'], '/');
if (($pos = strpos($dbname, '?')) !== false) $dbname = substr($dbname, 0, $pos);
$user = urldecode($parts['user']);
$pass = urldecode($parts['pass'] ?? '');

echo "Step 4: Parsed host=$host port=$port db=$dbname user=$user<br>";

// Try TCP first — this isolates network from SSL from auth
$start = microtime(true);
$fp = @fsockopen($host, $port, $errno, $errstr, 5);
$elapsed = round(microtime(true) - $start, 2);
if (!$fp) {
    die("Step 5 FAIL: TCP to $host:$port failed in {$elapsed}s — $errno $errstr (likely Aiven IP allowlist or firewall)");
}
fclose($fp);
echo "Step 5 OK: TCP reachable in {$elapsed}s<br>";

// Now try PDO
$start = microtime(true);
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require;connect_timeout=5";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $elapsed = round(microtime(true) - $start, 2);
    echo "Step 6 OK: PDO connected in {$elapsed}s<br>";
    
    $c = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Step 7 OK: users table has $c rows<br>";
} catch (Throwable $e) {
    $elapsed = round(microtime(true) - $start, 2);
    echo "Step 6 FAIL after {$elapsed}s: " . htmlspecialchars($e->getMessage()) . "<br>";
}
