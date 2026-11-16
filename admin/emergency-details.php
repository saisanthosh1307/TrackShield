<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('admin');
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$em = find_emergency($pdo, $id);
if (!$em) {
    flash('error', 'Emergency not found.');
    redirect('admin/emergencies.php');
}
$assignment = active_assignment($pdo, $id);
$hasActiveAssignment = $assignment && $assignment['assignment_status'] === 'Active';

if (is_post()) {
    verify_csrf();
    $action  = input('action');
    $remarks = input('remarks');
    if (!str_limit($remarks, 2000)) {
        flash('error', 'Remarks must be 2000 characters or fewer.');
        redirect('admin/emergency-details.php?id=' . $id);
    }

    try {
        $pdo->beginTransaction();
        switch ($action) {
            case 'verify':
                if ($em['status'] !== 'Submitted') throw new DomainException('Only newly submitted reports can be moved to verification.');
                change_status($pdo, $em, 'Under Verification', (int) $user['id'], $remarks ?: 'Report received and under verification.');
                flash('success', 'Emergency moved to verification.');
                break;

            case 'reject':
                if (in_array($em['status'], ['Resolved', 'Rejected'], true)) throw new DomainException('This emergency is already closed.');
                if ($remarks === '') throw new DomainException('Please provide a reason for rejecting this report.');
                change_status($pdo, $em, 'Rejected', (int) $user['id'], $remarks);
                flash('success', 'Emergency report rejected.');
                break;

            case 'priority':
                $p = input('priority');
                if (!in_array($p, PRIORITIES, true)) throw new DomainException('Invalid priority.');
                if ($p !== $em['priority']) {
                    $pdo->prepare('UPDATE emergencies SET priority = ? WHERE id = ?')->execute([$p, $id]);
                    log_update($pdo, $id, (int) $user['id'], $em['status'], $em['status'],
                        "Priority changed from {$em['priority']} to {$p}." . ($remarks ? "\n" . $remarks : ''));
                    if ($hasActiveAssignment) {
                        $tu = $pdo->prepare('SELECT user_id FROM response_teams WHERE id = ?');
                        $tu->execute([$assignment['team_id']]);
                        if ($tuid = $tu->fetchColumn()) notify($pdo, (int) $tuid, $id, "Priority of {$em['reference_number']} changed to {$p}.");
                    }
                    flash('success', "Priority updated to {$p}.");
                }
                break;

            case 'status':
                $new = input('new_status');
                if (!in_array($new, EMERGENCY_STATUSES, true)) throw new DomainException('Invalid status.');
                if (in_array($new, ['Assigned', 'Response Team Dispatched', 'In Progress'], true) && !$hasActiveAssignment) {
                    throw new DomainException('Assign a response team before setting this status.');
                }
                if ($new === 'Rejected' && $remarks === '') throw new DomainException('Please provide a reason for rejection.');
                if ($new === $em['status'] && $remarks === '') throw new DomainException('Choose a different status or add a remark.');
                change_status($pdo, $em, $new, (int) $user['id'], $remarks);
                if ($hasActiveAssignment && $new !== $em['status']) {
                    $tu = $pdo->prepare('SELECT user_id FROM response_teams WHERE id = ?');
                    $tu->execute([$assignment['team_id']]);
                    if ($tuid = $tu->fetchColumn()) notify($pdo, (int) $tuid, $id, "Administrator updated {$em['reference_number']} to {$new}.");
                }
                flash('success', $new === $em['status'] ? 'Remark added.' : "Status updated to {$new}.");
                break;

            default:
                throw new DomainException('Unknown action.');
        }
        $pdo->commit();
    } catch (DomainException $ex) {
        $pdo->rollBack();
        flash('error', $ex->getMessage());
    } catch (Throwable $ex) {
        $pdo->rollBack();
        error_log('Admin emergency action failed: ' . $ex->getMessage());
        flash('error', 'The update could not be saved. Please try again.');
    }
    redirect('admin/emergency-details.php?id=' . $id);
}

$history = emergency_history($pdo, $id);
$showReporter = true;
$closed = in_array($em['status'], ['Resolved', 'Rejected'], true);

// Mark admin's notifications for this emergency as read.
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND emergency_id = ?')->execute([$user['id'], $id]);

// Assignment history
$stmt = $pdo->prepare('SELECT a.*, t.team_name FROM emergency_assignments a JOIN response_teams t ON t.id = a.team_id WHERE a.emergency_id = ? ORDER BY a.assigned_at DESC');
$stmt->execute([$id]);
$allAssignments = $stmt->fetchAll();

