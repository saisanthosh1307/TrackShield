<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('response');
$pdo = db();
$team = current_team((int) $user['id']);

$stats = ['active' => 0, 'critical' => 0, 'resolved' => 0, 'total' => 0];
$active = $recentDone = [];
if ($team) {
    $s = $pdo->prepare("SELECT COUNT(*) total,
            COALESCE(SUM(a.assignment_status = 'Active'), 0) active,
            COALESCE(SUM(a.assignment_status = 'Active' AND e.priority = 'Critical'), 0) critical,
            COALESCE(SUM(a.assignment_status = 'Completed' AND e.status = 'Resolved'), 0) resolved
        FROM emergency_assignments a JOIN emergencies e ON e.id = a.emergency_id
        WHERE a.team_id = ? AND a.assignment_status <> 'Reassigned'");
    $s->execute([$team['id']]);
    $stats = $s->fetch();

    $s = $pdo->prepare("SELECT e.*, c.category_name, a.assigned_at FROM emergency_assignments a
        JOIN emergencies e ON e.id = a.emergency_id JOIN emergency_categories c ON c.id = e.category_id
        WHERE a.team_id = ? AND a.assignment_status = 'Active'
        ORDER BY FIELD(e.priority, 'Critical', 'High', 'Medium'), a.assigned_at");
    $s->execute([$team['id']]);
    $active = $s->fetchAll();

    $s = $pdo->prepare("SELECT e.id, e.reference_number, e.title, e.status, a.completed_at FROM emergency_assignments a
        JOIN emergencies e ON e.id = a.emergency_id
        WHERE a.team_id = ? AND a.assignment_status = 'Completed' ORDER BY a.completed_at DESC LIMIT 5");
    $s->execute([$team['id']]);
    $recentDone = $s->fetchAll();
}

$pageTitle = 'Response Team Dashboard';
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<?php if (!$team): ?>
    <div class="alert alert-warning">Your account is not linked to a response team yet. Please contact the administrator.</div>
<?php else: ?>
<section class="welcome">
    <div>
        <h2><?= e($team['team_name']) ?></h2>
        <p><?= e($team['specialization']) ?> &middot; Team leader: <?= e($team['team_leader']) ?> &middot; Status: <strong style="color:#fff"><?= e($team['availability']) ?></strong></p>
    </div>
    <a class="btn btn-accent btn-lg" href="<?= e(url('response/assigned-emergencies.php')) ?>"><?= icon('list') ?> View Assignments</a>
</section>

<div class="stat-grid">
    <div class="stat-card"><span class="ic ic-orange"><?= icon('activity', 22) ?></span><div><div class="val"><?= (int)$stats['active'] ?></div><div class="lbl">Active Assignments</div></div></div>
    <div class="stat-card"><span class="ic ic-red"><?= icon('alert', 22) ?></span><div><div class="val"><?= (int)$stats['critical'] ?></div><div class="lbl">Critical Active</div></div></div>
    <div class="stat-card"><span class="ic ic-green"><?= icon('check', 22) ?></span><div><div class="val"><?= (int)$stats['resolved'] ?></div><div class="lbl">Resolved by Team</div></div></div>
    <div class="stat-card"><span class="ic ic-navy"><?= icon('file', 22) ?></span><div><div class="val"><?= (int)$stats['total'] ?></div><div class="lbl">Total Handled</div></div></div>
</div>

<div class="grid-main-side">
    <div class="card">
        <div class="card-header"><h3>Active Emergencies</h3><span class="muted small">Sorted by priority</span></div>
        <?php if (!$active): ?>
            <div class="empty-state"><?= icon('check', 40) ?><p>No active assignments. Stand by for dispatch.</p></div>
        <?php else: foreach ($active as $r): ?>
            <a class="list-item" style="color:inherit" href="<?= e(url('response/emergency-details.php?id=' . (int)$r['id'])) ?>">
                <span class="ic <?= $r['priority'] === 'Critical' ? 'ic-red' : ($r['priority'] === 'High' ? 'ic-orange' : 'ic-navy') ?>"><?= icon('alert', 18) ?></span>
                <div class="body">
                    <strong><?= e($r['title']) ?></strong>
                    <span class="muted small"><span class="mono"><?= e($r['reference_number']) ?></span> · <?= e($r['category_name']) ?> · <?= e($r['station_name']) ?><?= $r['train_number'] ? ' · Train ' . e($r['train_number']) : '' ?></span>
                    <span class="muted small" style="display:block"><?= icon('map', 14) ?> <?= e($r['location']) ?> · assigned <?= e(time_ago($r['assigned_at'])) ?></span>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end"><?= priority_badge($r['priority']) ?><?= status_badge($r['status']) ?></div>
            </a>
        <?php endforeach; endif; ?>
    </div>
    <div class="stack">
        <div class="card">
            <div class="card-header"><h3>Recently Resolved</h3></div>
            <?php if (!$recentDone): ?><div class="empty-state small">Nothing yet.</div><?php endif; ?>
            <?php foreach ($recentDone as $r): ?>
                <a class="list-item" style="color:inherit" href="<?= e(url('response/emergency-details.php?id=' . (int)$r['id'])) ?>">
                    <span class="ic ic-green"><?= icon('check', 18) ?></span>
                    <div class="body"><strong class="small"><?= e($r['title']) ?></strong><span class="muted small"><?= e($r['reference_number']) ?> · <?= e(time_ago($r['completed_at'])) ?></span></div>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="card"><div class="card-body small">
            <strong>Response workflow</strong>
            <ol class="muted" style="padding-left:18px;margin:8px 0 0">
                <li>Assigned &rarr; mark <em>Response Team Dispatched</em> when leaving.</li>
                <li>On site &rarr; mark <em>In Progress</em> and post notes.</li>
                <li>Record actions taken, then mark <em>Resolved</em>.</li>
            </ol>
        </div></div>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
