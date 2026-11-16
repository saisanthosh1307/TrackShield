<?php
/**
 * Shared emergency detail rendering.
 * Expects: $em (emergency row from find_emergency), $history (emergency_history), $assignment (active_assignment|null),
 *          $showReporter (bool) - show reporter identity (admin/response only).
 */
$stages = ['Submitted', 'Under Verification', 'Assigned', 'Response Team Dispatched', 'In Progress', 'Resolved'];
$isRejected = $em['status'] === 'Rejected';
$reached = array_unique(array_merge(['Submitted'], array_column($history, 'new_status')));
if ($isRejected) {
    $stages[5] = 'Rejected';
}
$currentIdx = $isRejected ? 5 : (int) array_search($em['status'], $stages, true);
$imgUrl = upload_url($em['image']);
?>
<div class="card mb-2">
    <div class="card-body">
        <div class="ref-banner mb-2">
            <span class="ref"><?= e($em['reference_number']) ?></span>
            <?= status_badge($em['status']) ?>
            <?= priority_badge($em['priority']) ?>
            <span class="muted small">Reported <?= e(format_dt($em['created_at'])) ?></span>
        </div>
        <h2 style="font-size:20px;margin-bottom:18px"><?= e($em['title']) ?></h2>
        <div class="tracker <?= $isRejected ? 'rejected' : '' ?>" aria-label="Emergency progress">
            <div class="bar" style="width:<?= $currentIdx * 88 / 5 ?>%"></div>
            <?php foreach ($stages as $i => $s):
                $cls = ($i < $currentIdx || $em['status'] === 'Resolved') ? 'done' : ($i === $currentIdx ? 'current' : '');
                if ($isRejected && $i < 5 && !in_array($s, $reached, true)) $cls = ''; ?>
                <div class="stage <?= $cls ?>">
                    <div class="circle"><?= $cls === 'done' ? icon('check', 16) : ($isRejected && $i === 5 ? icon('x', 16) : '') ?></div>
                    <span><?= e($s === 'Response Team Dispatched' ? 'Dispatched' : $s) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card mb-2">
    <div class="card-header"><h3>Incident Details</h3></div>
    <div class="card-body">
        <dl class="detail-list">
            <div><dt>Category</dt><dd><?= e($em['category_name']) ?></dd></div>
            <div><dt>Incident date &amp; time</dt><dd><?= e(format_dt($em['incident_datetime'])) ?></dd></div>
            <div><dt>Railway station</dt><dd><?= e($em['station_name']) ?></dd></div>
            <div><dt>Train number</dt><dd><?= e($em['train_number'] ?: 'Not specified') ?></dd></div>
            <div class="full"><dt>Exact location</dt><dd><?= e($em['location']) ?></dd></div>
            <div><dt>Contact number</dt><dd><a href="tel:<?= e($em['contact_number']) ?>"><?= e($em['contact_number']) ?></a></dd></div>
            <?php if (!empty($showReporter)): ?>
            <div><dt>Reported by</dt><dd><?= e($em['reporter_name']) ?><span class="cell-sub"><?= e($em['reporter_email']) ?></span></dd></div>
            <?php endif; ?>
            <div class="full"><dt>Description</dt><dd style="white-space:pre-line;font-weight:400"><?= e($em['description']) ?></dd></div>
            <?php if ($imgUrl): ?>
            <div class="full evidence"><dt>Evidence</dt><dd><a href="<?= e($imgUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($imgUrl) ?>" alt="Incident evidence photo" loading="lazy"></a></dd></div>
            <?php endif; ?>
        </dl>
    </div>
</div>

<?php if ($assignment): ?>
<div class="card mb-2">
    <div class="card-header"><h3>Response Team</h3><span class="badge <?= $assignment['assignment_status'] === 'Active' ? 'status-progress' : 'status-resolved' ?>"><?= e($assignment['assignment_status']) ?></span></div>
    <div class="card-body">
        <dl class="detail-list">
            <div><dt>Team</dt><dd><?= e($assignment['team_name']) ?></dd></div>
            <div><dt>Specialization</dt><dd><?= e($assignment['specialization']) ?></dd></div>
            <div><dt>Team leader</dt><dd><?= e($assignment['team_leader']) ?></dd></div>
            <div><dt>Team contact</dt><dd><?= e($assignment['team_phone']) ?></dd></div>
            <div><dt>Assigned at</dt><dd><?= e(format_dt($assignment['assigned_at'])) ?></dd></div>
            <div><dt>Assigned by</dt><dd><?= e($assignment['assigned_by_name']) ?></dd></div>
        </dl>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Status History &amp; Updates</h3><span class="muted small"><?= count($history) ?> entr<?= count($history) === 1 ? 'y' : 'ies' ?></span></div>
    <div class="card-body">
        <?php if (!$history): ?>
            <p class="muted mb-0">No updates yet.</p>
        <?php else: ?>
        <ul class="timeline">
            <?php foreach ($history as $h):
                $roleName = ['admin' => 'Administrator', 'response' => 'Response Team', 'passenger' => 'Passenger'][$h['role']] ?? ''; ?>
            <li>
                <span class="tl-dot"></span>
                <div class="tl-head">
                    <?= status_badge($h['new_status']) ?>
                    <?php if ($h['previous_status'] && $h['previous_status'] !== $h['new_status']): ?>
                        <span class="muted small">from <?= e($h['previous_status']) ?></span>
                    <?php elseif ($h['previous_status'] === $h['new_status']): ?>
                        <span class="muted small">progress note</span>
                    <?php endif; ?>
                </div>
                <div class="tl-meta"><?= e(format_dt($h['created_at'])) ?> &middot; <?= e($h['full_name']) ?> (<?= e($roleName) ?>)</div>
                <?php if ($h['remarks']): ?><div class="tl-remarks"><?= e($h['remarks']) ?></div><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
