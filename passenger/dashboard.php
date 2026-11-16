<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('passenger');
$pdo = db();
$uid = (int) $user['id'];

$stmt = $pdo->prepare("SELECT COUNT(*) total,
        COALESCE(SUM(status IN ('" . implode("','", ACTIVE_STATUSES) . "')), 0) active,
        COALESCE(SUM(status = 'Resolved'), 0) resolved,
        COALESCE(SUM(status = 'Rejected'), 0) rejected
    FROM emergencies WHERE user_id = ?");
$stmt->execute([$uid]);
$s = $stmt->fetch();

$stmt = $pdo->prepare('SELECT e.id, e.reference_number, e.title, e.status, e.priority, e.station_name, e.created_at, c.category_name
                       FROM emergencies e JOIN emergency_categories c ON c.id = e.category_id
                       WHERE e.user_id = ? ORDER BY e.created_at DESC LIMIT 5');
$stmt->execute([$uid]);
$recent = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 5');
$stmt->execute([$uid]);
$notes = $stmt->fetchAll();

$pageTitle = 'Passenger Dashboard';
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<section class="welcome">
    <div>
        <h2>Welcome, <?= e(strtok($user['full_name'], ' ')) ?></h2>
        <p>Witnessed an emergency on the railway? Report it now &mdash; every second counts.</p>
    </div>
    <a class="btn btn-accent btn-lg" href="<?= e(url('passenger/report-emergency.php')) ?>"><?= icon('alert') ?> Report Emergency</a>
</section>

<div class="stat-grid">
    <div class="stat-card"><span class="ic ic-navy"><?= icon('file', 22) ?></span><div><div class="val"><?= (int)$s['total'] ?></div><div class="lbl">Total Reported</div></div></div>
    <div class="stat-card"><span class="ic ic-orange"><?= icon('activity', 22) ?></span><div><div class="val"><?= (int)$s['active'] ?></div><div class="lbl">Active Reports</div></div></div>
    <div class="stat-card"><span class="ic ic-green"><?= icon('check', 22) ?></span><div><div class="val"><?= (int)$s['resolved'] ?></div><div class="lbl">Resolved</div></div></div>
    <div class="stat-card"><span class="ic ic-red"><?= icon('x', 22) ?></span><div><div class="val"><?= (int)$s['rejected'] ?></div><div class="lbl">Rejected</div></div></div>
</div>

<div class="grid-main-side">
    <div class="card">
        <div class="card-header"><h3>Recent Emergency History</h3><a class="btn btn-ghost btn-sm" href="<?= e(url('passenger/my-emergencies.php')) ?>">View all</a></div>
        <?php if (!$recent): ?>
            <div class="empty-state"><?= icon('file', 40) ?><p>You haven't reported any emergencies yet.</p>
                <a class="btn btn-accent" href="<?= e(url('passenger/report-emergency.php')) ?>">Report your first emergency</a></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table responsive">
                <thead><tr><th>Emergency</th><th>Category</th><th>Status</th><th>Reported</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr class="<?= $r['priority'] === 'Critical' ? 'row-critical' : '' ?>">
                        <td class="cell-main"><span class="cell-title"><?= e($r['title']) ?></span><span class="cell-sub mono"><?= e($r['reference_number']) ?> · <?= e($r['station_name']) ?></span></td>
                        <td data-label="Category"><?= e($r['category_name']) ?></td>
                        <td data-label="Status"><?= status_badge($r['status']) ?></td>
                        <td data-label="Reported"><?= e(time_ago($r['created_at'])) ?></td>
                        <td data-label=""><a class="btn btn-ghost btn-sm" href="<?= e(url('passenger/emergency-details.php?id=' . (int)$r['id'])) ?>">Track</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <div class="card helpline-list">
            <div class="card-header"><h3>Emergency Helplines</h3></div>
            <div class="list-item"><span class="ic ic-red"><?= icon('phone', 18) ?></span><div class="body"><strong>139</strong><span class="muted small">Railway Helpline (Rail Madad)</span></div></div>
            <div class="list-item"><span class="ic ic-navy"><?= icon('team', 18) ?></span><div class="body"><strong>182</strong><span class="muted small">Railway Protection Force</span></div></div>
            <div class="list-item"><span class="ic ic-green"><?= icon('activity', 18) ?></span><div class="body"><strong>108</strong><span class="muted small">Ambulance</span></div></div>
            <div class="list-item"><span class="ic ic-orange"><?= icon('alert', 18) ?></span><div class="body"><strong>101 / 112</strong><span class="muted small">Fire / National Emergency</span></div></div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Latest Updates</h3><a class="small" href="<?= e(url('passenger/notifications.php')) ?>">See all</a></div>
            <?php if (!$notes): ?><div class="empty-state small">No notifications yet.</div><?php endif; ?>
            <?php foreach ($notes as $n): ?>
                <a class="list-item <?= $n['is_read'] ? '' : 'unread' ?>" style="color:inherit" href="<?= e(url('passenger/notifications.php?open=' . (int)$n['id'])) ?>">
                    <div class="body"><strong class="small" style="font-weight:600"><?= e($n['message']) ?></strong><span class="muted small"><?= e(time_ago($n['created_at'])) ?></span></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
