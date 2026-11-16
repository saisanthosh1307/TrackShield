<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('admin');
$pdo = db();

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
$form = ['team_name' => '', 'team_leader' => '', 'email' => '', 'phone' => '', 'specialization' => '', 'availability' => 'Available'];

if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM response_teams WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('error', 'Team not found.');
        redirect('admin/manage-teams.php');
    }
    $form = array_intersect_key($editing, $form);
}

if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'save') {
        $teamId = (int) ($_POST['team_id'] ?? 0);
        foreach (array_keys($form) as $k) $form[$k] = input($k);
        $form['email'] = mb_strtolower($form['email']);
        $form['phone'] = preg_replace('/[\s-]/', '', $form['phone']);
        $password = $_POST['password'] ?? '';

        if (mb_strlen($form['team_name']) < 3 || mb_strlen($form['team_name']) > 100) $errors['team_name'] = 'Team name must be 3-100 characters.';
        if (mb_strlen($form['team_leader']) < 2 || mb_strlen($form['team_leader']) > 100) $errors['team_leader'] = 'Enter the team leader name.';
        if (!valid_email($form['email'])) $errors['email'] = 'Enter a valid email.';
        if (!valid_phone($form['phone'])) $errors['phone'] = 'Enter a valid phone number.';
        if (mb_strlen($form['specialization']) < 2 || mb_strlen($form['specialization']) > 100) $errors['specialization'] = 'Enter a specialization.';
        if (!in_array($form['availability'], TEAM_AVAILABILITY, true)) $errors['availability'] = 'Invalid availability.';
        if (!$teamId || $password !== '') {
            if ($e = password_error($password)) $errors['password'] = $e;
        }

        $existingUserId = null;
        if ($teamId) {
            $s = $pdo->prepare('SELECT user_id FROM response_teams WHERE id = ?');
            $s->execute([$teamId]);
            $existingUserId = $s->fetchColumn();
            if ($existingUserId === false) { flash('error', 'Team not found.'); redirect('admin/manage-teams.php'); }
        }
        if (!$errors) {
            $s = $pdo->prepare('SELECT id FROM response_teams WHERE team_name = ? AND id <> ?');
            $s->execute([$form['team_name'], $teamId]);
            if ($s->fetchColumn()) $errors['team_name'] = 'A team with this name already exists.';
            $s = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $s->execute([$form['email'], (int) $existingUserId]);
            if ($s->fetchColumn()) $errors['email'] = 'This email is already used by another account.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                if ($teamId) {
                    $pdo->prepare('UPDATE response_teams SET team_name=?, team_leader=?, email=?, phone=?, specialization=?, availability=? WHERE id=?')
                        ->execute([$form['team_name'], $form['team_leader'], $form['email'], $form['phone'], $form['specialization'], $form['availability'], $teamId]);
                    if ($existingUserId) {
                        $pdo->prepare('UPDATE users SET full_name=?, email=?, phone=? WHERE id=?')
                            ->execute([$form['team_name'], $form['email'], $form['phone'], $existingUserId]);
                        if ($password !== '') {
                            $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $existingUserId]);
                        }
                    } else {
                        $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'response')")
                            ->execute([$form['team_name'], $form['email'], $form['phone'], password_hash($password ?: bin2hex(random_bytes(12)), PASSWORD_DEFAULT)]);
                        $pdo->prepare('UPDATE response_teams SET user_id = ? WHERE id = ?')->execute([$pdo->lastInsertId(), $teamId]);
                    }
                    if ($form['availability'] !== 'Off Duty') refresh_team_availability($pdo, $teamId);
                    flash('success', 'Team details updated.');
                } else {
                    $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'response')")
                        ->execute([$form['team_name'], $form['email'], $form['phone'], password_hash($password, PASSWORD_DEFAULT)]);
                    $uid = (int) $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO response_teams (user_id, team_name, team_leader, email, phone, specialization, availability) VALUES (?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$uid, $form['team_name'], $form['team_leader'], $form['email'], $form['phone'], $form['specialization'], $form['availability']]);
                    flash('success', 'Response team created. They can now log in with ' . $form['email'] . '.');
                }
                $pdo->commit();
                redirect('admin/manage-teams.php');
            } catch (Throwable $ex) {
                $pdo->rollBack();
                error_log('Team save failed: ' . $ex->getMessage());
                $errors['general'] = 'Could not save the team. Please try again.';
            }
        }
        $editId = $teamId;
    } elseif ($action === 'toggle') {
        $teamId = (int) ($_POST['team_id'] ?? 0);
        $s = $pdo->prepare('SELECT t.user_id, u.status, t.team_name FROM response_teams t JOIN users u ON u.id = t.user_id WHERE t.id = ?');
        $s->execute([$teamId]);
        if ($row = $s->fetch()) {
            $new = $row['status'] === 'active' ? 'inactive' : 'active';
            if ($new === 'inactive') {
                $c = $pdo->prepare("SELECT COUNT(*) FROM emergency_assignments WHERE team_id = ? AND assignment_status = 'Active'");
                $c->execute([$teamId]);
                if ((int) $c->fetchColumn() > 0) {
                    flash('error', 'Reassign this team\'s active emergencies before deactivating it.');
                    redirect('admin/manage-teams.php');
                }
            }
            $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $row['user_id']]);
            if ($new === 'inactive') $pdo->prepare("UPDATE response_teams SET availability = 'Off Duty' WHERE id = ?")->execute([$teamId]);
            flash('success', $row['team_name'] . ' account ' . ($new === 'active' ? 'activated' : 'deactivated') . '.');
        }
        redirect('admin/manage-teams.php');
    } elseif ($action === 'availability') {
        $teamId = (int) ($_POST['team_id'] ?? 0);
        $av = input('availability');
        if (in_array($av, TEAM_AVAILABILITY, true)) {
            $pdo->prepare('UPDATE response_teams SET availability = ? WHERE id = ?')->execute([$av, $teamId]);
            flash('success', 'Availability updated.');
        }
        redirect('admin/manage-teams.php');
    }
}

