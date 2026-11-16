<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Railway Emergency Management System';
$layout = 'public';

// Public, aggregate-only platform statistics.
$pdo = db();
$stats = $pdo->query("SELECT COUNT(*) total,
        SUM(status = 'Resolved') resolved,
        SUM(status IN ('" . implode("','", ACTIVE_STATUSES) . "')) active
    FROM emergencies")->fetch();
$teams = (int) $pdo->query('SELECT COUNT(*) FROM response_teams')->fetchColumn();
$categories = get_categories($pdo);

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="eyebrow"><span class="pulse"></span> 24/7 railway emergency reporting</span>
            <h1>Report railway emergencies. <span>Get help faster.</span></h1>
            <p class="lead">TrackShield connects passengers, railway staff and emergency response teams on one secure platform &mdash; from the first report to final resolution.</p>
            <div class="hero-actions">
                <?php if (current_user()): ?>
                    <a class="btn btn-accent btn-lg" href="<?= e(url(dashboard_path(current_user()['role']))) ?>">Go to Dashboard</a>
                <?php else: ?>
                    <a class="btn btn-accent btn-lg" href="<?= e(url('register.php')) ?>"><?= icon('alert') ?> Report an Emergency</a>
                    <a class="btn btn-outline-light btn-lg" href="<?= e(url('login.php')) ?>">Sign in</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-card" aria-hidden="true">
            <h3>Live incident board (sample)</h3>
            <div class="hero-incident">
                <span class="ic" style="background:#d6282833;color:#ff8a8a"><?= icon('alert') ?></span>
                <div><strong>Smoke in coach S4</strong><small>Train 12627 &middot; Near Itarsi Jn</small></div>
                <span class="badge priority-critical">Critical</span>
            </div>
            <div class="hero-incident">
                <span class="ic" style="background:#f47b2033;color:#ffb57a"><?= icon('activity') ?></span>
                <div><strong>Passenger needs medical aid</strong><small>Platform 3 &middot; Central Station</small></div>
                <span class="badge status-dispatched">Dispatched</span>
            </div>
            <div class="hero-incident">
                <span class="ic" style="background:#1f9d5533;color:#7fe0aa"><?= icon('check') ?></span>
                <div><strong>Signal failure resolved</strong><small>Block section KM 214</small></div>
                <span class="badge status-resolved">Resolved</span>
            </div>
        </div>
    </div>
</section>

<section class="stats-strip">
    <div class="container grid">
        <div class="item"><strong><?= (int)$stats['total'] ?></strong><span>Emergencies reported</span></div>
        <div class="item"><strong><?= (int)$stats['active'] ?></strong><span>Active operations</span></div>
        <div class="item"><strong><?= (int)$stats['resolved'] ?></strong><span>Incidents resolved</span></div>
        <div class="item"><strong><?= $teams ?></strong><span>Response teams</span></div>
    </div>
</section>

<section class="section" id="features">
    <div class="container">
        <div class="section-head">
            <span class="kicker">Platform features</span>
            <h2>Everything needed to coordinate a railway emergency</h2>
            <p class="muted">A single digital workflow for passengers, administrators and response teams.</p>
        </div>
        <div class="feature-grid">
            <div class="feature"><span class="ic"><?= icon('alert', 22) ?></span><h3>Rapid Reporting</h3><p>Report accidents, fires, medical issues or suspicious activity in under a minute, with photos and location.</p></div>
            <div class="feature"><span class="ic"><?= icon('activity', 22) ?></span><h3>Live Status Tracking</h3><p>Every report gets a unique reference number and a full status timeline from submission to resolution.</p></div>
            <div class="feature"><span class="ic"><?= icon('team', 22) ?></span><h3>Team Assignment</h3><p>Administrators verify, prioritise and assign incidents to specialised railway response teams.</p></div>
            <div class="feature"><span class="ic"><?= icon('bell', 22) ?></span><h3>Instant Notifications</h3><p>In-app notifications keep passengers and teams informed at every stage of the response.</p></div>
            <div class="feature"><span class="ic"><?= icon('chart', 22) ?></span><h3>Reports &amp; Analytics</h3><p>Daily and monthly trends, category statistics and response-team performance at a glance.</p></div>
            <div class="feature"><span class="ic"><?= icon('team', 22) ?></span><h3>Secure by Design</h3><p>Role-based access, hashed passwords, CSRF protection and validated uploads protect every record.</p></div>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="kicker">Emergency categories</span>
            <h2>What you can report</h2>
        </div>
        <div class="category-chips">
            <?php foreach ($categories as $c): ?><span><?= e($c['category_name']) ?></span><?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" id="how-it-works">
    <div class="container">
        <div class="section-head">
            <span class="kicker">How it works</span>
            <h2>From report to resolution</h2>
        </div>
        <div class="steps">
            <div class="step"><h3>Report</h3><p>Passenger submits the emergency with category, location and optional photo evidence.</p></div>
            <div class="step"><h3>Verify &amp; Prioritise</h3><p>Administrators verify the report and set its priority: Critical, High or Medium.</p></div>
            <div class="step"><h3>Assign &amp; Respond</h3><p>The right response team is assigned and dispatched, posting progress updates.</p></div>
            <div class="step"><h3>Resolve</h3><p>The incident is resolved and the full history is retained for analysis.</p></div>
        </div>
    </div>
</section>

<section class="section section-alt" id="helplines">
    <div class="container">
        <div class="section-head">
            <span class="kicker">In immediate danger?</span>
            <h2>Call emergency helplines first</h2>
            <p class="muted">For life-threatening situations always call the helpline directly, then report on TrackShield.</p>
        </div>
        <div class="helpline-grid">
            <div class="helpline"><strong>139</strong><span>Railway Helpline</span></div>
            <div class="helpline"><strong>182</strong><span>Railway Protection Force</span></div>
            <div class="helpline"><strong>108</strong><span>Ambulance</span></div>
            <div class="helpline"><strong>112</strong><span>National Emergency</span></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="cta">
            <div>
                <h2>Help make every journey safer</h2>
                <p>Create a free passenger account and report emergencies in seconds.</p>
            </div>
            <a class="btn btn-primary btn-lg" href="<?= e(url(current_user() ? dashboard_path(current_user()['role']) : 'register.php')) ?>"><?= current_user() ? 'Open Dashboard' : 'Create Account' ?></a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
