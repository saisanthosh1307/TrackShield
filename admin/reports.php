<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('admin');
$pdo = db();

// Date range (default: last 30 days)
$from = input('from', date('Y-m-d', strtotime('-29 days')), 'GET');
$to   = input('to', date('Y-m-d'), 'GET');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !strtotime($from)) $from = date('Y-m-d', strtotime('-29 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || !strtotime($to))     $to = date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];

// ---- CSV export of emergency history ----
if (input('export', '', 'GET') === 'csv') {
    $s = $pdo->prepare("SELECT e.reference_number, e.title, c.category_name, e.priority, e.status, e.station_name, e.train_number, e.location,
            e.incident_datetime, e.created_at, e.updated_at, u.full_name AS reporter,
            (SELECT t.team_name FROM emergency_assignments a JOIN response_teams t ON t.id = a.team_id
             WHERE a.emergency_id = e.id AND a.assignment_status <> 'Reassigned' ORDER BY a.assigned_at DESC LIMIT 1) AS team
        FROM emergencies e JOIN emergency_categories c ON c.id = e.category_id JOIN users u ON u.id = e.user_id
        WHERE e.created_at BETWEEN ? AND ? ORDER BY e.created_at DESC");
    $s->execute($range);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="trackshield-emergencies-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Reference', 'Title', 'Category', 'Priority', 'Status', 'Station', 'Train', 'Location', 'Incident Time', 'Reported At', 'Last Updated', 'Reporter', 'Team']);
    while ($r = $s->fetch(PDO::FETCH_NUM)) {
        // Neutralise spreadsheet formula injection.
        fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $r));
    }
    fclose($out);
    exit;
}

// ---- Summary ----
$s = $pdo->prepare("SELECT COUNT(*) total,
        COALESCE(SUM(status = 'Resolved'), 0) resolved,
        COALESCE(SUM(status = 'Rejected'), 0) rejected,
        COALESCE(SUM(status NOT IN ('Resolved', 'Rejected')), 0) pending,
        COALESCE(SUM(priority = 'Critical'), 0) critical
    FROM emergencies WHERE created_at BETWEEN ? AND ?");
$s->execute($range);
$sum = $s->fetch();

// Average resolution time (hours) from report to Resolved status
$s = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, e.created_at, h.created_at)) / 60 FROM emergencies e
    JOIN (SELECT emergency_id, MAX(created_at) created_at FROM emergency_updates WHERE new_status = 'Resolved' GROUP BY emergency_id) h ON h.emergency_id = e.id
    WHERE e.status = 'Resolved' AND e.created_at BETWEEN ? AND ?");
$s->execute($range);
$avgResolution = $s->fetchColumn();

// ---- Daily report ----
$s = $pdo->prepare("SELECT DATE(created_at) d, COUNT(*) total, COALESCE(SUM(status = 'Resolved'), 0) resolved,
        COALESCE(SUM(priority = 'Critical'), 0) critical
    FROM emergencies WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY d");
$s->execute($range);
$dailyRows = [];
foreach ($s->fetchAll() as $r) $dailyRows[$r['d']] = $r;
$daily = ['labels' => [], 'datasets' => [['label' => 'Reported', 'data' => []], ['label' => 'Resolved', 'data' => []]]];
$days = (int) ((strtotime($to) - strtotime($from)) / 86400);
if ($days <= 92) {
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
        $d = date('Y-m-d', $t);
        $daily['labels'][] = date('d M', $t);
        $daily['datasets'][0]['data'][] = (int) ($dailyRows[$d]['total'] ?? 0);
        $daily['datasets'][1]['data'][] = (int) ($dailyRows[$d]['resolved'] ?? 0);
    }
}

// ---- Monthly report (last 12 months, independent of range) ----
$mRows = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) total, COALESCE(SUM(status = 'Resolved'), 0) resolved,
        COALESCE(SUM(status NOT IN ('Resolved','Rejected')), 0) pending
    FROM emergencies WHERE created_at >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01') GROUP BY m")->fetchAll();
