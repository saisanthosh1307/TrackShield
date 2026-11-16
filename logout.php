<?php
require_once __DIR__ . '/includes/auth.php';

// Logout must be a POST with a valid CSRF token (prevents forced logout via links).
if (is_post() && csrf_valid($_POST['csrf_token'] ?? null)) {
    logout_user();
    session_start();
    flash('success', 'You have been logged out securely.');
}
redirect('login.php');
