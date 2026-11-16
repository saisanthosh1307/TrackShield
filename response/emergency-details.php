<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('response');
$pdo = db();
$team = current_team((int) $user['id']);
$id = (int) ($_GET['id'] ?? 0);

// A team may only view emergencies it has been assigned (current or past, not reassigned away).
$s = $pdo->prepare("SELECT assignment_status FROM emergency_assignments WHERE emergency_id = ? AND team_id = ? AND assignment_status <> 'Reassigned'
                    ORDER BY assigned_at DESC LIMIT 1");
$s->execute([$id, $team['id'] ?? 0]);
$myAssignment = $s->fetchColumn();
$em = $myAssignment ? find_emergency($pdo, $id) : null;
if (!$em) {
    flash('error', 'Emergency not found or not assigned to your team.');
    redirect('response/assigned-emergencies.php');
}
$isActive = $myAssignment === 'Active';
$history = emergency_history($pdo, $id);
$assignment = active_assignment($pdo, $id);
$showReporter = true;
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND emergency_id = ?')->execute([$user['id'], $id]);

// Next logical status for the quick action.
$next = ['Assigned' => 'Response Team Dispatched', 'Response Team Dispatched' => 'In Progress', 'In Progress' => 'Resolved'][$em['status']] ?? null;

$pageTitle = 'Respond: ' . $em['reference_number'];
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head no-print">
    <div><h2>Emergency Response</h2><p><?= $isActive ? 'Update progress and record the actions taken.' : 'This assignment is closed (read-only).' ?></p></div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('response/assigned-emergencies.php')) ?>">&larr; Assignments</a>
</div>
<div class="grid-main-side">
    <div><?php require __DIR__ . '/../includes/emergency-view.php'; ?></div>
    <div class="stack no-print">
        <div class="card">
            <div class="card-header"><h3>Quick Contact</h3></div>
            <div class="card-body">
                <a class="btn btn-primary btn-block mb-2" href="tel:<?= e($em['contact_number']) ?>"><?= icon('phone', 18) ?> Call reporter: <?= e($em['contact_number']) ?></a>
                <p class="small muted mb-0"><?= icon('map', 14) ?> <?= e($em['station_name']) ?> &mdash; <?= e($em['location']) ?></p>
            </div>
        </div>
        <?php if ($isActive): ?>
        <div class="card">
            <div class="card-header"><h3>Update Response Status</h3></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('response/update-status.php')) ?>" data-validate-form data-confirm="Save this response update? The passenger and administrators will be notified." data-confirm-btn="Save update">
                    <?= csrf_field() ?>
                    <input type="hidden" name="emergency_id" value="<?= (int)$em['id'] ?>">
                    <div class="form-group">
                        <label for="new_status">Status</label>
                        <select class="form-control" id="new_status" name="new_status">
                            <option value="<?= e($em['status']) ?>">No change (add note only) &mdash; <?= e($em['status']) ?></option>
                            <?php foreach (RESPONSE_TEAM_STATUSES as $st): if ($st === $em['status']) continue; ?>
                                <option value="<?= e($st) ?>" <?= $st === $next ? 'selected' : '' ?>><?= e($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="remarks">Response notes / actions taken <span class="req">*</span></label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="5" maxlength="2000" data-validate="required" data-minlength="5"
                            placeholder="e.g. Team reached platform 3, first aid administered, passenger shifted to hospital."></textarea>
                        <div class="form-hint">Notes are visible to the passenger and administrators.</div>
                    </div>
                    <button class="btn btn-accent btn-block" type="submit">Submit Update</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
