<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('admin');
$pdo = db();
$errors = [];
$new = ['full_name' => '', 'email' => '', 'phone' => ''];

if (is_post()) {
    verify_csrf();
    $action = input('action');
    if ($action === 'toggle') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        if ($uid === (int) $user['id']) {
            flash('error', 'You cannot deactivate your own account.');
        } else {
            $s = $pdo->prepare('SELECT full_name, status, role FROM users WHERE id = ?');
            $s->execute([$uid]);
            if ($u = $s->fetch()) {
                $status = $u['status'] === 'active' ? 'inactive' : 'active';
                $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $uid]);
                if ($u['role'] === 'response' && $status === 'inactive') {
                    $pdo->prepare("UPDATE response_teams SET availability = 'Off Duty' WHERE user_id = ?")->execute([$uid]);
                }
                flash('success', $u['full_name'] . ' has been ' . ($status === 'active' ? 'activated' : 'deactivated') . '.');
            }
        }
        redirect('admin/manage-users.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'role', 'status', 'page']))));
    } elseif ($action === 'add_admin') {
        $new['full_name'] = input('full_name');
        $new['email'] = mb_strtolower(input('email'));
        $new['phone'] = preg_replace('/[\s-]/', '', input('phone'));
        $pw = $_POST['password'] ?? '';
        if (mb_strlen($new['full_name']) < 2 || mb_strlen($new['full_name']) > 100) $errors['full_name'] = 'Enter a full name.';
        if (!valid_email($new['email'])) $errors['email'] = 'Enter a valid email.';
        if (!valid_phone($new['phone'])) $errors['phone'] = 'Enter a valid phone number.';
        if ($e = password_error($pw)) $errors['password'] = $e;
        if (!$errors) {
            $s = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
            $s->execute([$new['email']]);
            if ($s->fetchColumn()) $errors['email'] = 'Email already registered.';
        }
        if (!$errors) {
            $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'admin')")
                ->execute([$new['full_name'], $new['email'], $new['phone'], password_hash($pw, PASSWORD_DEFAULT)]);
            flash('success', 'Administrator account created.');
            redirect('admin/manage-users.php');
        }
    }
}

$q = input('q', '', 'GET');
$role = input('role', '', 'GET');
$status = input('status', '', 'GET');
$where = ['1=1'];
$params = [];
if ($q !== '') { $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)'; $l = "%$q%"; array_push($params, $l, $l, $l); }
if (in_array($role, ['passenger', 'response', 'admin'], true)) { $where[] = 'u.role = ?'; $params[] = $role; }
if (in_array($status, ['active', 'inactive'], true)) { $where[] = 'u.status = ?'; $params[] = $status; }
$w = implode(' AND ', $where);

[$page, $perPage, $offset] = paginate(15);
$s = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $w");
$s->execute($params);
$total = (int) $s->fetchColumn();
$s = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM emergencies e WHERE e.user_id = u.id) AS reports
                    FROM users u WHERE $w ORDER BY u.created_at DESC, u.id DESC LIMIT $perPage OFFSET $offset");
