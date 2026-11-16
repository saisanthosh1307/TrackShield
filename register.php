<?php
require_once __DIR__ . '/includes/auth.php';

if ($u = current_user()) {
    redirect(dashboard_path($u['role']));
}

$errors = [];
$data = ['full_name' => '', 'email' => '', 'phone' => ''];

if (is_post()) {
    verify_csrf();
    $data['full_name'] = input('full_name');
    $data['email']     = mb_strtolower(input('email'));
    $data['phone']     = preg_replace('/[\s-]/', '', input('phone'));
    $password          = $_POST['password'] ?? '';
    $confirm           = $_POST['confirm_password'] ?? '';

    if ($data['full_name'] === '' || !str_limit($data['full_name'], 100) || !preg_match("/^[\p{L} .'-]{2,100}$/u", $data['full_name'])) {
        $errors['full_name'] = 'Enter your full name (letters, spaces, . \' - only).';
    }
    if (!valid_email($data['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!valid_phone($data['phone'])) {
        $errors['phone'] = 'Enter a valid phone number (10-15 digits).';
    }
    if ($err = password_error($password)) {
        $errors['password'] = $err;
    } elseif ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'You must agree to report emergencies truthfully.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetchColumn()) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare("INSERT INTO users (full_name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'passenger', 'active')");
        $stmt->execute([$data['full_name'], $data['email'], $data['phone'], password_hash($password, PASSWORD_DEFAULT)]);
        $id = (int) db()->lastInsertId();
        login_user(['id' => $id, 'role' => 'passenger']);
        flash('success', 'Account created successfully. Welcome to TrackShield!');
        redirect('passenger/dashboard.php');
    }
}

function field_err(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<div class="field-error">' . e($errors[$k]) . '</div>' : '';
}

$pageTitle = 'Register';
$layout = 'public';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-side">
        <h2>Join TrackShield</h2>
        <p>Create a passenger account to report railway emergencies and receive real-time updates.</p>
        <ul>
            <li><?= icon('check') ?> Unique reference number for every report</li>
            <li><?= icon('check') ?> Track status from submission to resolution</li>
            <li><?= icon('check') ?> Notifications on every update</li>
        </ul>
    </div>
    <div class="auth-form-wrap">
        <div class="auth-card">
            <h1>Create account</h1>
            <p class="muted">Passenger registration &mdash; takes less than a minute.</p>
            <?php if ($errors): ?>
                <div class="alert alert-error">Please correct the errors below.</div>
            <?php endif; ?>
            <form method="post" action="" data-validate-form>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="full_name">Full name <span class="req">*</span></label>
                    <input class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" id="full_name" name="full_name" value="<?= e($data['full_name']) ?>" maxlength="100" autocomplete="name" data-validate="required">
                    <?= field_err($errors, 'full_name') ?>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email <span class="req">*</span></label>
                        <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e($data['email']) ?>" maxlength="150" autocomplete="email" data-validate="required email">
                        <?= field_err($errors, 'email') ?>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone <span class="req">*</span></label>
                        <input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" type="tel" id="phone" name="phone" value="<?= e($data['phone']) ?>" maxlength="16" autocomplete="tel" data-validate="required phone">
                        <?= field_err($errors, 'phone') ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password <span class="req">*</span></label>
                    <div class="password-wrap">
                        <input class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" type="password" id="password" name="password" maxlength="72" autocomplete="new-password" data-validate="required password" data-strength="#pwMeter">
                        <button type="button" class="toggle-pass small">Show</button>
                    </div>
                    <div class="pw-meter" id="pwMeter"><span></span></div>
                    <div class="form-hint">Min. 8 characters with upper &amp; lower case, a number and a symbol.</div>
                    <?= field_err($errors, 'password') ?>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm password <span class="req">*</span></label>
                    <input class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" type="password" id="confirm_password" name="confirm_password" maxlength="72" autocomplete="new-password" data-validate="required" data-match="password">
                    <?= field_err($errors, 'confirm_password') ?>
                </div>
                <div class="form-group">
                    <label style="display:flex;gap:8px;align-items:flex-start;font-weight:400;color:var(--muted)">
                        <input type="checkbox" name="terms" value="1" <?= !empty($_POST['terms']) ? 'checked' : '' ?> style="margin-top:4px">
                        I confirm that the emergencies I report will be genuine. False reporting is an offence.
                    </label>
                    <?= field_err($errors, 'terms') ?>
                </div>
                <button class="btn btn-accent btn-block btn-lg" type="submit" data-loading="Creating account...">Create account</button>
            </form>
            <p class="switch">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
