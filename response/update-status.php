<?php
/**
 * POST handler: response team updates the status / adds progress notes on an assigned emergency.
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('response');
$pdo = db();

if (!is_post()) {
    redirect('response/assigned-emergencies.php');
}
verify_csrf();

$id      = (int) ($_POST['emergency_id'] ?? 0);
$new     = input('new_status');
$remarks = input('remarks');
$back    = 'response/emergency-details.php?id=' . $id;
$team    = current_team((int) $user['id']);

// Verify the emergency is actively assigned to this user's team.
$s = $pdo->prepare("SELECT 1 FROM emergency_assignments WHERE emergency_id = ? AND team_id = ? AND assignment_status = 'Active'");
$s->execute([$id, $team['id'] ?? 0]);
$em = ($team && $s->fetchColumn()) ? find_emergency($pdo, $id) : null;

if (!$em) {
    flash('error', 'You can only update emergencies actively assigned to your team.');
    redirect('response/assigned-emergencies.php');
}
if ($new !== $em['status'] && !in_array($new, RESPONSE_TEAM_STATUSES, true)) {
    flash('error', 'Invalid status selected.');
    redirect($back);
}
if (mb_strlen($remarks) < 5 || mb_strlen($remarks) > 2000) {
    flash('error', 'Please enter response notes (5-2000 characters).');
    redirect($back);
}

try {
    $pdo->beginTransaction();
    change_status($pdo, $em, $new, (int) $user['id'], $remarks);
    $msg = $new === $em['status']
        ? "{$team['team_name']} added a note on {$em['reference_number']}."
        : "{$team['team_name']} updated {$em['reference_number']} to {$new}.";
    notify_admins($pdo, $id, $msg);
    $pdo->commit();
    flash('success', $new === $em['status'] ? 'Response note added.' : "Status updated to {$new}.");
} catch (Throwable $ex) {
    $pdo->rollBack();
    error_log('Response update failed: ' . $ex->getMessage());
    flash('error', 'Update could not be saved. Please try again.');
}
redirect($new === 'Resolved' ? 'response/assigned-emergencies.php?view=history' : $back);