$s->execute($params);
$rows = $s->fetchAll();
$counts = $pdo->query("SELECT role, COUNT(*) FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);

$roleLabel = ['passenger' => 'Passenger', 'response' => 'Response Team', 'admin' => 'Administrator'];
function uerr(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<div class="field-error">' . e($errors[$k]) . '</div>' : '';
}

$pageTitle = 'User Management';
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h2>User Accounts</h2><p>Manage passenger, response team and administrator accounts.</p></div></div>
<div class="stat-grid cols-3">
    <div class="stat-card"><span class="ic ic-navy"><?= icon('users', 22) ?></span><div><div class="val"><?= (int)($counts['passenger'] ?? 0) ?></div><div class="lbl">Passengers</div></div></div>
    <div class="stat-card"><span class="ic ic-orange"><?= icon('team', 22) ?></span><div><div class="val"><?= (int)($counts['response'] ?? 0) ?></div><div class="lbl">Response Team Accounts</div></div></div>
    <div class="stat-card"><span class="ic ic-purple"><?= icon('dashboard', 22) ?></span><div><div class="val"><?= (int)($counts['admin'] ?? 0) ?></div><div class="lbl">Administrators</div></div></div>
</div>
<div class="grid-main-side">
    <div class="card">
        <form class="filters" method="get" data-autosubmit>
            <div class="search input-icon"><?= icon('search', 18) ?><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, phone"></div>
            <select class="form-control" name="role" aria-label="Role"><option value="">All roles</option>
                <?php foreach ($roleLabel as $k => $v): ?><option value="<?= $k ?>" <?= $role === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
            <select class="form-control" name="status" aria-label="Status"><option value="">Any status</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option></select>
            <button class="btn btn-primary btn-sm" type="submit">Search</button>
        </form>
        <?php if (!$rows): ?><div class="empty-state"><p>No users found.</p></div><?php else: ?>
        <div class="table-wrap"><table class="table responsive">
            <thead><tr><th>User</th><th>Role</th><th>Reports</th><th>Joined</th><th>Status</th><th></th></tr></thead>
            <tbody><?php foreach ($rows as $r): ?>
                <tr>
                    <td class="cell-main"><span class="cell-title"><?= e($r['full_name']) ?></span><span class="cell-sub"><?= e($r['email']) ?> · <?= e($r['phone']) ?></span></td>
                    <td data-label="Role"><?= e($roleLabel[$r['role']]) ?></td>
                    <td data-label="Reports"><?= (int)$r['reports'] ?></td>
                    <td data-label="Joined"><?= e(format_dt($r['created_at'], 'd M Y')) ?></td>
                    <td data-label="Status"><?= $r['status'] === 'active' ? '<span class="badge status-resolved">Active</span>' : '<span class="badge status-rejected">Inactive</span>' ?></td>
                    <td data-label="">
                        <?php if ((int)$r['id'] !== (int)$user['id']): ?>
                        <form method="post" class="inline-form" data-confirm="<?= $r['status'] === 'active' ? 'Deactivate ' . e($r['full_name']) . '? They will be logged out and unable to sign in.' : 'Re-activate ' . e($r['full_name']) . '?' ?>" data-confirm-variant="<?= $r['status'] === 'active' ? 'danger' : '' ?>">
                            <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm <?= $r['status'] === 'active' ? 'btn-ghost' : 'btn-success' ?>" type="submit"><?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                        <?php else: ?><span class="muted small">You</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?= pagination_links($total, $page, $perPage) ?>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header"><h3>Add Administrator</h3></div>
        <div class="card-body">
            <p class="muted small">Response team accounts are created from <a href="<?= e(url('admin/manage-teams.php')) ?>">Response Teams</a>. Passengers self-register.</p>
            <form method="post" data-validate-form>
                <?= csrf_field() ?><input type="hidden" name="action" value="add_admin">
                <div class="form-group"><label for="full_name">Full name</label><input class="form-control" id="full_name" name="full_name" maxlength="100" value="<?= e($new['full_name']) ?>" data-validate="required"><?= uerr($errors, 'full_name') ?></div>
                <div class="form-group"><label for="email">Email</label><input class="form-control" type="email" id="email" name="email" maxlength="150" value="<?= e($new['email']) ?>" data-validate="required email"><?= uerr($errors, 'email') ?></div>
                <div class="form-group"><label for="phone">Phone</label><input class="form-control" type="tel" id="phone" name="phone" maxlength="16" value="<?= e($new['phone']) ?>" data-validate="required phone"><?= uerr($errors, 'phone') ?></div>
                <div class="form-group"><label for="password">Password</label><div class="password-wrap"><input class="form-control" type="password" id="password" name="password" maxlength="72" autocomplete="new-password" data-validate="required password"><button type="button" class="toggle-pass small">Show</button></div><?= uerr($errors, 'password') ?></div>
                <button class="btn btn-primary btn-block" type="submit">Create Administrator</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
