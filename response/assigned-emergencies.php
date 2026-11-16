<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('response');
$pdo = db();
$team = current_team((int) $user['id']);
if (!$team) {
    flash('error', 'Your account is not linked to a response team.');
    redirect('response/dashboard.php');
}

$view     = input('view', 'active', 'GET');         // active | history | all
$q        = input('q', '', 'GET');
$priority = input('priority', '', 'GET');
$status   = input('status', '', 'GET');

$where = ['a.team_id = ?', "a.assignment_status <> 'Reassigned'"];
$params = [(int) $team['id']];
if ($view === 'active')  $where[] = "a.assignment_status = 'Active'";
if ($view === 'history') $where[] = "a.assignment_status = 'Completed'";
if ($q !== '') { $where[] = '(e.reference_number LIKE ? OR e.title LIKE ? OR e.station_name LIKE ? OR e.location LIKE ?)'; $l = "%$q%"; array_push($params, $l, $l, $l, $l); }
if (in_array($priority, PRIORITIES, true)) { $where[] = 'e.priority = ?'; $params[] = $priority; }
if (in_array($status, EMERGENCY_STATUSES, true)) { $where[] = 'e.status = ?'; $params[] = $status; }
$w = implode(' AND ', $where);

[$page, $perPage, $offset] = paginate(12);
$s = $pdo->prepare("SELECT COUNT(*) FROM emergency_assignments a JOIN emergencies e ON e.id = a.emergency_id WHERE $w");
$s->execute($params);
$total = (int) $s->fetchColumn();
$s = $pdo->prepare("SELECT e.*, c.category_name, a.assigned_at, a.assignment_status, a.completed_at
    FROM emergency_assignments a JOIN emergencies e ON e.id = a.emergency_id JOIN emergency_categories c ON c.id = e.category_id
    WHERE $w ORDER BY (a.assignment_status = 'Active') DESC, FIELD(e.priority, 'Critical', 'High', 'Medium'), a.assigned_at DESC
    LIMIT $perPage OFFSET $offset");
$s->execute($params);
$rows = $s->fetchAll();

$pageTitle = 'Assigned Emergencies';
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h2>Assigned Emergencies</h2><p><?= e($team['team_name']) ?> &middot; <?= $total ?> record<?= $total === 1 ? '' : 's' ?></p></div>
    <div class="page-actions">
        <?php foreach (['active' => 'Active', 'history' => 'Handled (history)', 'all' => 'All'] as $k => $lbl): ?>
            <a class="btn btn-sm <?= $view === $k ? 'btn-primary' : 'btn-ghost' ?>" href="?view=<?= $k ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
    </div>
</div>
<div class="card">
    <form class="filters" method="get" data-autosubmit>
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <div class="search input-icon"><?= icon('search', 18) ?><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Reference, title, station, location"></div>
        <select class="form-control" name="priority" aria-label="Priority"><option value="">All priorities</option>
            <?php foreach (PRIORITIES as $p): ?><option <?= $priority === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select>
        <select class="form-control" name="status" aria-label="Status"><option value="">All statuses</option>
            <?php foreach (EMERGENCY_STATUSES as $st): ?><option <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary btn-sm" type="submit">Filter</button>
    </form>
    <?php if (!$rows): ?>
        <div class="empty-state"><?= icon('check', 40) ?><p><?= $view === 'active' ? 'No active assignments right now.' : 'No records found.' ?></p></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table responsive">
        <thead><tr><th>Emergency</th><th>Location</th><th>Priority</th><th>Status</th><th>Assigned</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $r): ?>
            <tr class="<?= $r['priority'] === 'Critical' && $r['assignment_status'] === 'Active' ? 'row-critical' : '' ?>">
                <td class="cell-main"><a class="cell-title" href="<?= e(url('response/emergency-details.php?id=' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a><span class="cell-sub"><span class="mono"><?= e($r['reference_number']) ?></span> · <?= e($r['category_name']) ?></span></td>
                <td data-label="Location"><?= e($r['station_name']) ?><span class="cell-sub"><?= e($r['location']) ?></span></td>
                <td data-label="Priority"><?= priority_badge($r['priority']) ?></td>
                <td data-label="Status"><?= status_badge($r['status']) ?></td>
                <td data-label="Assigned"><?= e(format_dt($r['assigned_at'], 'd M, H:i')) ?></td>
                <td data-label=""><a class="btn btn-sm <?= $r['assignment_status'] === 'Active' ? 'btn-accent' : 'btn-ghost' ?>" href="<?= e(url('response/emergency-details.php?id=' . (int)$r['id'])) ?>"><?= $r['assignment_status'] === 'Active' ? 'Respond' : 'View' ?></a></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?= pagination_links($total, $page, $perPage) ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
