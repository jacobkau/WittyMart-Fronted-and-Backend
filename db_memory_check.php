<?php
require_once 'includes/config.php';

header('Content-Type: text/html; charset=utf-8');

/**
 * Safely run a query and return rows, catching errors.
 */
function q($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['ok' => true, 'rows' => $stmt->fetchAll()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

$showParams = [
    'work_mem',
    'shared_buffers',
    'max_connections',
    'maintenance_work_mem',
    'effective_cache_size',
    'temp_buffers',
    'max_worker_processes',
    'max_parallel_workers',
    'max_parallel_workers_per_gather',
    'server_version',
    'max_connections',
];

$settings = [];
foreach ($showParams as $p) {
    $r = q($pdo, "SHOW $p");
    if ($r['ok'] && !empty($r['rows'])) {
        // SHOW returns a column named after the parameter
        $settings[$p] = array_values($r['rows'][0])[0] ?? null;
    } else {
        $settings[$p] = 'ERROR: ' . ($r['error'] ?? 'unknown');
    }
}

// Connection breakdown by state
$connByState = q($pdo, "
    SELECT COALESCE(state, 'unknown') AS state, COUNT(*) AS count
    FROM pg_stat_activity
    GROUP BY state
    ORDER BY count DESC
");

// Connection breakdown by user (helps spot app pool leaks)
$connByUser = q($pdo, "
    SELECT COALESCE(usename, 'unknown') AS usename, COUNT(*) AS count
    FROM pg_stat_activity
    GROUP BY usename
    ORDER BY count DESC
");

// Long-running / non-idle sessions (potential memory hogs)
$activeSessions = q($pdo, "
    SELECT pid, usename, state,
           EXTRACT(EPOCH FROM (now() - query_start))::int AS query_seconds,
           EXTRACT(EPOCH FROM (now() - state_change))::int AS state_seconds,
           LEFT(query, 200) AS query_snippet
    FROM pg_stat_activity
    WHERE pid <> pg_backend_pid()
      AND state IS NOT NULL
      AND state <> 'idle'
    ORDER BY query_start ASC
    LIMIT 20
");

// Idle-in-transaction sessions (a classic memory/connection leak)
$idleInTx = q($pdo, "
    SELECT pid, usename,
           EXTRACT(EPOCH FROM (now() - state_change))::int AS idle_seconds
    FROM pg_stat_activity
    WHERE state = 'idle in transaction'
    ORDER BY state_change ASC
    LIMIT 20
");

// Total connections vs max
$totalConns = q($pdo, "SELECT COUNT(*) AS count FROM pg_stat_activity");
$totalConns = $totalConns['ok'] ? (int)$totalConns['rows'][0]['count'] : -1;

// Database size (useful context)
$dbSize = q($pdo, "SELECT pg_size_pretty(pg_database_size(current_database())) AS size");
$dbSize = $dbSize['ok'] ? $dbSize['rows'][0]['size'] : 'unknown';

// Memory pressure helper
$maxConn = (int)($settings['max_connections'] ?? 0);
$usagePct = $maxConn > 0 ? round($totalConns / $maxConn * 100, 1) : null;

?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>PostgreSQL Memory Diagnostic</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 2rem; color: #1f2937; }
  h1 { font-size: 1.4rem; }
  h2 { font-size: 1.1rem; margin-top: 2rem; border-bottom: 1px solid #e5e7eb; padding-bottom: .25rem; }
  table { border-collapse: collapse; width: 100%; margin-top: .5rem; font-size: .9rem; }
  th, td { border: 1px solid #e5e7eb; padding: .4rem .6rem; text-align: left; vertical-align: top; }
  th { background: #f9fafb; }
  .warn { background: #fef3c7; }
  .bad  { background: #fee2e2; }
  code { background: #f3f4f6; padding: 0 .2rem; border-radius: 3px; }
  .meta { color: #6b7280; font-size: .85rem; }
</style>
</head>
<body>

<h1>PostgreSQL Memory &amp; Connection Diagnostic</h1>
<p class="meta">Generated <?= htmlspecialchars(date('c')) ?> · DB size: <?= htmlspecialchars($dbSize) ?></p>

<h2>Memory-related settings</h2>
<table>
  <tr><th>Parameter</th><th>Value</th></tr>
  <?php foreach ($settings as $k => $v): ?>
    <tr><td><code><?= htmlspecialchars($k) ?></code></td><td><?= htmlspecialchars((string)$v) ?></td></tr>
  <?php endforeach; ?>
</table>

<h2>Connections</h2>
<p>
  Total: <strong><?= $totalConns ?></strong>
  <?php if ($usagePct !== null): ?>
    / max_connections <?= $maxConn ?> (<strong><?= $usagePct ?>%</strong>)
  <?php endif; ?>
</p>

<h3>By state</h3>
<?php if ($connByState['ok']): ?>
<table>
  <tr><th>State</th><th>Count</th></tr>
  <?php foreach ($connByState['rows'] as $r): ?>
    <tr><td><?= htmlspecialchars((string)$r['state']) ?></td><td><?= (int)$r['count'] ?></td></tr>
  <?php endforeach; ?>
</table>
<?php else: ?><p>Error: <?= htmlspecialchars($connByState['error']) ?></p><?php endif; ?>

<h3>By user</h3>
<?php if ($connByUser['ok']): ?>
<table>
  <tr><th>User</th><th>Count</th></tr>
  <?php foreach ($connByUser['rows'] as $r): ?>
    <tr><td><?= htmlspecialchars((string)$r['usename']) ?></td><td><?= (int)$r['count'] ?></td></tr>
  <?php endforeach; ?>
</table>
<?php else: ?><p>Error: <?= htmlspecialchars($connByUser['error']) ?></p><?php endif; ?>

<h2>Non-idle sessions (potential memory consumers)</h2>
<?php if ($activeSessions['ok'] && $activeSessions['rows']): ?>
<table>
  <tr><th>PID</th><th>User</th><th>State</th><th>Query (s)</th><th>State (s)</th><th>Query</th></tr>
  <?php foreach ($activeSessions['rows'] as $r): ?>
    <?php
      $cls = '';
      if ((int)$r['state_seconds'] > 300) $cls = 'warn';
      if ((int)$r['query_seconds'] > 600) $cls = 'bad';
    ?>
    <tr class="<?= $cls ?>">
      <td><?= (int)$r['pid'] ?></td>
      <td><?= htmlspecialchars((string)$r['usename']) ?></td>
      <td><?= htmlspecialchars((string)$r['state']) ?></td>
      <td><?= (int)$r['query_seconds'] ?></td>
      <td><?= (int)$r['state_seconds'] ?></td>
      <td><code><?= htmlspecialchars((string)$r['query_snippet']) ?></code></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php else: ?><p>No non-idle sessions, or query blocked.</p><?php endif; ?>

<h2>Idle-in-transaction sessions</h2>
<?php if ($idleInTx['ok'] && $idleInTx['rows']): ?>
<table>
  <tr><th>PID</th><th>User</th><th>Idle (s)</th></tr>
  <?php foreach ($idleInTx['rows'] as $r): ?>
    <tr class="<?= (int)$r['idle_seconds'] > 300 ? 'warn' : '' ?>">
      <td><?= (int)$r['pid'] ?></td>
      <td><?= htmlspecialchars((string)$r['usename']) ?></td>
      <td><?= (int)$r['idle_seconds'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php else: ?><p>None. Good.</p><?php endif; ?>

</body>
</html>
