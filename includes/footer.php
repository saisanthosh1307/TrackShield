<?php
/** Page footer: closes layout wrappers, renders toasts & scripts. $extraScripts optional. */
$layout = $layout ?? 'public';
?>
<?php if ($layout === 'app'): ?>
        </main>
    </div><!-- /.app-main -->
</div><!-- /.app -->
<?php else: ?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm-1 5h2v6h-2V7Zm0 8h2v2h-2v-2Z"/></svg>
                </span>
                <span>Track<strong>Shield</strong></span>
            </a>
            <p class="muted">Centralised railway emergency reporting, coordination and response management.</p>
        </div>
        <div>
            <h4>Emergency Helplines</h4>
            <ul class="plain-list">
                <li>Railway Helpline: <strong>139</strong></li>
                <li>Railway Protection Force: <strong>182</strong></li>
                <li>Ambulance: <strong>108</strong> &middot; Fire: <strong>101</strong></li>
                <li>National Emergency: <strong>112</strong></li>
            </ul>
        </div>
        <div>
            <h4>Quick Links</h4>
            <ul class="plain-list">
                <li><a href="<?= e(url('index.php#features')) ?>">Features</a></li>
                <li><a href="<?= e(url('index.php#how-it-works')) ?>">How it works</a></li>
                <li><a href="<?= e(url('login.php')) ?>">Login</a></li>
                <li><a href="<?= e(url('register.php')) ?>">Register</a></li>
            </ul>
        </div>
    </div>
    <div class="container footer-bottom">&copy; <?= date('Y') ?> TrackShield &middot; Railway Emergency Management System</div>
</footer>
<?php endif; ?>

<!-- Toasts -->
<div class="toast-stack" id="toastStack" aria-live="polite">
<?php foreach (get_flashes() as $f): ?>
    <div class="toast toast-<?= e($f['type']) ?>" role="status"><span><?= e($f['message']) ?></span><button type="button" class="toast-close" aria-label="Dismiss">&times;</button></div>
<?php endforeach; ?>
</div>

<!-- Confirmation dialog -->
<div class="modal" id="confirmModal" hidden>
    <div class="modal-backdrop" data-close></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <h3 id="confirmTitle">Please confirm</h3>
        <p id="confirmMessage">Are you sure?</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-close>Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmOk">Confirm</button>
        </div>
    </div>
</div>

<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<script src="<?= e(url('assets/js/validation.js')) ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