$pageTitle = 'Manage ' . $em['reference_number'];
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head no-print">
    <div><h2>Emergency Management</h2><p>Verify, prioritise, assign and update this incident.</p></div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/emergencies.php')) ?>">&larr; All emergencies</a>
        <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()"><?= icon('file', 16) ?> Print</button>
    </div>
</div>

<div class="grid-main-side">
    <div><?php require __DIR__ . '/../includes/emergency-view.php'; ?></div>

    <div class="stack no-print">
        <?php if ($em['status'] === 'Submitted'): ?>
        <div class="card">
            <div class="card-header"><h3>Verification</h3></div>
            <div class="card-body">
                <p class="muted small">New report awaiting review. Start verification, or reject it if it is invalid.</p>
                <form method="post" class="mb-2"><?= csrf_field() ?><input type="hidden" name="action" value="verify">
                    <button class="btn btn-primary btn-block" type="submit"><?= icon('check', 18) ?> Start Verification</button></form>
                <form method="post" data-confirm="Reject this emergency report? The passenger will be notified." data-confirm-btn="Reject" data-confirm-variant="danger">
                    <?= csrf_field() ?><input type="hidden" name="action" value="reject">
                    <div class="form-group"><textarea class="form-control" name="remarks" rows="2" maxlength="2000" placeholder="Reason for rejection (required)" required></textarea></div>
                    <button class="btn btn-ghost btn-block" type="submit"><?= icon('x', 18) ?> Reject Report</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$closed): ?>
        <div class="card">
            <div class="card-header"><h3>Response Team</h3></div>
            <div class="card-body">
                <?php if ($hasActiveAssignment): ?>
                    <p class="mb-2">Assigned to <strong><?= e($assignment['team_name']) ?></strong> <span class="muted small">(<?= e(time_ago($assignment['assigned_at'])) ?>)</span></p>
                    <a class="btn btn-ghost btn-block" href="<?= e(url('admin/assign-team.php?id=' . $id)) ?>"><?= icon('team', 18) ?> Reassign Team</a>
                <?php else: ?>
                    <p class="muted small">No team assigned yet.</p>
                    <a class="btn btn-accent btn-block" href="<?= e(url('admin/assign-team.php?id=' . $id)) ?>"><?= icon('team', 18) ?> Assign Response Team</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3>Priority</h3><?= priority_badge($em['priority']) ?></div>
            <div class="card-body">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="priority">
                    <div class="form-group">
                        <select class="form-control" name="priority" aria-label="Priority">
                            <?php foreach (PRIORITIES as $p): ?><option <?= $em['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
                        </select>
                        <div class="form-hint">Critical: immediate &middot; High: urgent &middot; Medium: timely</div>
                    </div>
                    <div class="form-group"><input class="form-control" name="remarks" maxlength="500" placeholder="Reason (optional)"></div>
                    <button class="btn btn-primary btn-block" type="submit">Update Priority</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3>Update Status</h3></div>
            <div class="card-body">
                <form method="post" data-confirm="Save this status update? The passenger will be notified." data-confirm-btn="Save update">
                    <?= csrf_field() ?><input type="hidden" name="action" value="status">
                    <div class="form-group">
                        <label for="new_status">New status</label>
                        <select class="form-control" id="new_status" name="new_status">
                            <?php foreach (EMERGENCY_STATUSES as $st):
                                $needsTeam = in_array($st, ['Assigned', 'Response Team Dispatched', 'In Progress'], true) && !$hasActiveAssignment; ?>
                                <option <?= $em['status'] === $st ? 'selected' : '' ?> <?= $needsTeam ? 'disabled' : '' ?>><?= e($st) ?><?= $needsTeam ? ' (assign team first)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="remarks">Remarks / administrative update</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" maxlength="2000" placeholder="Visible to the passenger and response team"></textarea>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">Save Update</button>
                </form>
            </div>
        </div>

        <?php if ($allAssignments): ?>
        <div class="card">
            <div class="card-header"><h3>Assignment History</h3></div>
            <?php foreach ($allAssignments as $a): ?>
                <div class="list-item"><div class="body"><strong class="small"><?= e($a['team_name']) ?></strong><span class="muted small"><?= e(format_dt($a['assigned_at'])) ?></span></div>
                    <span class="badge <?= $a['assignment_status'] === 'Active' ? 'status-progress' : ($a['assignment_status'] === 'Completed' ? 'status-resolved' : 'status-rejected') ?>"><?= e($a['assignment_status']) ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
