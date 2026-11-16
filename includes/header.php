<?php
/**
 * Page header. Set before including:
 *   $pageTitle  (string)  - page title
 *   $layout     (string)  - 'public' (default) or 'app' (sidebar dashboard)
 *   $extraHead  (string)  - optional extra <head> markup
 */
require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? 'Railway Emergency Management';
$layout    = $layout ?? 'public';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= e(url('assets/images/logo.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <?php if ($layout === 'app'): ?>
    <link rel="stylesheet" href="<?= e(url('assets/css/dashboard.css')) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(url('assets/css/responsive.css')) ?>">
    <?= $extraHead ?? '' ?>
</head>
<body class="layout-<?= e($layout) ?>">
<?php require __DIR__ . '/navbar.php'; ?>
