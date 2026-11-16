<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('admin');
$pdo = db();

$q        = input('q', '', 'GET');
$status   = input('status', '', 'GET');
$priority = input('priority', '', 'GET');
$category = (int) input('category', '0', 'GET');
$team     = (int) input('team', '0', 'GET');
$from     = input('from', '', 'GET');
$to       = input('to', '', 'GET');
$sort     = input('sort', 'created_at', 'GET');
$dir      = strtolower(input('dir', 'desc', 'GET')) === 'asc' ? 'ASC' : 'DESC';

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(e.reference_number LIKE ? OR e.title LIKE ? OR e.station_name LIKE ? OR e.train_number LIKE ? OR e.location LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if (in_array($status, EMERGENCY_STATUSES, true)) {
    $where[] = 'e.status = ?';
    $params[] = $status;
} elseif ($status === 'open') {
    $where[] = "e.status IN ('" . implode("','", ACTIVE_STATUSES) . "')";
} elseif ($status === 'operations') {
    $where[] = "e.status IN ('Response Team Dispatched', 'In Progress')";
}
if (in_array($priority, PRIORITIES, true)) { $where[] = 'e.priority = ?'; $params[] = $priority; }
if ($category > 0) { $where[] = 'e.category_id = ?'; $params[] = $category; }
if ($team > 0) { $where[] = "EXISTS (SELECT 1 FROM emergency_assignments a WHERE a.emergency_id = e.id AND a.team_id = ? AND a.assignment_status <> 'Reassigned')"; $params[] = $team; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'e.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'e.created_at <= ?'; $params[] = $to . ' 23:59:59'; }

$sortable = [
    'reference' => 'e.reference_number', 'title' => 'e.title', 'category' => 'c.category_name',
    'priority' => "FIELD(e.priority, 'Critical', 'High', 'Medium')", 'status' => "FIELD(e.status, '" . implode("','", EMERGENCY_STATUSES) . "')",
    'created_at' => 'e.created_at', 'updated_at' => 'e.updated_at', 'station' => 'e.station_name',
];
$sortSql = ($sortable[$sort] ?? 'e.created_at') . ' ' . $dir . ', e.id DESC';
$whereSql = implode(' AND ', $where);

[$page, $perPage, $offset] = paginate(15);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM emergencies e JOIN users u ON u.id = e.user_id WHERE $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT e.*, c.category_name, u.full_name AS reporter,
            (SELECT t.team_name FROM emergency_assignments a JOIN response_teams t ON t.id = a.team_id
             WHERE a.emergency_id = e.id AND a.assignment_status <> 'Reassigned' ORDER BY a.assigned_at DESC LIMIT 1) AS team_name
        FROM emergencies e
        JOIN emergency_categories c ON c.id = e.category_id
        JOIN users u ON u.id = e.user_id
        WHERE $whereSql ORDER BY $sortSql LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$categories = get_categories($pdo);
$teams = $pdo->query('SELECT id, team_name FROM response_teams ORDER BY team_name')->fetchAll();

function sort_link(string $key, string $label, string $sort, string $dir): string
{
    $q = $_GET;
    $q['sort'] = $key;
    $q['dir'] = ($sort === $key && $dir === 'DESC') ? 'asc' : 'desc';
    unset($q['page']);
    $arrow = $sort === $key ? ($dir === 'DESC' ? ' &darr;' : ' &uarr;') : '';
    return '<a href="?' . e(http_build_query($q)) . '">' . e($label) . $arrow . '</a>';
}

$pageTitle = 'Emergency Reports';
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h2>All Emergency Reports</h2><p><?= $total ?> result<?= $total === 1 ? '' : 's' ?> &middot; search, filter and sort incidents.</p></div>
</div>
<div class="card">
    <form class="filters" method="get" data-autosubmit>
        <div class="search input-icon"><?= icon('search', 18) ?><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Reference, title, station, train, reporter..."></div>
        <select class="form-control" name="status" aria-label="Status">
            <option value="">All statuses</option>
            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>All open</option>
            <option value="operations" <?= $status === 'operations' ? 'selected' : '' ?>>Active operations</option>
            <?php foreach (EMERGENCY_STATUSES as $st): ?><option <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?>
        </select>
        <select class="form-control" name="priority" aria-label="Priority">
            <option value="">All priorities</option>
            <?php foreach (PRIORITIES as $p): ?><option <?= $priority === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
        </select>
        <select class="form-control" name="category" aria-label="Category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $category === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?>
        </select>
        <select class="form-control" name="team" aria-label="Team">
            <option value="0">All teams</option>
            <?php foreach ($teams as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $team === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['team_name']) ?></option><?php endforeach; ?>
        </select>
        <input class="form-control" type="date" name="from" value="<?= e($from) ?>" aria-label="From date" title="From date">
        <input class="form-control" type="date" name="to" value="<?= e($to) ?>" aria-label="To date" title="To date">
        <input type="hidden" name="sort" value="<?= e($sort) ?>"><input type="hidden" name="dir" value="<?= e(strtolower($dir)) ?>">
        <button class="btn btn-primary btn-sm" type="submit">Apply</button>
        <a class="btn btn-ghost btn-sm" href="?">Reset</a>
    </form>
    <?php if (!$rows): ?>
        <div class="empty-state"><?= icon('search', 40) ?><p>No emergencies match the selected filters.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr>
                <th><?= sort_link('title', 'Emergency', $sort, $dir) ?></th>
                <th><?= sort_link('category', 'Category', $sort, $dir) ?></th>
                <th><?= sort_link('priority', 'Priority', $sort, $dir) ?></th>
                <th><?= sort_link('status', 'Status', $sort, $dir) ?></th>
                <th>Team</th>
                <th><?= sort_link('created_at', 'Reported', $sort, $dir) ?></th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= $r['priority'] === 'Critical' ? 'row-critical' : '' ?>">
                    <td class="cell-main"><a class="cell-title" href="<?= e(url('admin/emergency-details.php?id=' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a>
                        <span class="cell-sub"><span class="mono"><?= e($r['reference_number']) ?></span> · <?= e($r['station_name']) ?><?= $r['train_number'] ? ' · ' . e($r['train_number']) : '' ?> · by <?= e($r['reporter']) ?></span></td>
                    <td data-label="Category"><?= e($r['category_name']) ?></td>
                    <td data-label="Priority"><?= priority_badge($r['priority']) ?></td>
                    <td data-label="Status"><?= status_badge($r['status']) ?></td>
                    <td data-label="Team"><?= $r['team_name'] ? e($r['team_name']) : '<span class="muted">—</span>' ?></td>
                    <td data-label="Reported"><?= e(format_dt($r['created_at'], 'd M Y H:i')) ?></td>
                    <td data-label="Actions"><div class="actions">
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/emergency-details.php?id=' . (int)$r['id'])) ?>">Manage</a>
                        <?php if (in_array($r['status'], ['Submitted', 'Under Verification', 'Assigned'], true)): ?>
                        <a class="btn btn-accent btn-sm" href="<?= e(url('admin/assign-team.php?id=' . (int)$r['id'])) ?>">Assign</a>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_links($total, $page, $perPage) ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
