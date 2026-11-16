<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('admin');
$pdo = db();
$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
$form = ['category_name' => '', 'description' => ''];
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM emergency_categories WHERE id = ?');
    $s->execute([$editId]);
    if (!($row = $s->fetch())) { flash('error', 'Category not found.'); redirect('admin/categories.php'); }
    $form = ['category_name' => $row['category_name'], 'description' => $row['description']];
}

if (is_post()) {
    verify_csrf();
    $action = input('action');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'save') {
        $form['category_name'] = input('category_name');
        $form['description'] = input('description');
        if (mb_strlen($form['category_name']) < 3 || mb_strlen($form['category_name']) > 100) $errors['category_name'] = 'Name must be 3-100 characters.';
        if (mb_strlen($form['description']) > 255) $errors['description'] = 'Description must be 255 characters or fewer.';
        if (!$errors) {
            $s = $pdo->prepare('SELECT 1 FROM emergency_categories WHERE category_name = ? AND id <> ?');
            $s->execute([$form['category_name'], $id]);
            if ($s->fetchColumn()) $errors['category_name'] = 'This category already exists.';
        }
        if (!$errors) {
            if ($id) {
                $pdo->prepare('UPDATE emergency_categories SET category_name = ?, description = ? WHERE id = ?')->execute([$form['category_name'], $form['description'] ?: null, $id]);
                flash('success', 'Category updated.');
            } else {
                $pdo->prepare('INSERT INTO emergency_categories (category_name, description) VALUES (?, ?)')->execute([$form['category_name'], $form['description'] ?: null]);
                flash('success', 'Category added.');
            }
            redirect('admin/categories.php');
        }
        $editId = $id;
    } elseif ($action === 'delete') {
        $s = $pdo->prepare('SELECT COUNT(*) FROM emergencies WHERE category_id = ?');
        $s->execute([$id]);
        if ((int) $s->fetchColumn() > 0) {
            flash('error', 'This category is used by existing emergencies and cannot be deleted.');
        } else {
            $pdo->prepare('DELETE FROM emergency_categories WHERE id = ?')->execute([$id]);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }
}

$cats = $pdo->query("SELECT c.*, COUNT(e.id) AS total, COALESCE(SUM(e.status NOT IN ('Resolved','Rejected')), 0) AS open_count
                     FROM emergency_categories c LEFT JOIN emergencies e ON e.category_id = c.id GROUP BY c.id ORDER BY c.id")->fetchAll();

$pageTitle = 'Emergency Categories';
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h2>Emergency Categories</h2><p>Categories passengers can choose when reporting an emergency.</p></div></div>
<div class="grid-main-side">
    <div class="card">
        <div class="table-wrap"><table class="table responsive">
            <thead><tr><th>Category</th><th>Reports</th><th>Open</th><th>Actions</th></tr></thead>
            <tbody><?php foreach ($cats as $c): ?>
                <tr>
                    <td class="cell-main"><span class="cell-title"><?= e($c['category_name']) ?></span><span class="cell-sub"><?= e($c['description'] ?: '—') ?></span></td>
                    <td data-label="Reports"><?= (int)$c['total'] ?></td>
                    <td data-label="Open"><?= (int)$c['open_count'] ?></td>
                    <td data-label="Actions"><div class="actions">
                        <a class="btn btn-ghost btn-sm" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
                        <?php if ((int)$c['total'] === 0): ?>
                        <form method="post" class="inline-form" data-confirm="Delete the category &quot;<?= e($c['category_name']) ?>&quot;?" data-confirm-btn="Delete" data-confirm-variant="danger">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--red-600)">Delete</button>
                        </form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-header"><h3><?= $editId ? 'Edit Category' : 'Add Category' ?></h3><?php if ($editId): ?><a class="small" href="?">+ New</a><?php endif; ?></div>
        <div class="card-body">
            <form method="post" data-validate-form>
                <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $editId ?>">
                <div class="form-group"><label for="category_name">Category name <span class="req">*</span></label>
                    <input class="form-control" id="category_name" name="category_name" maxlength="100" value="<?= e($form['category_name']) ?>" data-validate="required">
                    <?= isset($errors['category_name']) ? '<div class="field-error">' . e($errors['category_name']) . '</div>' : '' ?></div>
                <div class="form-group"><label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" maxlength="255" rows="3"><?= e($form['description']) ?></textarea>
                    <?= isset($errors['description']) ? '<div class="field-error">' . e($errors['description']) . '</div>' : '' ?></div>
                <button class="btn btn-primary btn-block" type="submit"><?= $editId ? 'Save Changes' : 'Add Category' ?></button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
