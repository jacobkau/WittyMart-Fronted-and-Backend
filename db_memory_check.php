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
    'statement_timeout',
    'idle_in_transaction_session_timeout',
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

// --- NEW: Resolve the "unknown" states ---
// backend_type tells us if these are client backends (app connections),
// autovacuum workers, background writers, walsenders, etc.
$connByBackendType = q($pdo, "
    SELECT COALESCE(backend_type, 'unknown') AS backend_type,
           COUNT(*) AS count,
           COUNT(*) FILTER (WHERE state IS NULL) AS null_state
    FROM pg_stat_activity
    GROUP BY backend_type
    ORDER BY count DESC
");

// Full detail on anything with state IS NULL (the "unknown" ones)
$unknownDetail = q($pdo, "
    SELECT pid, usename, backend_type, application_name,
           client_addr,
           EXTRACT(EPOCH FROM (now() - backend_start))::int AS uptime_seconds,
           COALESCE(state, '(NULL)') AS state
    FROM pg_stat_activity
    WHERE state IS NULL
    ORDER BY backend_start ASC
");

// Per-database connection count (catches other apps on same instance)
$connByDatabase = q($pdo, "
    SELECT COALESCE(datname, 'unknown') AS datname,
           COUNT(a.pid) AS count
    FROM pg_stat_activity a
    LEFT JOIN pg_database d ON d.oid = a.datid
    GROUP BY datname
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

// --- NEW: Idle sessions with a live client_addr (app pool leak signal) ---
$idleConnections = q($pdo, "
    SELECT pid, usename, application_name, client_addr,
           EXTRACT(EPOCH FROM (now() - state_change))::int AS idle_seconds
    FROM pg_stat_activity
    WHERE state = 'idle'
      AND pid <> pg_backend_pid()
    ORDER BY state_change ASC
    LIMIT 30
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
  h3 { font-size: 1rem; margin-top: 1.2rem; }
  table { border-collapse: collapse; width: 100%; margin-top: .5rem; font-size: .9rem; }
  th, td { border: 1px solid #e5e7eb; padding: .4rem .6rem; text-align: left; vertical-align: top; }
  th { background: #f9fafb; }
  .warn { background: #fef3c7; }
  .bad  { background: #fee2e2; }
  code { background: #f3f4f6; padding: 0 .2rem; border-radius: 3px; }
  .meta { color: #6b7280; font-size: .85rem; }
  .note { background: #eff6ff; border-left: 3px solid #3b82f6; padding: .5rem .75rem; margin: .75rem 0; font-size: .85rem; }
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
    <tr class="<?= strtolower((string)$r['state']) === 'unknown' ? 'warn' : '' ?>">
      <td><?= htmlspecialchars((string)$r['state']) ?></td><td><?= (int)$r['count'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php else: ?><p>Error: <?= htmlspecialchars($connByState['error']) ?></p><?php endif; ?>

<h3>By backend_type <span class="meta">(explains the "unknown" rows)</span></h3>
<div class="note">
  <strong>background worker</strong>, <strong>autovacuum worker</strong>, <strong>walsender</strong>, and
  <strong>checkpointer</strong> are normal system processes — they have <code>state = NULL</code> in
  <code>pg_stat_activity</code>. If <strong>client backend</strong> shows up here with a NULL state,
  that means your DB user lacks <code>pg_read_all_stats</code> privilege and cannot see other users'
  sessions. That's not a leak — just a permissions limitation.
</div>
<?php if ($connByBackendType['ok']): ?>
<table>
  <tr><th>backend_type</th><th>Total</th><th>NULL state</th></tr>
  <?php foreach ($connByBackendType['rows'] as $r): ?>
    <tr>
      <td><?= htmlspecialchars((string)$r['backend_type']) ?></td>
      <td><?= (int)$r['count'] ?></td>
      <td class="<?= ((int)$r['null_state'] > 0 && $r['backend_type'] === 'client backend') ? 'bad' : '' ?>"><?= (int)$r['null_state'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php else: ?><p>Error: <?= htmlspecialchars($connByBackendType['error']) ?></p><?php endif; ?>

<h3>Detail of NULL-state sessions</h3>
<?php if ($unknownDetail['ok'] && $unknownDetail['rows']): ?>
<table>
  <tr><th>PID</th><th>User</th><th>backend_type</th><th>app</th><th>client_addr</th><th>Uptime (s)</th></tr>
  <?php foreach ($unknownDetail['rows'] as $r): ?>
    <tr class="<?= $r['backend_type'] === 'client backend' ? 'bad' : '' ?>">
      <td><?= (int)$r['pid'] ?></td>
      <td><?= htmlspecialchars((string)$r['usename']) ?></td>
      <td><?= htmlspecialchars((string)$r['backend_type']) ?></td>
      <td><?= htmlspecialchars((string)($r['application_name'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string)($r['client_addr'] ?? '')) ?></td>
      <td><?= (int)$r['uptime_seconds'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php else: ?><p>None. All sessions have a visible state.</p><?php endif; ?>

<h3>By database</h3>
<?php if ($connByDatabase['ok']): ?>
<table>
  <tr><th>Database</th><th>Count</th></tr>
  <?php foreach ($connByDatabase['rows'] as $r): ?>
    <tr><td><?= htmlspecialchars((string)$r['datname']) ?></td><td><?= (int)$r['count'] ?></td></tr>
  <?php endforeach; ?>
</table>
<?php else: ?><p>Error: <?= htmlspecialchars($connByDatabase['error']) ?></p><?php endif; ?>

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

<h2>Idle sessions (app-pool / leak check)</h2>
<div class="note">
  Idle connections aren't harmful by themselves, but on a 1 GB instance every one costs
  memory. A high count with long <code>idle_seconds</code> suggests an app connection-pool
  issue (PHP opening fresh PDO connections per request, or a pool size set too high).
</div>
<?php if ($idleConnections['ok'] && $idleConnections['rows']): ?>
<table>
  <tr><th>PID</th><th>User</th><th>app</th><th>client_addr</th><th>Idle (s)</th></tr>
  <?php foreach ($idleConnections['rows'] as $r): ?>
    <tr class="<?= (int)$r['idle_seconds'] > 600 ? 'warn' : '' ?>">
      <td><?= (int)$r['pid'] ?></td>
      <td><?= htmlspecialchars((string)$r['usename']) ?></td>
      <td><?= htmlspecialchars((string)($r['application_name'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string)($r['client_addr'] ?? '')) ?></td>
      <td><?= (int)$r['idle_seconds'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php else: ?><p>No idle sessions.</p><?php endif; ?>

</body>
</html>
