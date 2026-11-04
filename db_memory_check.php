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

/**
 * Format bytes into human-readable size.
 */
function hr($bytes) {
    if ($bytes === null) return 'n/a';
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    $b = (float)$bytes;
    while ($b >= 1024 && $i < count($units) - 1) { $b /= 1024; $i++; }
    return round($b, 2) . ' ' . $units[$i];
}

// ============================================
// 1. TIMEOUT SETTINGS (the two you must fix)
// ============================================
$timeoutSettings = [];
foreach (['statement_timeout', 'idle_in_transaction_session_timeout', 'lock_timeout'] as $p) {
    $r = q($pdo, "SHOW $p");
    $timeoutSettings[$p] = ($r['ok'] && !empty($r['rows'])) ? array_values($r['rows'][0])[0] : 'ERROR';
}

// ============================================
// 2. WAL SIZE (most likely cause of storage alert)
// ============================================
$walSize = q($pdo, "SELECT COALESCE(sum(size),0) AS bytes, COUNT(*) AS files FROM pg_ls_waldir()");
$walBytes = $walSize['ok'] ? (int)$walSize['rows'][0]['bytes'] : null;
$walFiles = $walSize['ok'] ? (int)$walSize['rows'][0]['files'] : null;

// Current WAL settings
$walSettings = [];
foreach (['wal_level', 'max_wal_size', 'min_wal_size', 'wal_keep_size', 'archive_mode', 'archive_command'] as $p) {
    $r = q($pdo, "SHOW $p");
    $walSettings[$p] = ($r['ok'] && !empty($r['rows'])) ? array_values($r['rows'][0])[0] : 'ERROR';
}

// ============================================
// 3. REPLICATION SLOTS (silent WAL hoarding)
// ============================================
$replicationSlots = q($pdo, "
    SELECT slot_name, plugin, slot_type, active,
           restart_lsn,
           pg_size_pretty(pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn)) AS retained_wal
    FROM pg_replication_slots
    ORDER BY active ASC, slot_name
");

