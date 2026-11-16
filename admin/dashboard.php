<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('admin');
$pdo = db();

$s = $pdo->query("SELECT COUNT(*) total,
        COALESCE(SUM(status = 'Submitted'), 0) new_reports,
        COALESCE(SUM(status = 'Under Verification'), 0) verification,
        COALESCE(SUM(status = 'Assigned'), 0) assigned,
        COALESCE(SUM(status IN ('Response Team Dispatched', 'In Progress')), 0) active_ops,
        COALESCE(SUM(status = 'Resolved'), 0) resolved,
        COALESCE(SUM(status = 'Rejected'), 0) rejected,
        COALESCE(SUM(priority = 'Critical' AND status NOT IN ('Resolved', 'Rejected')), 0) open_critical,
        COALESCE(SUM(DATE(created_at) = CURDATE()), 0) today
    FROM emergencies")->fetch();
$users = $pdo->query("SELECT COUNT(*) total, COALESCE(SUM(role = 'passenger'), 0) passengers FROM users")->fetch();
$teams = $pdo->query("SELECT COUNT(*) total, COALESCE(SUM(availability = 'Available'), 0) available FROM response_teams")->fetch();

// Category statistics
$catStats = $pdo->query('SELECT c.category_name, COUNT(e.id) n FROM emergency_categories c
                         LEFT JOIN emergencies e ON e.category_id = c.id GROUP BY c.id ORDER BY n DESC, c.category_name')->fetchAll();
// Status distribution
$statusRows = $pdo->query('SELECT status, COUNT(*) n FROM emergencies GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$statusData = ['labels' => [], 'values' => []];
foreach (EMERGENCY_STATUSES as $st) {
    if (!empty($statusRows[$st])) { $statusData['labels'][] = $st; $statusData['values'][] = (int) $statusRows[$st]; }
}
// Last 14 days trend
$trendRows = $pdo->query('SELECT DATE(created_at) d, COUNT(*) n FROM emergencies
                          WHERE created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(created_at)')->fetchAll(PDO::FETCH_KEY_PAIR);
$trend = ['labels' => [], 'values' => []];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $trend['labels'][] = date('d M', strtotime($d));
    $trend['values'][] = (int) ($trendRows[$d] ?? 0);
}

$recent = $pdo->query('SELECT e.id, e.reference_number, e.title, e.station_name, e.priority, e.status, e.created_at, c.category_name, u.full_name
                       FROM emergencies e JOIN emergency_categories c ON c.id = e.category_id JOIN users u ON u.id = e.user_id
                       ORDER BY e.created_at DESC LIMIT 8')->fetchAll();
$critical = $pdo->query("SELECT e.id, e.reference_number, e.title, e.station_name, e.status, e.created_at
                         FROM emergencies e WHERE e.priority = 'Critical' AND e.status NOT IN ('Resolved', 'Rejected')
                         ORDER BY e.created_at ASC LIMIT 6")->fetchAll();

$catMax = max(1, (int) max(array_column($catStats, 'n') ?: [0]));

$pageTitle = 'Admin Dashboard';
$layout = 'app';
$extraScripts = '<script src="' . e(url('assets/js/vendor/chart.umd.min.js')) . '"></script>'
              . '<script src="' . e(url('assets/js/dashboard.js')) . '"></script>';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h2>Emergency Management Dashboard</h2><p><?= e(date('l, d F Y')) ?> &middot; <?= (int)$s['today'] ?> new report<?= (int)$s['today'] === 1 ? '' : 's' ?> today</p></div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('admin/reports.php')) ?>"><?= icon('chart', 18) ?> Reports</a>
        <a class="btn btn-primary" href="<?= e(url('admin/emergencies.php?status=Submitted')) ?>"><?= icon('alert', 18) ?> Review new reports</a>
    </div>
</div>

<?php if ((int)$s['open_critical'] > 0): ?>
<div class="alert alert-error"><strong><?= (int)$s['open_critical'] ?> critical emergenc<?= (int)$s['open_critical'] === 1 ? 'y' : 'ies' ?></strong> currently open and require immediate attention.
    <a href="<?= e(url('admin/emergencies.php?priority=Critical&status=open')) ?>">View now &rarr;</a></div>
<?php endif; ?>

<div class="stat-grid cols-6">
    <a class="stat-card" href="<?= e(url('admin/emergencies.php')) ?>"><span class="ic ic-navy"><?= icon('file', 22) ?></span><div><div class="val"><?= (int)$s['total'] ?></div><div class="lbl">Total Emergencies</div></div></a>
    <a class="stat-card" href="<?= e(url('admin/emergencies.php?status=Submitted')) ?>"><span class="ic ic-blue"><?= icon('bell', 22) ?></span><div><div class="val"><?= (int)$s['new_reports'] ?></div><div class="lbl">New Reports</div></div></a>
    <a class="stat-card" href="<?= e(url('admin/emergencies.php?status=Under+Verification')) ?>"><span class="ic ic-amber"><?= icon('clock', 22) ?></span><div><div class="val"><?= (int)$s['verification'] ?></div><div class="lbl">Pending Verification</div></div></a>
    <a class="stat-card" href="<?= e(url('admin/emergencies.php?status=Assigned')) ?>"><span class="ic ic-purple"><?= icon('team', 22) ?></span><div><div class="val"><?= (int)$s['assigned'] ?></div><div class="lbl">Assigned</div></div></a>
    <a class="stat-card" href="<?= e(url('admin/emergencies.php?status=operations')) ?>"><span class="ic ic-orange"><?= icon('activity', 22) ?></span><div><div class="val"><?= (int)$s['active_ops'] ?></div><div class="lbl">Active Operations</div></div></a>
    <a class="stat-card" href="<?= e(url('admin/emergencies.php?status=Resolved')) ?>"><span class="ic ic-green"><?= icon('check', 22) ?></span><div><div class="val"><?= (int)$s['resolved'] ?></div><div class="lbl">Resolved</div></div></a>
</div>

<div class="stat-grid cols-3">
    <a class="stat-card" href="<?= e(url('admin/manage-users.php')) ?>"><span class="ic ic-teal"><?= icon('users', 22) ?></span><div><div class="val"><?= (int)$users['total'] ?></div><div class="lbl">Registered Users (<?= (int)$users['passengers'] ?> passengers)</div></div></a>
    <a class="stat-card" href="<?= e(url('admin/manage-teams.php')) ?>"><span class="ic ic-navy"><?= icon('team', 22) ?></span><div><div class="val"><?= (int)$teams['available'] ?> / <?= (int)$teams['total'] ?></div><div class="lbl">Response Teams Available</div></div></a>
    <a class="stat-card" href="<?= e(url('admin/emergencies.php?priority=Critical&status=open')) ?>"><span class="ic ic-red"><?= icon('alert', 22) ?></span><div><div class="val"><?= (int)$s['open_critical'] ?></div><div class="lbl">Open Critical Emergencies</div></div></a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Emergencies &mdash; Last 14 Days</h3></div>
        <div class="card-body"><div class="chart-box"><canvas data-chart="line" data-source="trendData" aria-label="Emergencies per day chart"></canvas></div></div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Status Distribution</h3></div>
        <div class="card-body">
            <?php if (!$statusData['labels']): ?><div class="empty-state">No data yet.</div>
            <?php else: ?><div class="chart-box"><canvas data-chart="doughnut" data-colors="status" data-source="statusData" aria-label="Status distribution chart"></canvas></div><?php endif; ?>
        </div>
    </div>
</div>

<div class="grid-main-side">
    <div class="card">
        <div class="card-header"><h3>Recent Emergency Reports</h3><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/emergencies.php')) ?>">View all</a></div>
        <?php if (!$recent): ?><div class="empty-state"><?= icon('file', 40) ?><p>No emergencies reported yet.</p></div><?php else: ?>
        <div class="table-wrap">
            <table class="table responsive">
                <thead><tr><th>Emergency</th><th>Category</th><th>Priority</th><th>Status</th><th>Reported</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr class="<?= $r['priority'] === 'Critical' ? 'row-critical' : '' ?>">
                        <td class="cell-main"><a class="cell-title" href="<?= e(url('admin/emergency-details.php?id=' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a><span class="cell-sub"><span class="mono"><?= e($r['reference_number']) ?></span> · <?= e($r['station_name']) ?> · <?= e($r['full_name']) ?></span></td>
                        <td data-label="Category"><?= e($r['category_name']) ?></td>
                        <td data-label="Priority"><?= priority_badge($r['priority']) ?></td>
                        <td data-label="Status"><?= status_badge($r['status']) ?></td>
                        <td data-label="Reported"><?= e(time_ago($r['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="stack">
        <div class="card">
            <div class="card-header"><h3>Open Critical Incidents</h3></div>
            <?php if (!$critical): ?><div class="empty-state small"><?= icon('check', 28) ?><p class="mb-0">No open critical incidents.</p></div><?php endif; ?>
            <?php foreach ($critical as $c): ?>
                <a class="list-item" style="color:inherit" href="<?= e(url('admin/emergency-details.php?id=' . (int)$c['id'])) ?>">
                    <span class="ic ic-red"><?= icon('alert', 18) ?></span>
                    <div class="body"><strong class="small"><?= e($c['title']) ?></strong><span class="muted small"><?= e($c['station_name']) ?> · <?= e(time_ago($c['created_at'])) ?></span></div>
                    <?= status_badge($c['status']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3>Emergency Categories</h3></div>
            <div class="card-body bar-list">
                <?php foreach ($catStats as $c): ?>
                    <div class="bar-row"><span><?= e($c['category_name']) ?></span><div class="bar-track"><div class="bar-fill" style="width:<?= round($c['n'] / $catMax * 100) ?>%"></div></div><strong class="text-right"><?= (int)$c['n'] ?></strong></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="trendData"><?= json_encode($trend, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="statusData"><?= json_encode($statusData, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
