<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('passenger');
$pdo = db();

$em = find_emergency($pdo, (int) ($_GET['id'] ?? 0));
// Passengers may only view their own reports (prevents IDOR).
if (!$em || (int) $em['user_id'] !== (int) $user['id']) {
    flash('error', 'Emergency report not found.');
    redirect('passenger/my-emergencies.php');
}

$history = emergency_history($pdo, (int) $em['id']);
$assignment = active_assignment($pdo, (int) $em['id']);
$showReporter = false;

// Mark this emergency's notifications as read.
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND emergency_id = ?')->execute([$user['id'], $em['id']]);

$pageTitle = 'Emergency ' . $em['reference_number'];
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head no-print">
    <div><h2>Emergency Tracking</h2><p>Live status and full update history for your report.</p></div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('passenger/my-emergencies.php')) ?>">&larr; Back</a>
        <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()"><?= icon('file', 16) ?> Print</button>
    </div>
</div>
<?php if ($history && $history[0]['remarks']): ?>
    <div class="alert alert-info"><strong>Latest update:</strong> <?= e($history[0]['remarks']) ?> <span class="small">(<?= e(time_ago($history[0]['created_at'])) ?>)</span></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/emergency-view.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
