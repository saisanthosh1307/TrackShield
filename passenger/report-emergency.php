<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('passenger');
$pdo = db();
$categories = get_categories($pdo);
$categoryIds = array_map('intval', array_column($categories, 'id'));

$errors = [];
$d = [
    'title' => '', 'category_id' => 0, 'description' => '', 'station_name' => '', 'train_number' => '',
    'location' => '', 'incident_datetime' => '', 'contact_number' => $user['phone'],
];
$submitted = null;

if (is_post()) {
    verify_csrf();
    foreach (['title', 'description', 'station_name', 'train_number', 'location', 'incident_datetime', 'contact_number'] as $k) {
        $d[$k] = input($k);
    }
    $d['category_id']    = (int) ($_POST['category_id'] ?? 0);
    $d['train_number']   = strtoupper($d['train_number']);
    $d['contact_number'] = preg_replace('/[\s-]/', '', $d['contact_number']);

    if (mb_strlen($d['title']) < 5 || mb_strlen($d['title']) > 150)      $errors['title'] = 'Title must be between 5 and 150 characters.';
    if (!in_array($d['category_id'], $categoryIds, true))                 $errors['category_id'] = 'Please select an emergency category.';
    if (mb_strlen($d['description']) < 15 || mb_strlen($d['description']) > 5000) $errors['description'] = 'Describe the incident in 15 to 5000 characters.';
    if (mb_strlen($d['station_name']) < 2 || mb_strlen($d['station_name']) > 120) $errors['station_name'] = 'Enter the nearest railway station.';
    if ($d['train_number'] !== '' && !preg_match('/^[A-Z0-9 -]{3,20}$/', $d['train_number'])) $errors['train_number'] = 'Enter a valid train number.';
    if (mb_strlen($d['location']) < 3 || mb_strlen($d['location']) > 255) $errors['location'] = 'Enter the exact incident location (platform, coach, KM marker...).';
    if (!valid_phone($d['contact_number']))                               $errors['contact_number'] = 'Enter a valid contact number (10-15 digits).';

    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $d['incident_datetime']);
    if (!$dt || $dt->format('Y-m-d\TH:i') !== $d['incident_datetime']) {
        $errors['incident_datetime'] = 'Enter a valid date and time.';
    } elseif ($dt > new DateTime('+5 minutes')) {
        $errors['incident_datetime'] = 'Incident time cannot be in the future.';
    } elseif ($dt < new DateTime('-30 days')) {
        $errors['incident_datetime'] = 'Incidents older than 30 days cannot be reported online. Please contact 139.';
    }

    $image = null;
    if (!$errors) {
        [$image, $uploadErr] = handle_image_upload('image');
        if ($uploadErr) $errors['image'] = $uploadErr;
    }

    if (!$errors) {
        // Default priority by category severity (admin can change it later).
        $catName = array_column($categories, 'category_name', 'id')[$d['category_id']];
        $priority = in_array($catName, ['Railway Accident', 'Fire Emergency', 'Medical Emergency'], true) ? 'Critical'
                  : (in_array($catName, ['Track Damage', 'Signal Failure', 'Suspicious Activity'], true) ? 'High' : 'Medium');

        try {
            $pdo->beginTransaction();
            $ref = generate_reference($pdo);
            $stmt = $pdo->prepare('INSERT INTO emergencies
                (reference_number, user_id, category_id, title, description, station_name, train_number, location,
                 incident_datetime, priority, status, contact_number, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'Submitted\', ?, ?)');
            $stmt->execute([$ref, $user['id'], $d['category_id'], $d['title'], $d['description'], $d['station_name'],
                $d['train_number'] ?: null, $d['location'], $dt->format('Y-m-d H:i:s'), $priority, $d['contact_number'], $image]);
            $eid = (int) $pdo->lastInsertId();

            log_update($pdo, $eid, (int) $user['id'], null, 'Submitted', 'Emergency reported by passenger.');
            notify($pdo, (int) $user['id'], $eid, "Your emergency report {$ref} was submitted successfully.");
            notify_admins($pdo, $eid, "New {$priority} emergency {$ref}: {$catName} at {$d['station_name']}.");
            $pdo->commit();

            $submitted = ['id' => $eid, 'ref' => $ref, 'priority' => $priority];
            flash('success', "Emergency reported successfully. Reference: {$ref}");
        } catch (Throwable $ex) {
            $pdo->rollBack();
            if ($image) @unlink(UPLOAD_DIR . $image);
            error_log('Emergency submission failed: ' . $ex->getMessage());
            $errors['general'] = 'We could not save your report. Please try again or call 139.';
        }
    }
}

function ferr(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<div class="field-error">' . e($errors[$k]) . '</div>' : '';
}
function inv(array $errors, string $k): string
{
    return isset($errors[$k]) ? ' is-invalid' : '';
}

$catIcons = ['Railway Accident' => 'train', 'Fire Emergency' => 'alert', 'Medical Emergency' => 'activity', 'Track Damage' => 'x',
             'Signal Failure' => 'clock', 'Train Breakdown' => 'train', 'Passenger Safety Issue' => 'users', 'Suspicious Activity' => 'eye'];

$pageTitle = 'Report Emergency';
$layout = 'app';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($submitted): ?>
<div class="card" style="max-width:640px;margin:0 auto">
    <div class="success-box">
        <div class="check"><?= icon('check', 36) ?></div>
        <h2>Emergency reported successfully</h2>
        <p class="muted">Railway authorities have been notified. Save your reference number to track progress.</p>
        <div class="ref"><?= e($submitted['ref']) ?></div>
        <p>Initial priority: <?= priority_badge($submitted['priority']) ?></p>
        <div class="page-actions" style="justify-content:center">
            <a class="btn btn-primary" href="<?= e(url('passenger/emergency-details.php?id=' . $submitted['id'])) ?>">Track this emergency</a>
            <a class="btn btn-ghost" href="<?= e(url('passenger/report-emergency.php')) ?>">Report another</a>
        </div>
        <div class="alert alert-warning mt-2" style="text-align:left">If anyone's life is in danger, call <strong>139</strong> or <strong>112</strong> immediately.</div>
    </div>
