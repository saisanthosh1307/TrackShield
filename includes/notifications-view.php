<?php
/**
 * Shared notifications page logic + view for every role.
 * Expects $user (current user). Links point to the role's emergency-details page.
 */
$pdo = db();
$uid = (int) $user['id'];
$detailsPage = ['admin' => 'admin/emergency-details.php', 'response' => 'response/emergency-details.php'][$user['role']] ?? 'passenger/emergency-details.php';

// Open a notification: mark read and jump to the emergency.
if (isset($_GET['open'])) {
    $stmt = $pdo->prepare('SELECT emergency_id FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['open'], $uid]);
    $row = $stmt->fetch();
    if ($row) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([(int) $_GET['open'], $uid]);
        if ($row['emergency_id']) {
            redirect($detailsPage . '?id=' . (int) $row['emergency_id']);
        }
    }
    redirect($user['role'] . '/notifications.php');
}

if (is_post()) {
    verify_csrf();
    $action = input('action');
    if ($action === 'mark_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
        flash('success', 'All notifications marked as read.');
    } elseif ($action === 'clear_read') {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1')->execute([$uid]);
        flash('success', 'Read notifications cleared.');
    }
    redirect($user['role'] . '/notifications.php');
}

$filter = input('filter', 'all', 'GET');
[$page, $perPage, $offset] = paginate(15);
$where = 'n.user_id = ?' . ($filter === 'unread' ? ' AND n.is_read = 0' : '');
$cnt = $pdo->prepare("SELECT COUNT(*) FROM notifications n WHERE $where");
$cnt->execute([$uid]);
$total = (int) $cnt->fetchColumn();

$stmt = $pdo->prepare("SELECT n.*, e.reference_number, e.status FROM notifications n
                       LEFT JOIN emergencies e ON e.id = n.emergency_id
                       WHERE $where ORDER BY n.created_at DESC, n.id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute([$uid]);
$items = $stmt->fetchAll();
$unread = unread_notification_count($pdo, $uid);

$pageTitle = 'Notifications';
$layout = 'app';
require __DIR__ . '/header.php';
?>
<div class="page-head">
    <div>
        <h2>Notifications</h2>
        <p>You have <strong><?= $unread ?></strong> unread notification<?= $unread === 1 ? '' : 's' ?>.</p>
    </div>
    <div class="page-actions">
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all">
            <button class="btn btn-ghost btn-sm" type="submit" <?= $unread ? '' : 'disabled' ?>><?= icon('check', 16) ?> Mark all read</button></form>
        <form method="post" class="inline-form" data-confirm="Delete all read notifications?" data-confirm-btn="Clear" data-confirm-variant="danger"><?= csrf_field() ?><input type="hidden" name="action" value="clear_read">
            <button class="btn btn-ghost btn-sm" type="submit"><?= icon('x', 16) ?> Clear read</button></form>
    </div>
</div>
<div class="card">
    <div class="filters">
        <a class="btn btn-sm <?= $filter !== 'unread' ? 'btn-primary' : 'btn-ghost' ?>" href="?filter=all">All</a>
        <a class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-ghost' ?>" href="?filter=unread">Unread</a>
    </div>
    <?php if (!$items): ?>
        <div class="empty-state"><?= icon('bell', 40) ?><p>No notifications to show.</p></div>
    <?php else: foreach ($items as $n):
        $ic = 'ic-navy'; $iname = 'bell';
        if ($n['status'] === 'Resolved') { $ic = 'ic-green'; $iname = 'check'; }
        elseif ($n['status'] === 'Rejected') { $ic = 'ic-red'; $iname = 'x'; }
        elseif ($n['status']) { $ic = 'ic-orange'; $iname = 'alert'; } ?>
        <a class="list-item <?= $n['is_read'] ? '' : 'unread' ?>" href="?open=<?= (int)$n['id'] ?>" style="color:inherit">
            <span class="ic <?= $ic ?>"><?= icon($iname, 18) ?></span>
            <div class="body">
                <strong><?= e($n['message']) ?></strong>
                <span class="muted small"><?= e(time_ago($n['created_at'])) ?><?= $n['reference_number'] ? ' · ' . e($n['reference_number']) : '' ?></span>
            </div>
            <?php if ($n['status']) echo status_badge($n['status']); ?>
        </a>
    <?php endforeach; endif; ?>
    <?= pagination_links($total, $page, $perPage) ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
