/* TrackShield - Global UI behaviour */
(function () {
    'use strict';

    /* ---------- Toasts ---------- */
    function dismiss(toast) {
        toast.classList.add('hide');
        setTimeout(function () { toast.remove(); }, 300);
    }
    window.showToast = function (message, type) {
        var stack = document.getElementById('toastStack');
        if (!stack) return;
        var t = document.createElement('div');
        t.className = 'toast toast-' + (type || 'info');
        t.setAttribute('role', 'status');
        var s = document.createElement('span');
        s.textContent = message;
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'toast-close'; b.setAttribute('aria-label', 'Dismiss'); b.innerHTML = '&times;';
        t.appendChild(s); t.appendChild(b);
        stack.appendChild(t);
        setTimeout(function () { dismiss(t); }, 6000);
    };
    document.querySelectorAll('.toast').forEach(function (t) {
        setTimeout(function () { dismiss(t); }, 6000);
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.toast-close');
        if (btn) dismiss(btn.closest('.toast'));
    });

    /* ---------- Navigation toggles ---------- */
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-toggle]');
        if (!t) return;
        if (t.dataset.toggle === 'nav') {
            var nav = document.getElementById('siteNav');
            var open = nav.classList.toggle('open');
            t.setAttribute('aria-expanded', open ? 'true' : 'false');
        } else if (t.dataset.toggle === 'sidebar') {
            document.body.classList.toggle('sidebar-open');
        }
    });

    /* ---------- Confirmation dialog ----------
       <form data-confirm="Message" data-confirm-btn="Delete" data-confirm-variant="danger"> */
    var modal = document.getElementById('confirmModal');
    var pendingForm = null;
    function closeModal() { if (modal) modal.hidden = true; pendingForm = null; }
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || !form.dataset.confirm || !modal) return;
        if (form.dataset.confirmed === '1') { form.dataset.confirmed = ''; return; }
        e.preventDefault();
        pendingForm = form;
        pendingForm._submitter = e.submitter || null;
        document.getElementById('confirmMessage').textContent = form.dataset.confirm;
        var ok = document.getElementById('confirmOk');
        ok.textContent = form.dataset.confirmBtn || 'Confirm';
        ok.className = 'btn ' + (form.dataset.confirmVariant === 'danger' ? 'btn-danger' : 'btn-primary');
        modal.hidden = false;
        ok.focus();
    });
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target.hasAttribute('data-close')) closeModal();
        });
        document.getElementById('confirmOk').addEventListener('click', function () {
            if (!pendingForm) return;
            var f = pendingForm, sub = f._submitter;
            f.dataset.confirmed = '1';
            modal.hidden = true;
            if (sub && sub.name) {           // preserve the clicked button's name/value
                var h = document.createElement('input');
                h.type = 'hidden'; h.name = sub.name; h.value = sub.value;
                f.appendChild(h);
            }
            if (typeof f.requestSubmit === 'function') f.requestSubmit(); else f.submit();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeModal(); });
    }

    /* ---------- Password visibility ---------- */
    document.querySelectorAll('.toggle-pass').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('input');
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
        });
    });

    /* ---------- Auto-submit filters ---------- */
    document.querySelectorAll('form[data-autosubmit] select').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.form.submit(); });
    });

    /* ---------- Image drop zone with preview ---------- */
    document.querySelectorAll('.dropzone').forEach(function (zone) {
        var input = zone.querySelector('input[type=file]');
        var label = zone.querySelector('.dz-label');
        var preview = zone.querySelector('img');
        ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.add('drag'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('drag'); }); });
        input.addEventListener('change', function () {
            var f = input.files[0];
            if (!f) { preview.hidden = true; label.textContent = label.dataset.default; return; }
            label.textContent = f.name + ' (' + Math.round(f.size / 1024) + ' KB)';
            if (/^image\/(jpeg|png|gif|webp)$/.test(f.type)) {
                var r = new FileReader();
                r.onload = function (ev) { preview.src = ev.target.result; preview.hidden = false; };
                r.readAsDataURL(f);
            }
        });
    });

    /* ---------- Set max datetime to now on datetime-local inputs ---------- */
    document.querySelectorAll('input[type=datetime-local][data-max-now]').forEach(function (inp) {
        var d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        var now = d.toISOString().slice(0, 16);
        inp.max = now;
        if (!inp.value) inp.value = now;
    });
})();