</div>
<?php else: ?>
<div class="page-head">
    <div><h2>Report an Emergency</h2><p>Provide as much detail as possible so the right team can respond quickly.</p></div>
</div>
<div class="alert alert-warning">In a life-threatening situation, call <strong>139</strong> (Railway Helpline) or <strong>112</strong> first.</div>
<?php if (isset($errors['general'])): ?><div class="alert alert-error"><?= e($errors['general']) ?></div><?php elseif ($errors): ?><div class="alert alert-error">Please correct the highlighted fields.</div><?php endif; ?>

<form method="post" enctype="multipart/form-data" data-validate-form data-confirm="Submit this emergency report to railway authorities?" data-confirm-btn="Submit Report">
    <?= csrf_field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_BYTES ?>">
    <div class="grid-main-side">
        <div class="stack">
            <div class="card">
                <div class="card-header"><h3>1. Emergency Category <span class="req" style="color:var(--red-600)">*</span></h3></div>
                <div class="card-body form-group mb-0">
                    <div class="category-picker">
                        <?php foreach ($categories as $i => $c): ?>
                        <label>
                            <input type="radio" name="category_id" value="<?= (int)$c['id'] ?>" <?= $d['category_id'] === (int)$c['id'] ? 'checked' : '' ?> <?= $i === 0 ? 'data-validate="required"' : '' ?>>
                            <span class="opt" title="<?= e($c['description']) ?>"><?= icon($catIcons[$c['category_name']] ?? 'alert', 18) ?> <?= e($c['category_name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?= ferr($errors, 'category_id') ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>2. Incident Details</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="title">Incident title <span class="req">*</span></label>
                        <input class="form-control<?= inv($errors, 'title') ?>" id="title" name="title" maxlength="150" value="<?= e($d['title']) ?>" placeholder="e.g. Smoke coming from coach B2" data-validate="required" data-minlength="5">
                        <?= ferr($errors, 'title') ?>
                    </div>
                    <div class="form-group">
                        <label for="description">Description <span class="req">*</span></label>
                        <textarea class="form-control<?= inv($errors, 'description') ?>" id="description" name="description" maxlength="5000" rows="5" placeholder="What happened? How many people are affected? Any injuries?" data-validate="required" data-minlength="15"><?= e($d['description']) ?></textarea>
                        <?= ferr($errors, 'description') ?>
                    </div>
                    <div class="form-group mb-0">
                        <label for="incident_datetime">Date &amp; time of incident <span class="req">*</span></label>
                        <input class="form-control<?= inv($errors, 'incident_datetime') ?>" type="datetime-local" id="incident_datetime" name="incident_datetime" value="<?= e($d['incident_datetime']) ?>" data-max-now data-validate="required">
                        <?= ferr($errors, 'incident_datetime') ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>3. Location</h3></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="station_name">Railway station <span class="req">*</span></label>
                            <input class="form-control<?= inv($errors, 'station_name') ?>" id="station_name" name="station_name" maxlength="120" value="<?= e($d['station_name']) ?>" placeholder="Nearest station" data-validate="required">
                            <?= ferr($errors, 'station_name') ?>
                        </div>
                        <div class="form-group">
                            <label for="train_number">Train number <span class="muted">(optional)</span></label>
                            <input class="form-control<?= inv($errors, 'train_number') ?>" id="train_number" name="train_number" maxlength="20" value="<?= e($d['train_number']) ?>" placeholder="e.g. 12627" data-validate="train">
                            <?= ferr($errors, 'train_number') ?>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label for="location">Exact location <span class="req">*</span></label>
                        <input class="form-control<?= inv($errors, 'location') ?>" id="location" name="location" maxlength="255" value="<?= e($d['location']) ?>" placeholder="Platform no., coach no., KM marker, landmark..." data-validate="required">
                        <?= ferr($errors, 'location') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><h3>4. Contact</h3></div>
                <div class="card-body">
                    <div class="form-group mb-0">
                        <label for="contact_number">Contact number <span class="req">*</span></label>
                        <input class="form-control<?= inv($errors, 'contact_number') ?>" type="tel" id="contact_number" name="contact_number" maxlength="16" value="<?= e($d['contact_number']) ?>" data-validate="required phone">
                        <?= ferr($errors, 'contact_number') ?>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3>5. Evidence <span class="muted small">(optional)</span></h3></div>
                <div class="card-body form-group mb-0">
                    <div class="dropzone">
                        <?= icon('file', 28) ?>
                        <div class="dz-label" data-default="Tap or drop a photo here">Tap or drop a photo here</div>
                        <div class="small">JPG, PNG, GIF, WEBP &middot; max 5 MB</div>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" data-max-size="<?= UPLOAD_MAX_BYTES ?>" aria-label="Upload incident photo">
                        <img alt="Selected photo preview" hidden>
                    </div>
                    <?= ferr($errors, 'image') ?>
                </div>
            </div>
            <button class="btn btn-accent btn-lg btn-block" type="submit"><?= icon('alert') ?> Submit Emergency Report</button>
            <p class="muted small" style="text-align:center">False reporting is a punishable offence under the Railways Act.</p>
        </div>
    </div>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