$mMap = array_column($mRows, null, 'm');
$monthly = ['labels' => [], 'datasets' => [['label' => 'Reported', 'data' => []], ['label' => 'Resolved', 'data' => []]]];
$monthlyTable = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
    $label = date('M Y', strtotime($m . '-01'));
    $monthly['labels'][] = date('M y', strtotime($m . '-01'));
    $monthly['datasets'][0]['data'][] = (int) ($mMap[$m]['total'] ?? 0);
    $monthly['datasets'][1]['data'][] = (int) ($mMap[$m]['resolved'] ?? 0);
    $monthlyTable[] = ['label' => $label, 'total' => (int) ($mMap[$m]['total'] ?? 0), 'resolved' => (int) ($mMap[$m]['resolved'] ?? 0), 'pending' => (int) ($mMap[$m]['pending'] ?? 0)];
}

// ---- Category-wise ----
$s = $pdo->prepare("SELECT c.category_name, COUNT(e.id) total, COALESCE(SUM(e.status = 'Resolved'), 0) resolved,
        COALESCE(SUM(e.status NOT IN ('Resolved','Rejected')), 0) pending, COALESCE(SUM(e.priority = 'Critical'), 0) critical
    FROM emergency_categories c LEFT JOIN emergencies e ON e.category_id = c.id AND e.created_at BETWEEN ? AND ?
    GROUP BY c.id ORDER BY total DESC, c.category_name");
$s->execute($range);
$catRows = $s->fetchAll();
$catChart = ['labels' => array_column($catRows, 'category_name'), 'values' => array_map('intval', array_column($catRows, 'total'))];

// ---- Priority ----
$s = $pdo->prepare("SELECT priority, COUNT(*) n FROM emergencies WHERE created_at BETWEEN ? AND ? GROUP BY priority ORDER BY FIELD(priority, 'Critical','High','Medium')");
$s->execute($range);
$pr = $s->fetchAll(PDO::FETCH_KEY_PAIR);
$priorityChart = ['labels' => array_keys($pr), 'values' => array_map('intval', array_values($pr))];

$resolvedVsPending = ['labels' => ['Resolved', 'Pending', 'Rejected'], 'values' => [(int) $sum['resolved'], (int) $sum['pending'], (int) $sum['rejected']]];

// ---- Team performance ----
$s = $pdo->prepare("SELECT t.team_name, t.specialization, t.availability,
        COUNT(a.id) assigned,
        COALESCE(SUM(a.assignment_status = 'Completed' AND e.status = 'Resolved'), 0) resolved,
        COALESCE(SUM(a.assignment_status = 'Active'), 0) active,
        AVG(CASE WHEN a.assignment_status = 'Completed' AND e.status = 'Resolved' THEN TIMESTAMPDIFF(MINUTE, a.assigned_at, a.completed_at) END) / 60 avg_hours
    FROM response_teams t
    LEFT JOIN emergency_assignments a ON a.team_id = t.id AND a.assigned_at BETWEEN ? AND ? AND a.assignment_status <> 'Reassigned'
    LEFT JOIN emergencies e ON e.id = a.emergency_id
    GROUP BY t.id ORDER BY resolved DESC, assigned DESC");
$s->execute($range);
$teamRows = $s->fetchAll();

// ---- Emergency history (latest updates) ----
$s = $pdo->prepare("SELECT h.*, e.reference_number, e.id AS eid, u.full_name FROM emergency_updates h
    JOIN emergencies e ON e.id = h.emergency_id JOIN users u ON u.id = h.updated_by
    WHERE h.created_at BETWEEN ? AND ? ORDER BY h.created_at DESC, h.id DESC LIMIT 15");
$s->execute($range);
$historyRows = $s->fetchAll();

$resolutionRate = $sum['total'] > 0 ? round($sum['resolved'] / $sum['total'] * 100) : 0;
$json = fn($v) => json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP);

$pageTitle = 'Reports & Analytics';
$layout = 'app';
$extraScripts = '<script src="' . e(url('assets/js/vendor/chart.umd.min.js')) . '"></script>'
              . '<script src="' . e(url('assets/js/dashboard.js')) . '"></script>';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h2>Reports &amp; Analytics</h2><p><?= e(format_dt($from, 'd M Y')) ?> &ndash; <?= e(format_dt($to, 'd M Y')) ?></p></div>
    <form class="page-actions no-print" method="get">
        <input class="form-control" type="date" name="from" value="<?= e($from) ?>" aria-label="From" style="width:auto">
        <input class="form-control" type="date" name="to" value="<?= e($to) ?>" aria-label="To" style="width:auto">
        <button class="btn btn-primary" type="submit">Apply</button>
        <a class="btn btn-ghost" href="?from=<?= e($from) ?>&amp;to=<?= e($to) ?>&amp;export=csv"><?= icon('file', 16) ?> Export CSV</a>
        <button class="btn btn-ghost" type="button" onclick="window.print()">Print</button>
    </form>
</div>

<div class="stat-grid cols-6">
    <div class="stat-card"><span class="ic ic-navy"><?= icon('file', 22) ?></span><div><div class="val"><?= (int)$sum['total'] ?></div><div class="lbl">Reported</div></div></div>
    <div class="stat-card"><span class="ic ic-green"><?= icon('check', 22) ?></span><div><div class="val"><?= (int)$sum['resolved'] ?></div><div class="lbl">Resolved</div></div></div>
    <div class="stat-card"><span class="ic ic-orange"><?= icon('clock', 22) ?></span><div><div class="val"><?= (int)$sum['pending'] ?></div><div class="lbl">Pending</div></div></div>
    <div class="stat-card"><span class="ic ic-red"><?= icon('alert', 22) ?></span><div><div class="val"><?= (int)$sum['critical'] ?></div><div class="lbl">Critical</div></div></div>
    <div class="stat-card"><span class="ic ic-teal"><?= icon('activity', 22) ?></span><div><div class="val"><?= $resolutionRate ?>%</div><div class="lbl">Resolution Rate</div></div></div>
    <div class="stat-card"><span class="ic ic-purple"><?= icon('clock', 22) ?></span><div><div class="val"><?= $avgResolution !== null && $avgResolution !== false ? number_format((float)$avgResolution, 1) . 'h' : '—' ?></div><div class="lbl">Avg. Resolution Time</div></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Daily Emergency Report</h3></div>
        <div class="card-body">
            <?php if ($daily['labels']): ?><div class="chart-box"><canvas data-chart="line" data-source="dailyData" aria-label="Daily emergencies"></canvas></div>
            <?php else: ?><p class="muted">Select a range of 92 days or less to view the daily chart.</p><?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Monthly Emergency Report <span class="muted small">(last 12 months)</span></h3></div>
        <div class="card-body"><div class="chart-box"><canvas data-chart="bar" data-source="monthlyData" aria-label="Monthly emergencies"></canvas></div></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Category-wise Incidents</h3></div>
        <div class="card-body"><div class="chart-box tall"><canvas data-chart="bar" data-horizontal="1" data-source="catData" aria-label="Incidents by category"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Resolved vs Pending</h3></div>
        <div class="card-body">
            <?php if ((int)$sum['total'] === 0): ?><div class="empty-state">No emergencies in this period.</div><?php else: ?>
            <div class="chart-box"><canvas data-chart="doughnut" data-source="rvpData" data-colors="rvp" aria-label="Resolved versus pending"></canvas></div>
            <?php endif; ?>
            <?php if ($priorityChart['labels']): ?>
            <h4 class="mt-2 small">By priority</h4>
            <div class="bar-list"><?php $pmax = max(1, max($priorityChart['values'])); foreach ($priorityChart['labels'] as $i => $l): ?>
                <div class="bar-row"><span><?= priority_badge($l) ?></span><div class="bar-track"><div class="bar-fill" style="width:<?= round($priorityChart['values'][$i] / $pmax * 100) ?>%"></div></div><strong class="text-right"><?= $priorityChart['values'][$i] ?></strong></div>
            <?php endforeach; ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header"><h3>Category Statistics</h3></div>
    <div class="table-wrap"><table class="table responsive">
        <thead><tr><th>Category</th><th>Total</th><th>Resolved</th><th>Pending</th><th>Critical</th><th>Resolution %</th></tr></thead>
        <tbody><?php foreach ($catRows as $c): ?>
            <tr><td class="cell-main cell-title"><?= e($c['category_name']) ?></td><td data-label="Total"><?= (int)$c['total'] ?></td><td data-label="Resolved"><?= (int)$c['resolved'] ?></td>
                <td data-label="Pending"><?= (int)$c['pending'] ?></td><td data-label="Critical"><?= (int)$c['critical'] ?></td>
                <td data-label="Resolution %"><?= $c['total'] > 0 ? round($c['resolved'] / $c['total'] * 100) . '%' : '—' ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>

<div class="card mb-2">
    <div class="card-header"><h3>Response Team Performance</h3></div>
    <?php if (!$teamRows): ?><div class="empty-state">No response teams.</div><?php else: ?>
    <div class="table-wrap"><table class="table responsive">
        <thead><tr><th>Team</th><th>Assigned</th><th>Resolved</th><th>Active</th><th>Avg. time to resolve</th><th>Completion</th><th>Availability</th></tr></thead>
        <tbody><?php foreach ($teamRows as $t): $rate = $t['assigned'] > 0 ? round($t['resolved'] / $t['assigned'] * 100) : 0; ?>
            <tr><td class="cell-main"><span class="cell-title"><?= e($t['team_name']) ?></span><span class="cell-sub"><?= e($t['specialization']) ?></span></td>
                <td data-label="Assigned"><?= (int)$t['assigned'] ?></td><td data-label="Resolved"><?= (int)$t['resolved'] ?></td><td data-label="Active"><?= (int)$t['active'] ?></td>
                <td data-label="Avg. time"><?= $t['avg_hours'] !== null ? number_format((float)$t['avg_hours'], 1) . ' h' : '—' ?></td>
                <td data-label="Completion"><div class="bar-track" style="min-width:80px"><div class="bar-fill" style="width:<?= $rate ?>%"></div></div><span class="small muted"><?= $rate ?>%</span></td>
                <td data-label="Availability"><?= availability_badge($t['availability']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Monthly Summary</h3></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Month</th><th>Reported</th><th>Resolved</th><th>Pending</th></tr></thead>
            <tbody><?php foreach (array_reverse($monthlyTable) as $m): ?>
                <tr><td><?= e($m['label']) ?></td><td><?= $m['total'] ?></td><td><?= $m['resolved'] ?></td><td><?= $m['pending'] ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Emergency History (latest activity)</h3></div>
        <?php if (!$historyRows): ?><div class="empty-state">No activity in this period.</div><?php endif; ?>
        <?php foreach ($historyRows as $h): ?>
            <a class="list-item" style="color:inherit" href="<?= e(url('admin/emergency-details.php?id=' . (int)$h['eid'])) ?>">
                <div class="body"><strong class="small"><span class="mono"><?= e($h['reference_number']) ?></span> &rarr; <?= e($h['new_status']) ?></strong>
                    <span class="muted small"><?= e($h['full_name']) ?> · <?= e(format_dt($h['created_at'])) ?></span></div>
                <?= status_badge($h['new_status']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<script type="application/json" id="dailyData"><?= $json($daily) ?></script>
<script type="application/json" id="monthlyData"><?= $json($monthly) ?></script>
<script type="application/json" id="catData"><?= $json($catChart) ?></script>
<script type="application/json" id="rvpData"><?= $json($resolvedVsPending) ?></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
