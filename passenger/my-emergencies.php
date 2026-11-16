<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('passenger');
$pdo = db();

$q        = input('q', '', 'GET');
$status   = input('status', '', 'GET');
$category = (int) input('category', '0', 'GET');
$sort     = input('sort', 'newest', 'GET');

$where = ['e.user_id = ?'];
$params = [(int) $user['id']];
if ($q !== '') {
    $where[] = '(e.reference_number LIKE ? OR e.title LIKE ? OR e.station_name LIKE ? OR e.train_number LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status, EMERGENCY_STATUSES, true)) {
    $where[] = 'e.status = ?';
    $params[] = $status;
} elseif ($status === 'active') {
    $where[] = "e.status IN ('" . implode("','", ACTIVE_STATUSES) . "')";
}
if ($category > 0) {
    $where[] = 'e.category_id = ?';
    $params[] = $category;
}
$orderBy = [
    'newest'   => 'e.created_at DESC',
    'oldest'   => 'e.created_at ASC',
    'priority' => "FIELD(e.priority, 'Critical', 'High', 'Medium'), e.created_at DESC",
    'updated'  => 'e.updated_at DESC',
][$sort] ?? 'e.created_at DESC';
$whereSql = implode(' AND ', $where);

[$page, $perPage, $offset] = paginate(10);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM emergencies e WHERE $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT e.*, c.category_name FROM emergencies e
                       JOIN emergency_categories c ON c.id = e.category_id
                       WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();
$categories = get_categories($pdo);

$pageTitle = 'My Emergencies';
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h2>My Emergency Reports</h2><p><?= $total ?> report<?= $total === 1 ? '' : 's' ?> found.</p></div>
    <a class="btn btn-accent" href="<?= e(url('passenger/report-emergency.php')) ?>"><?= icon('plus', 18) ?> New Report</a>
</div>
<div class="card">
    <form class="filters" method="get" data-autosubmit>
        <div class="search input-icon"><?= icon('search', 18) ?><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search reference, title, station, train..."></div>
        <select class="form-control" name="status" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active only</option>
            <?php foreach (EMERGENCY_STATUSES as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select>
        <select class="form-control" name="category" aria-label="Filter by category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $category === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?>
        </select>
        <select class="form-control" name="sort" aria-label="Sort">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
            <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
            <option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>Recently updated</option>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Apply</button>
        <?php if ($q !== '' || $status !== '' || $category): ?><a class="btn btn-ghost btn-sm" href="?">Reset</a><?php endif; ?>
    </form>
    <?php if (!$rows): ?>
        <div class="empty-state"><?= icon('search', 40) ?><p>No emergency reports match your filters.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Emergency</th><th>Category</th><th>Priority</th><th>Status</th><th>Reported</th><th>Last update</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= $r['priority'] === 'Critical' ? 'row-critical' : '' ?>">
                    <td class="cell-main"><span class="cell-title"><?= e($r['title']) ?></span><span class="cell-sub"><span class="mono"><?= e($r['reference_number']) ?></span> · <?= e($r['station_name']) ?><?= $r['train_number'] ? ' · Train ' . e($r['train_number']) : '' ?></span></td>
                    <td data-label="Category"><?= e($r['category_name']) ?></td>
                    <td data-label="Priority"><?= priority_badge($r['priority']) ?></td>
                    <td data-label="Status"><?= status_badge($r['status']) ?></td>
                    <td data-label="Reported"><?= e(format_dt($r['created_at'], 'd M Y')) ?></td>
                    <td data-label="Last update"><?= e(time_ago($r['updated_at'])) ?></td>
                    <td data-label=""><a class="btn btn-ghost btn-sm" href="<?= e(url('passenger/emergency-details.php?id=' . (int)$r['id'])) ?>"><?= icon('eye', 16) ?> Track</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_links($total, $page, $perPage) ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
