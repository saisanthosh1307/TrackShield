/* TrackShield - Client-side form validation (server validates everything again). */
(function () {
    'use strict';

    var rules = {
        required: function (v) { return v.trim() !== '' || 'This field is required.'; },
        email: function (v) { return v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) || 'Enter a valid email address.'; },
        phone: function (v) { return v === '' || /^\+?[0-9]{10,15}$/.test(v.replace(/[\s-]/g, '')) || 'Enter a valid phone number (10-15 digits).'; },
        password: function (v) {
            if (v === '') return true;
            if (v.length < 8) return 'At least 8 characters.';
            if (!/[A-Z]/.test(v)) return 'Include an uppercase letter.';
            if (!/[a-z]/.test(v)) return 'Include a lowercase letter.';
            if (!/[0-9]/.test(v)) return 'Include a number.';
            if (!/[^A-Za-z0-9]/.test(v)) return 'Include a special character.';
            return true;
        },
        train: function (v) { return v === '' || /^[A-Za-z0-9 -]{3,20}$/.test(v) || 'Enter a valid train number.'; }
    };

    function setError(field, msg) {
        var group = field.closest('.form-group') || field.parentElement;
        var err = group.querySelector('.field-error.js');
        if (msg) {
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
            if (!err) { err = document.createElement('div'); err.className = 'field-error js'; group.appendChild(err); }
            err.textContent = msg;
        } else {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
            if (err) err.remove();
        }
    }

    function validateField(field) {
        if (field.type === 'radio') {
            if (!field.hasAttribute('data-validate')) return true;
            var group = field.form.querySelectorAll('input[name="' + field.name + '"]');
            var ok = Array.prototype.some.call(group, function (r) { return r.checked; });
            var holder = field.closest('.form-group');
            var e = holder.querySelector('.field-error.js');
            if (!ok) { if (!e) { e = document.createElement('div'); e.className = 'field-error js'; holder.appendChild(e); } e.textContent = 'Please select an option.'; }
            else if (e) e.remove();
            return ok;
        }
        if (field.type === 'file') {
            var f = field.files[0];
            if (!f) return true;
            var max = parseInt(field.dataset.maxSize || '5242880', 10);
            if (!/^image\/(jpeg|png|gif|webp)$/.test(f.type)) { setError(field, 'Only JPG, PNG, GIF or WEBP images are allowed.'); return false; }
            if (f.size > max) { setError(field, 'Image must be smaller than ' + Math.round(max / 1048576) + ' MB.'); return false; }
            setError(field, null); return true;
        }
        var list = (field.dataset.validate || '').split(' ').filter(Boolean);
        var v = field.value;
        for (var i = 0; i < list.length; i++) {
            var fn = rules[list[i]];
            if (!fn) continue;
            var res = fn(v);
            if (res !== true) { setError(field, res); return false; }
        }
        if (field.dataset.match) {
            var other = field.form.querySelector('[name="' + field.dataset.match + '"]');
            if (other && other.value !== v) { setError(field, 'Passwords do not match.'); return false; }
        }
        if (field.maxLength > 0 && v.length > field.maxLength) { setError(field, 'Too long.'); return false; }
        if (field.dataset.minlength && v.trim().length < +field.dataset.minlength && v.trim() !== '') {
            setError(field, 'Please enter at least ' + field.dataset.minlength + ' characters.'); return false;
        }
        setError(field, null);
        return true;
    }

    document.querySelectorAll('form[data-validate-form]').forEach(function (form) {
        form.setAttribute('novalidate', 'novalidate');
        var fields = form.querySelectorAll('[data-validate], input[type=file]');
        fields.forEach(function (f) {
            f.addEventListener(f.type === 'radio' || f.type === 'file' ? 'change' : 'blur', function () { validateField(f); });
        });
        form.addEventListener('submit', function (e) {
            var ok = true, first = null;
            fields.forEach(function (f) { if (!validateField(f)) { ok = false; first = first || f; } });
            if (!ok) {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (first) first.focus();
                if (window.showToast) window.showToast('Please correct the highlighted fields.', 'error');
                return;
            }
            var btn = form.querySelector('button[type=submit][data-loading]');
            if (btn && !form.dataset.confirm) { btn.disabled = true; btn.textContent = btn.dataset.loading; }
        });
    });

    /* Password strength meter */
    document.querySelectorAll('[data-strength]').forEach(function (inp) {
        var bar = document.querySelector(inp.dataset.strength + ' span');
        if (!bar) return;
        inp.addEventListener('input', function () {
            var v = inp.value, s = 0;
            if (v.length >= 8) s++;
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
            if (/[0-9]/.test(v)) s++;
            if (/[^A-Za-z0-9]/.test(v)) s++;
            var colors = ['#d62828', '#d62828', '#f47b20', '#b7791f', '#1f9d55'];
            bar.style.width = (s * 25) + '%';
            bar.style.background = colors[s];
        });
    });
})();
