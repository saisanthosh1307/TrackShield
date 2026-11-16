<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('admin');
$pdo = db();

$id = (int) ($_GET['id'] ?? $_POST['emergency_id'] ?? 0);
$em = find_emergency($pdo, $id);
if (!$em) {
    flash('error', 'Emergency not found.');
    redirect('admin/emergencies.php');
}
if (in_array($em['status'], ['Resolved', 'Rejected'], true)) {
    flash('error', 'Closed emergencies cannot be assigned.');
    redirect('admin/emergency-details.php?id=' . $id);
}
$current = active_assignment($pdo, $id);
$currentTeamId = ($current && $current['assignment_status'] === 'Active') ? (int) $current['team_id'] : 0;

if (is_post()) {
    verify_csrf();
    $teamId  = (int) ($_POST['team_id'] ?? 0);
    $remarks = input('remarks');

    $stmt = $pdo->prepare("SELECT t.*, u.status AS account_status FROM response_teams t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?");
    $stmt->execute([$teamId]);
    $team = $stmt->fetch();

    if (!$team) {
        flash('error', 'Please select a valid response team.');
    } elseif ($team['account_status'] !== 'active') {
        flash('error', 'That team account is deactivated.');
    } elseif ($team['availability'] === 'Off Duty') {
        flash('error', 'That team is currently off duty.');
    } elseif ($teamId === $currentTeamId) {
        flash('info', 'This team is already assigned to the emergency.');
    } elseif (!str_limit($remarks, 1000)) {
        flash('error', 'Instructions must be 1000 characters or fewer.');
    } else {
        try {
            $pdo->beginTransaction();
            // Retire any current assignment (reassignment).
            if ($currentTeamId) {
                $pdo->prepare("UPDATE emergency_assignments SET assignment_status = 'Reassigned', completed_at = NOW()
                               WHERE emergency_id = ? AND assignment_status = 'Active'")->execute([$id]);
                refresh_team_availability($pdo, $currentTeamId);
                $old = $pdo->prepare('SELECT user_id FROM response_teams WHERE id = ?');
                $old->execute([$currentTeamId]);
                if ($ou = $old->fetchColumn()) notify($pdo, (int) $ou, $id, "Emergency {$em['reference_number']} has been reassigned to another team.");
            }
            $pdo->prepare('INSERT INTO emergency_assignments (emergency_id, team_id, assigned_by) VALUES (?, ?, ?)')
                ->execute([$id, $teamId, $user['id']]);
            refresh_team_availability($pdo, $teamId);

            $note = ($currentTeamId ? 'Reassigned' : 'Assigned') . " to {$team['team_name']}." . ($remarks !== '' ? "\nInstructions: {$remarks}" : '');
            change_status($pdo, $em, 'Assigned', (int) $user['id'], $note);

            if ($team['user_id']) {
                notify($pdo, (int) $team['user_id'], $id, "New {$em['priority']} emergency assigned: {$em['reference_number']} at {$em['station_name']}.");
            }
            $pdo->commit();
            flash('success', "Emergency assigned to {$team['team_name']}.");
            redirect('admin/emergency-details.php?id=' . $id);
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('Assignment failed: ' . $ex->getMessage());
            flash('error', 'Assignment could not be saved. Please try again.');
        }
    }
    redirect('admin/assign-team.php?id=' . $id);
}

$teams = $pdo->query("SELECT t.*, u.status AS account_status,
        (SELECT COUNT(*) FROM emergency_assignments a WHERE a.team_id = t.id AND a.assignment_status = 'Active') AS active_count,
        (SELECT COUNT(*) FROM emergency_assignments a WHERE a.team_id = t.id AND a.assignment_status = 'Completed') AS completed_count
    FROM response_teams t LEFT JOIN users u ON u.id = t.user_id
    ORDER BY FIELD(t.availability, 'Available', 'Busy', 'Off Duty'), active_count, t.team_name")->fetchAll();

$pageTitle = 'Assign Response Team';
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h2><?= $currentTeamId ? 'Reassign' : 'Assign' ?> Response Team</h2>
        <p><span class="mono"><?= e($em['reference_number']) ?></span> &middot; <?= e($em['title']) ?> &middot; <?= priority_badge($em['priority']) ?></p></div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/emergency-details.php?id=' . $id)) ?>">&larr; Back to emergency</a>
</div>
<div class="grid-main-side">
    <div class="card">
        <div class="card-header"><h3>Select a team</h3><a class="small" href="<?= e(url('admin/manage-teams.php')) ?>">Manage teams</a></div>
        <div class="card-body">
            <?php if (!$teams): ?>
                <div class="empty-state"><p>No response teams exist yet.</p><a class="btn btn-primary" href="<?= e(url('admin/manage-teams.php')) ?>">Add a team</a></div>
            <?php else: ?>
            <form method="post" data-confirm="Assign this emergency to the selected team? The team and passenger will be notified." data-confirm-btn="Assign">
                <?= csrf_field() ?><input type="hidden" name="emergency_id" value="<?= $id ?>">
                <?php foreach ($teams as $i => $t):
                    $disabled = $t['availability'] === 'Off Duty' || $t['account_status'] !== 'active' || (int)$t['id'] === $currentTeamId; ?>
                <label class="team-option" style="<?= $disabled ? 'opacity:.55;cursor:not-allowed' : '' ?>">
                    <input type="radio" name="team_id" value="<?= (int)$t['id'] ?>" <?= $disabled ? 'disabled' : '' ?> required>
                    <div style="flex:1">
                        <strong><?= e($t['team_name']) ?></strong> <?= (int)$t['id'] === $currentTeamId ? '<span class="badge status-progress">Current</span>' : '' ?>
                        <div class="muted small"><?= e($t['specialization']) ?> &middot; Lead: <?= e($t['team_leader']) ?> &middot; <?= e($t['phone']) ?></div>
                        <div class="muted small"><?= (int)$t['active_count'] ?> active &middot; <?= (int)$t['completed_count'] ?> completed<?= $t['account_status'] !== 'active' ? ' &middot; <strong style="color:var(--red-600)">account inactive</strong>' : '' ?></div>
                    </div>
                    <?= availability_badge($t['availability']) ?>
                </label>
                <?php endforeach; ?>
                <div class="form-group mt-2">
                    <label for="remarks">Instructions for the team <span class="muted">(optional)</span></label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="3" maxlength="1000"></textarea>
                </div>
                <button class="btn btn-accent btn-lg" type="submit"><?= icon('team', 18) ?> <?= $currentTeamId ? 'Reassign' : 'Assign' ?> Team</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Incident summary</h3></div>
        <div class="card-body">
            <dl class="detail-list" style="grid-template-columns:1fr">
                <div><dt>Category</dt><dd><?= e($em['category_name']) ?></dd></div>
                <div><dt>Status</dt><dd><?= status_badge($em['status']) ?></dd></div>
                <div><dt>Station / Train</dt><dd><?= e($em['station_name']) ?><?= $em['train_number'] ? ' · ' . e($em['train_number']) : '' ?></dd></div>
                <div><dt>Location</dt><dd><?= e($em['location']) ?></dd></div>
                <div><dt>Incident time</dt><dd><?= e(format_dt($em['incident_datetime'])) ?></dd></div>
                <div><dt>Description</dt><dd style="font-weight:400;white-space:pre-line"><?= e($em['description']) ?></dd></div>
            </dl>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