// ============================================
// 4. PG_CRON HISTORY (the growth table)
// ============================================
$cronHistory = q($pdo, "
    SELECT
        COUNT(*) AS rows,
        pg_size_pretty(pg_total_relation_size('cron.job_run_details')) AS total_size,
        MIN(end_time) AS oldest,
        MAX(end_time) AS newest
    FROM cron.job_run_details
");

$cronJobs = q($pdo, "
    SELECT jobid, jobname, schedule, active
    FROM cron.job
    ORDER BY jobid
");

// ============================================
// 5. DATABASE SIZE BREAKDOWN
// ============================================
$dbSize = q($pdo, "SELECT pg_size_pretty(pg_database_size(current_database())) AS size");
$dbSizeVal = $dbSize['ok'] ? $dbSize['rows'][0]['size'] : 'unknown';

$dbSizeBytes = q($pdo, "SELECT pg_database_size(current_database()) AS bytes");
$dbSizeBytes = $dbSizeBytes['ok'] ? (int)$dbSizeBytes['rows'][0]['bytes'] : 0;

// Top 10 tables by total size (data + indexes + toast)
$topTables = q($pdo, "
    SELECT
        C.relname AS table_name,
        N.nspname AS schema_name,
        pg_size_pretty(pg_total_relation_size(C.oid)) AS total_size,
        pg_total_relation_size(C.oid) AS total_bytes
    FROM pg_class C
    LEFT JOIN pg_namespace N ON N.oid = C.relnamespace
    WHERE N.nspname NOT IN ('pg_catalog', 'information_schema')
      AND C.relkind = 'r'
    ORDER BY pg_total_relation_size(C.oid) DESC
    LIMIT 10
");

// ============================================
// 6. TEMP FILE USAGE (queries spilling to disk)
// ============================================
$tempFiles = q($pdo, "
    SELECT datname,
           pg_size_pretty(temp_bytes) AS temp_size,
           temp_files
    FROM pg_stat_database
    WHERE temp_bytes > 0
    ORDER BY temp_bytes DESC
");

// ============================================
// 7. CONNECTION QUICK CHECK (proves no leak)
// ============================================
$connSummary = q($pdo, "
    SELECT COALESCE(state, 'background') AS state, COUNT(*) AS count
    FROM pg_stat_activity
    WHERE backend_type = 'client backend'
    GROUP BY state
    ORDER BY count DESC
");

// ============================================
// 8. ALERT LOGIC
// ============================================
$alerts = [];

// Timeout alerts
if ($timeoutSettings['statement_timeout'] === '0') {
    $alerts[] = ['critical', 'statement_timeout is 0 (unlimited). A runaway query can OOM a 1 GB instance. Set to 30s.'];
}
if ($timeoutSettings['idle_in_transaction_session_timeout'] === '1d') {
    $alerts[] = ['critical', 'idle_in_transaction_session_timeout is 1 day. A stuck transaction can hold memory/locks for 24h. Set to 5min.'];
}

// WAL alerts
if ($walBytes !== null) {
    $walPct = $dbSizeBytes > 0 ? round($walBytes / $dbSizeBytes * 100, 1) : 0;
    if ($walBytes > 200 * 1024 * 1024) {
        $alerts[] = ['critical', 'WAL directory is ' . hr($walBytes) . ' across ' . $walFiles . ' files. This is likely the storage alert cause.'];
    } elseif ($walBytes > 50 * 1024 * 1024) {
        $alerts[] = ['warn', 'WAL directory is ' . hr($walBytes) . '. Worth monitoring.'];
    }
}

// Replication slot alerts
if ($replicationSlots['ok'] && !empty($replicationSlots['rows'])) {
    foreach ($replicationSlots['rows'] as $slot) {
        if (!$slot['active']) {
            $alerts[] = ['critical', 'Inactive replication slot "' . htmlspecialchars($slot['slot_name']) . '" is retaining ' . htmlspecialchars($slot['retained_wal']) . ' of WAL. Drop it if unused.'];
        }
    }
}

// Cron history alerts
if ($cronHistory['ok'] && !empty($cronHistory['rows'])) {
    $ch = $cronHistory['rows'][0];
    $chBytes = q($pdo, "SELECT pg_total_relation_size('cron.job_run_details') AS b");
    $chBytes = $chBytes['ok'] ? (int)$chBytes['rows'][0]['b'] : 0;
    if ($chBytes > 10 * 1024 * 1024) {
        $alerts[] = ['warn', 'cron.job_run_details is ' . hr($chBytes) . ' with ' . (int)$ch['rows'] . ' rows. Schedule cleanup.'];
    }
}

?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>PostgreSQL Storage &amp; Alert Diagnostic</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 2rem; color: #1f2937; max-width: 1100px; }
  h1 { font-size: 1.4rem; }
  h2 { font-size: 1.1rem; margin-top: 2rem; border-bottom: 1px solid #e5e7eb; padding-bottom: .25rem; }
  h3 { font-size: 1rem; margin-top: 1.2rem; }
  table { border-collapse: collapse; width: 100%; margin-top: .5rem; font-size: .9rem; }
  th, td { border: 1px solid #e5e7eb; padding: .4rem .6rem; text-align: left; vertical-align: top; }
  th { background: #f9fafb; }
  .warn { background: #fef3c7; }
  .bad  { background: #fee2e2; }
  .ok   { background: #d1fae5; }
  code { background: #f3f4f6; padding: 0 .2rem; border-radius: 3px; font-size: .85em; }
  .meta { color: #6b7280; font-size: .85rem; }
  .alert { padding: .6rem .8rem; margin: .5rem 0; border-radius: 4px; font-size: .9rem; }
  .alert.critical { background: #fee2e2; border-left: 4px solid #dc2626; }
  .alert.warn { background: #fef3c7; border-left: 4px solid #d97706; }
  .alert.info { background: #dbeafe; border-left: 4px solid #3b82f6; }
</style>
</head>
<body>

<h1>PostgreSQL Storage &amp; Alert Diagnostic</h1>
<p class="meta">Generated <?= htmlspecialchars(date('c')) ?> · DB size: <?= htmlspecialchars($dbSizeVal) ?></p>

<h2>1. Alerts Summary</h2>
<?php if (empty($alerts)): ?>
  <div class="alert ok">No alerts. Everything looks healthy.</div>
<?php else: ?>
  <?php foreach ($alerts as $a): ?>
    <div class="alert <?= htmlspecialchars($a[0]) ?>"><?= htmlspecialchars($a[1]) ?></div>
  <?php endforeach; ?>
<?php endif; ?>

<h2>2. Timeout Settings <span class="meta">(must-fix items)</span></h2>
<table>
  <tr><th>Parameter</th><th>Value</th><th>Status</th></tr>
  <?php foreach ($timeoutSettings as $k => $v): ?>
    <?php
      $status = 'ok';
      if ($k === 'statement_timeout' && $v === '0') $status = 'bad';
      if ($k === 'idle_in_transaction_session_timeout' && $v === '1d') $status = 'bad';
    ?>
    <tr class="<?= $status ?>">
      <td><code><?= htmlspecialchars($k) ?></code></td>
      <td><?= htmlspecialchars((string)$v) ?></td>
      <td><?= $status === 'bad' ? '⚠ Should change' : '✓ OK' ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<div class="alert info">
  Fix with:<br>
  <code>ALTER SYSTEM SET statement_timeout = '30s';</code><br>
  <code>ALTER SYSTEM SET idle_in_transaction_session_timeout = '5min';</code><br>
  <code>SELECT pg_reload_conf();</code>
</div>

<h2>3. WAL Directory <span class="meta">(primary suspect for the storage alert)</span></h2>
<table>
  <tr><th>Metric</th><th>Value</th></tr>
  <tr><td>WAL total size</td><td><strong><?= $walBytes !== null ? hr($walBytes) : 'ERROR' ?></strong></td></tr>
  <tr><td>WAL file count</td><td><?= $walFiles !== null ? (int)$walFiles : 'ERROR' ?></td></tr>
  <tr><td>WAL as % of database size</td><td><?= ($walBytes !== null && $dbSizeBytes > 0) ? round($walBytes / $dbSizeBytes * 100, 1) . '%' : 'n/a' ?></td></tr>
</table>
<h3>WAL-related settings</h3>
<table>
  <tr><th>Parameter</th><th>Value</th></tr>
  <?php foreach ($walSettings as $k => $v): ?>
    <tr><td><code><?= htmlspecialchars($k) ?></code></td><td><?= htmlspecialchars((string)$v) ?></td></tr>
  <?php endforeach; ?>
</table>
<div class="alert info">
  On Aiven, WAL segments accumulate normally but <code>max_wal_size</code> caps their growth.
  If <code>wal_keep_size</code> is large or an inactive replication slot exists, WAL is held indefinitely.
</div>

<h2>4. Replication Slots</h2>
<?php if ($replicationSlots['ok']): ?>
  <?php if (empty($replicationSlots['rows'])): ?>
    <div class="alert ok">No replication slots. Nothing is silently hoarding WAL.</div>
  <?php else: ?>
    <table>
      <tr><th>Slot</th><th>Type</th><th>Active</th><th>Retained WAL</th></tr>
      <?php foreach ($replicationSlots['rows'] as $s): ?>
        <tr class="<?= !$s['active'] ? 'bad' : 'ok' ?>">
          <td><?= htmlspecialchars((string)$s['slot_name']) ?></td>
          <td><?= htmlspecialchars((string)$s['slot_type']) ?></td>
          <td><?= $s['active'] ? 'active' : '⚠ INACTIVE' ?></td>
          <td><?= htmlspecialchars((string)$s['retained_wal']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
<?php else: ?><p>Error: <?= htmlspecialchars($replicationSlots['error']) ?></p><?php endif; ?>

<h2>5. pg_cron History <span class="meta">(the growth table)</span></h2>
<?php if ($cronHistory['ok'] && !empty($cronHistory['rows'])): ?>
  <table>
    <tr><th>Rows</th><th>Total size</th><th>Oldest run</th><th>Newest run</th></tr>
    <tr>
      <td><?= (int)$cronHistory['rows'][0]['rows'] ?></td>
      <td><?= htmlspecialchars((string)$cronHistory['rows'][0]['total_size']) ?></td>
      <td><?= htmlspecialchars((string)($cronHistory['rows'][0]['oldest'] ?? '—')) ?></td>
      <td><?= htmlspecialchars((string)($cronHistory['rows'][0]['newest'] ?? '—')) ?></td>
    </tr>
  </table>
  <div class="alert info">
    Schedule cleanup (runs daily at 2 AM, keeps 7 days):<br>
    <code>SELECT cron.schedule('cleanup-cron-history', '0 2 * * *', $$DELETE FROM cron.job_run_details WHERE end_time &lt; now() - interval '7 days'$$);</code>
  </div>
<?php else: ?><p>pg_cron history table not found or inaccessible.</p><?php endif; ?>

<h3>Active cron jobs</h3>
<?php if ($cronJobs['ok'] && !empty($cronJobs['rows'])): ?>
  <table>
    <tr><th>Job ID</th><th>Name</th><th>Schedule</th><th>Active</th></tr>
    <?php foreach ($cronJobs['rows'] as $j): ?>
      <tr><td><?= (int)$j['jobid'] ?></td><td><?= htmlspecialchars((string)$j['jobname']) ?></td><td><code><?= htmlspecialchars((string)$j['schedule']) ?></code></td><td><?= $j['active'] ? 'yes' : 'no' ?></td></tr>
    <?php endforeach; ?>
  </table>
<?php else: ?><p>No cron jobs scheduled.</p><?php endif; ?>

<h2>6. Top 10 Tables by Size</h2>
<?php if ($topTables['ok'] && !empty($topTables['rows'])): ?>
  <table>
    <tr><th>Schema</th><th>Table</th><th>Total size</th></tr>
    <?php foreach ($topTables['rows'] as $t): ?>
      <tr>
        <td><?= htmlspecialchars((string)$t['schema_name']) ?></td>
        <td><?= htmlspecialchars((string)$t['table_name']) ?></td>
        <td><?= htmlspecialchars((string)$t['total_size']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php else: ?><p>No tables or query failed.</p><?php endif; ?>

<h2>7. Temp Files <span class="meta">(queries spilling to disk)</span></h2>
<?php if ($tempFiles['ok'] && !empty($tempFiles['rows'])): ?>
  <table>
    <tr><th>Database</th><th>Temp size</th><th>Temp files</th></tr>
    <?php foreach ($tempFiles['rows'] as $t): ?>
      <tr>
        <td><?= htmlspecialchars((string)$t['datname']) ?></td>
        <td><?= htmlspecialchars((string)$t['temp_size']) ?></td>
        <td><?= (int)$t['temp_files'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php else: ?><p>No temp file usage since last stats reset.</p><?php endif; ?>

<h2>8. Client Connection Health <span class="meta">(confirms no leak)</span></h2>
<?php if ($connSummary['ok'] && !empty($connSummary['rows'])): ?>
  <table>
    <tr><th>State</th><th>Count</th></tr>
    <?php foreach ($connSummary['rows'] as $c): ?>
      <tr class="<?= $c['state'] === 'idle in transaction' ? 'bad' : '' ?>">
        <td><?= htmlspecialchars((string)$c['state']) ?></td>
        <td><?= (int)$c['count'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php else: ?><p>No client connections or query failed.</p><?php endif; ?>

<hr style="margin-top:2rem; border:none; border-top:1px solid #e5e7eb;">
<p class="meta">
  <strong>Interpreting this report:</strong><br>
  • If WAL size is large &gt; 200 MB, that is your storage alert cause — not user data.<br>
  • If any replication slot is INACTIVE, drop it (<code>SELECT pg_drop_replication_slot('name');</code>) — it's holding WAL forever.<br>
  • If cron.job_run_details &gt; 10 MB, schedule the cleanup job shown above.<br>
  • If statement_timeout = 0 or idle_in_transaction = 1d, apply the two ALTER SYSTEM commands.<br>
  • After all fixes, re-run this script weekly and keep an eye on WAL and cron history.
</p>

</body>
</html>
