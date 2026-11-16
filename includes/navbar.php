<?php
/** Navigation: public top navbar, or role-based sidebar + topbar for dashboards. */
$navUser = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentDir    = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

$brand = '<a class="brand" href="' . e(url('index.php')) . '"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm-1 5h2v6h-2V7Zm0 8h2v2h-2v-2Z"/></svg></span><span>Track<strong>Shield</strong></span></a>';

if ($layout !== 'app'): ?>
<header class="site-nav">
    <div class="container nav-inner">
        <?= $brand ?>
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" data-toggle="nav"><?= icon('menu') ?></button>
        <nav class="nav-links" id="siteNav">
            <a href="<?= e(url('index.php#features')) ?>">Features</a>
            <a href="<?= e(url('index.php#how-it-works')) ?>">How it Works</a>
            <a href="<?= e(url('index.php#helplines')) ?>">Helplines</a>
            <?php if ($navUser): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url(dashboard_path($navUser['role']))) ?>">My Dashboard</a>
            <?php else: ?>
                <a href="<?= e(url('login.php')) ?>" class="<?= $currentScript === 'login.php' ? 'active' : '' ?>">Login</a>
                <a class="btn btn-accent btn-sm" href="<?= e(url('register.php')) ?>">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="site-main">
<?php else:
    $navRole = $navUser['role'] ?? 'passenger';
    $menus = [
        'passenger' => [
            ['passenger/dashboard.php', 'Dashboard', 'dashboard'],
            ['passenger/report-emergency.php', 'Report Emergency', 'alert'],
            ['passenger/my-emergencies.php', 'My Emergencies', 'list'],
            ['passenger/notifications.php', 'Notifications', 'bell'],
        ],
        'response' => [
            ['response/dashboard.php', 'Dashboard', 'dashboard'],
            ['response/assigned-emergencies.php', 'Assigned Emergencies', 'list'],
            ['response/notifications.php', 'Notifications', 'bell'],
        ],
        'admin' => [
            ['admin/dashboard.php', 'Dashboard', 'dashboard'],
            ['admin/emergencies.php', 'Emergencies', 'alert'],
            ['admin/manage-teams.php', 'Response Teams', 'team'],
            ['admin/manage-users.php', 'Users', 'users'],
            ['admin/categories.php', 'Categories', 'tag'],
            ['admin/reports.php', 'Reports & Analytics', 'chart'],
            ['admin/notifications.php', 'Notifications', 'bell'],
        ],
    ];
    $navRoleLabel = ['passenger' => 'Passenger', 'response' => 'Response Team', 'admin' => 'Administrator'][$navRole];
    $unread = $navUser ? unread_notification_count(db(), (int)$navUser['id']) : 0;
    // Treat detail pages as part of their parent section.
    $sectionMap = ['emergency-details.php' => ['passenger' => 'my-emergencies.php', 'admin' => 'emergencies.php', 'response' => 'assigned-emergencies.php'],
                   'assign-team.php' => ['admin' => 'emergencies.php'], 'update-status.php' => ['response' => 'assigned-emergencies.php']];
    $activeScript = $sectionMap[$currentScript][$navRole] ?? $currentScript;
?>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-head"><?= $brand ?></div>
        <div class="sidebar-role"><?= e($navRoleLabel) ?> Portal</div>
        <nav class="sidebar-nav">
            <?php foreach ($menus[$navRole] as [$href, $label, $ic]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= basename($href) === $activeScript ? 'active' : '' ?>">
                    <?= icon($ic) ?><span><?= e($label) ?></span>
                    <?php if ($ic === 'bell' && $unread > 0): ?><span class="nav-count"><?= $unread ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-help">
            <strong>Emergency?</strong>
            <span>Railway Helpline <b>139</b></span>
            <span>RPF Security <b>182</b></span>
        </div>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="sidebar-logout">
            <?= csrf_field() ?>
            <button type="submit"><?= icon('logout') ?><span>Logout</span></button>
        </form>
    </aside>
    <div class="sidebar-overlay" data-toggle="sidebar"></div>
    <div class="app-main">
        <header class="topbar">
            <button class="icon-btn sidebar-toggle" type="button" aria-label="Toggle menu" data-toggle="sidebar"><?= icon('menu') ?></button>
            <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
            <div class="topbar-actions">
                <a class="icon-btn bell" href="<?= e(url($navRole . '/notifications.php')) ?>" aria-label="Notifications">
                    <?= icon('bell') ?><?php if ($unread > 0): ?><span class="dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
                </a>
                <div class="user-chip">
                    <span class="avatar"><?= e(mb_strtoupper(mb_substr($navUser['full_name'] ?? '?', 0, 1))) ?></span>
                    <span class="user-meta"><strong><?= e($navUser['full_name'] ?? '') ?></strong><small><?= e($navRoleLabel) ?></small></span>
                </div>
            </div>
        </header>
        <main class="content">
<?php endif; ?>
