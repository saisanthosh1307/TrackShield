<?php
require_once __DIR__ . '/includes/auth.php';

if ($u = current_user()) {
    redirect(dashboard_path($u['role']));
}

$errors = [];
$email = '';

if (is_post()) {
    verify_csrf();
    $email    = mb_strtolower(input('email'));
    $password = $_POST['password'] ?? '';

    // Basic brute-force throttling per session.
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? ['count' => 0, 'time' => time()];
    $att = &$_SESSION['login_attempts'];
    if (time() - $att['time'] > 900) {
        $att = ['count' => 0, 'time' => time()];
    }

    if ($att['count'] >= 5) {
        $errors[] = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } elseif (!valid_email($email) || $password === '') {
        $errors[] = 'Please enter a valid email and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $errors[] = 'Your account has been deactivated. Please contact the administrator.';
            } else {
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    db()->prepare('UPDATE users SET password = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                unset($_SESSION['login_attempts']);
                login_user($user);
                flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect(dashboard_path($user['role']));
            }
        } else {
            $att['count']++;
            $errors[] = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Login';
$layout = 'public';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-side">
        <h2>Secure access to TrackShield</h2>
        <p>Sign in to report emergencies, follow their progress, or manage the response.</p>
        <ul>
            <li><?= icon('check') ?> Passengers: report &amp; track emergencies</li>
            <li><?= icon('check') ?> Response teams: manage assigned incidents</li>
            <li><?= icon('check') ?> Administrators: coordinate &amp; analyse</li>
        </ul>
    </div>
    <div class="auth-form-wrap">
        <div class="auth-card">
            <h1>Sign in</h1>
            <p class="muted">Enter your registered email and password.</p>
            <?php if ($errors): ?>
                <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <form method="post" action="" data-validate-form>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="150" autocomplete="email" data-validate="required email" autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" data-validate="required">
                        <button type="button" class="toggle-pass small">Show</button>
                    </div>
                </div>
                <button class="btn btn-primary btn-block btn-lg" type="submit" data-loading="Signing in...">Sign in</button>
            </form>
            <p class="switch">New to TrackShield? <a href="<?= e(url('register.php')) ?>">Create a passenger account</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