$teams = $pdo->query("SELECT t.*, u.status AS account_status,
        COALESCE(SUM(a.assignment_status = 'Active'), 0) AS active_count,
        COALESCE(SUM(a.assignment_status = 'Completed'), 0) AS completed_count,
        COUNT(a.id) AS total_assigned
    FROM response_teams t
    LEFT JOIN users u ON u.id = t.user_id
    LEFT JOIN emergency_assignments a ON a.team_id = t.id
    GROUP BY t.id ORDER BY t.team_name")->fetchAll();

// Current active assignments for monitoring
$activeOps = $pdo->query("SELECT a.assigned_at, t.team_name, e.id, e.reference_number, e.title, e.status, e.priority
    FROM emergency_assignments a JOIN response_teams t ON t.id = a.team_id JOIN emergencies e ON e.id = a.emergency_id
    WHERE a.assignment_status = 'Active' ORDER BY FIELD(e.priority, 'Critical', 'High', 'Medium'), a.assigned_at")->fetchAll();

function terr(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<div class="field-error">' . e($errors[$k]) . '</div>' : '';
}

$pageTitle = 'Response Teams';
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h2>Response Team Management</h2><p>Add teams, manage accounts and monitor assignments.</p></div>
</div>
<div class="grid-main-side">
    <div class="stack">
        <div class="card">
            <div class="card-header"><h3>Teams (<?= count($teams) ?>)</h3></div>
            <?php if (!$teams): ?><div class="empty-state"><p>No teams yet. Add one using the form.</p></div><?php else: ?>
            <div class="table-wrap">
                <table class="table responsive">
                    <thead><tr><th>Team</th><th>Availability</th><th>Assignments</th><th>Account</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($teams as $t): ?>
                        <tr>
                            <td class="cell-main"><span class="cell-title"><?= e($t['team_name']) ?></span><span class="cell-sub"><?= e($t['specialization']) ?> · Lead: <?= e($t['team_leader']) ?></span><span class="cell-sub"><?= e($t['email']) ?> · <?= e($t['phone']) ?></span></td>
                            <td data-label="Availability">
                                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="availability"><input type="hidden" name="team_id" value="<?= (int)$t['id'] ?>">
                                    <select class="form-control" name="availability" onchange="this.form.submit()" aria-label="Availability" style="min-width:120px;padding:6px 30px 6px 10px">
                                        <?php foreach (TEAM_AVAILABILITY as $av): ?><option <?= $t['availability'] === $av ? 'selected' : '' ?>><?= e($av) ?></option><?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td data-label="Assignments"><strong><?= (int)$t['active_count'] ?></strong> active<span class="cell-sub"><?= (int)$t['completed_count'] ?> completed · <?= (int)$t['total_assigned'] ?> total</span></td>
                            <td data-label="Account"><?= $t['account_status'] === 'active' ? '<span class="badge status-resolved">Active</span>' : '<span class="badge status-rejected">Inactive</span>' ?></td>
                            <td data-label="Actions"><div class="actions">
                                <a class="btn btn-ghost btn-sm" href="?edit=<?= (int)$t['id'] ?>">Edit</a>
                                <?php if ($t['user_id']): ?>
                                <form method="post" class="inline-form" data-confirm="<?= $t['account_status'] === 'active' ? 'Deactivate this team account? Its members will not be able to log in.' : 'Activate this team account?' ?>" data-confirm-variant="<?= $t['account_status'] === 'active' ? 'danger' : '' ?>">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="team_id" value="<?= (int)$t['id'] ?>">
                                    <button class="btn btn-sm <?= $t['account_status'] === 'active' ? 'btn-ghost' : 'btn-success' ?>" type="submit"><?= $t['account_status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <?php endif; ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/emergencies.php?team=' . (int)$t['id'])) ?>">Cases</a>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><h3>Active Response Operations</h3><span class="muted small"><?= count($activeOps) ?> in progress</span></div>
            <?php if (!$activeOps): ?><div class="empty-state small">No active assignments.</div><?php else: ?>
            <div class="table-wrap"><table class="table responsive">
                <thead><tr><th>Emergency</th><th>Team</th><th>Priority</th><th>Status</th><th>Assigned</th></tr></thead>
                <tbody><?php foreach ($activeOps as $o): ?>
                    <tr class="<?= $o['priority'] === 'Critical' ? 'row-critical' : '' ?>">
                        <td class="cell-main"><a class="cell-title" href="<?= e(url('admin/emergency-details.php?id=' . (int)$o['id'])) ?>"><?= e($o['title']) ?></a><span class="cell-sub mono"><?= e($o['reference_number']) ?></span></td>
                        <td data-label="Team"><?= e($o['team_name']) ?></td>
                        <td data-label="Priority"><?= priority_badge($o['priority']) ?></td>
                        <td data-label="Status"><?= status_badge($o['status']) ?></td>
                        <td data-label="Assigned"><?= e(time_ago($o['assigned_at'])) ?></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" id="team-form">
        <div class="card-header"><h3><?= $editId ? 'Edit Team' : 'Add Response Team' ?></h3><?php if ($editId): ?><a class="small" href="?">+ New team</a><?php endif; ?></div>
        <div class="card-body">
            <?php if (isset($errors['general'])): ?><div class="alert alert-error"><?= e($errors['general']) ?></div><?php endif; ?>
            <form method="post" data-validate-form>
                <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="team_id" value="<?= $editId ?>">
                <div class="form-group"><label for="team_name">Team name <span class="req">*</span></label>
                    <input class="form-control" id="team_name" name="team_name" maxlength="100" value="<?= e($form['team_name']) ?>" data-validate="required"><?= terr($errors, 'team_name') ?></div>
                <div class="form-group"><label for="team_leader">Team leader <span class="req">*</span></label>
                    <input class="form-control" id="team_leader" name="team_leader" maxlength="100" value="<?= e($form['team_leader']) ?>" data-validate="required"><?= terr($errors, 'team_leader') ?></div>
                <div class="form-group"><label for="specialization">Specialization <span class="req">*</span></label>
                    <input class="form-control" id="specialization" name="specialization" maxlength="100" list="specs" value="<?= e($form['specialization']) ?>" data-validate="required">
                    <datalist id="specs"><option>Accident &amp; Rescue</option><option>Fire Response</option><option>Medical Response</option><option>Track Maintenance</option><option>Signal &amp; Telecom</option><option>Security (RPF)</option><option>Rolling Stock</option></datalist>
                    <?= terr($errors, 'specialization') ?></div>
                <div class="form-row">
                    <div class="form-group"><label for="email">Login email <span class="req">*</span></label>
                        <input class="form-control" type="email" id="email" name="email" maxlength="150" value="<?= e($form['email']) ?>" data-validate="required email"><?= terr($errors, 'email') ?></div>
                    <div class="form-group"><label for="phone">Phone <span class="req">*</span></label>
                        <input class="form-control" type="tel" id="phone" name="phone" maxlength="16" value="<?= e($form['phone']) ?>" data-validate="required phone"><?= terr($errors, 'phone') ?></div>
                </div>
                <div class="form-group"><label for="availability">Availability</label>
                    <select class="form-control" id="availability" name="availability">
                        <?php foreach (TEAM_AVAILABILITY as $av): ?><option <?= $form['availability'] === $av ? 'selected' : '' ?>><?= e($av) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label for="password"><?= $editId ? 'Reset password' : 'Account password' ?> <?= $editId ? '<span class="muted">(leave blank to keep)</span>' : '<span class="req">*</span>' ?></label>
                    <div class="password-wrap"><input class="form-control" type="password" id="password" name="password" maxlength="72" autocomplete="new-password" data-validate="<?= $editId ? '' : 'required ' ?>password"><button type="button" class="toggle-pass small">Show</button></div>
                    <?= terr($errors, 'password') ?></div>
                <button class="btn btn-primary btn-block" type="submit"><?= $editId ? 'Save Changes' : 'Create Team' ?></button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
