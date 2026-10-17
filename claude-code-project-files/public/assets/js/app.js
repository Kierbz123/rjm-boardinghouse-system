// Shared application JS, loaded on every layout page: the new-notification toast, the
// password byte counter and the submit-button loading state. Page-specific GSAP timelines
// live inline in their views (see src/Views/admin/dashboard.php, staff/dashboard.php,
// staff/maintenance_queue.php, portal/dashboard.php).

// In-app banner when a new notification arrives (data comes from nav.php's poll).
(function() {
    const userRole = document.body.dataset.userRole;
    if (!userRole) return; // Not logged in — nothing to poll

    let lastKnownCount = -1;

    // nav.php polls /api/notifications/unread and broadcasts the result; this only adds
    // the "new notification" banner, so each page makes one request per interval.
    document.addEventListener('rjm:notifications', function (e) {
            var data = e.detail;
            var count = Array.isArray(data) ? data.length : 0;

            // Show banner for brand-new notifications
            if (lastKnownCount >= 0 && count > lastKnownCount && Array.isArray(data) && data.length > 0) {
                var newest = data[0]; // first = most recent
                showInAppNotification({
                    type_label: newest.type || 'Notification',
                    message:    newest.message || 'You have a new notification.'
                });
            }
            lastKnownCount = count;
    });

    // A new notification shows as a toast in the same stack layout.php uses for flash messages
    // (.toast in input.css); SOS and emergencies use the red marker and stay until dismissed.
    function showInAppNotification(data) {
        var stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        var t = (data.type_label || '').toLowerCase();
        var urgent = t.indexOf('sos') >= 0 || t.indexOf('emergency') >= 0;

        var toast = document.createElement('div');
        toast.className = 'toast ' + (urgent ? 'toast-error' : 'toast-info');
        toast.setAttribute('role', urgent ? 'alert' : 'status');
        toast.innerHTML =
            '<span><strong style="display:block;font-weight:600;">' + escapeHtml(data.type_label) + '</strong>' +
            '<span style="opacity:0.8;">' + escapeHtml(data.message) + '</span></span>' +
            '<button type="button" aria-label="Dismiss">&times;</button>';
        toast.querySelector('button').addEventListener('click', function () { toast.remove(); });
        stack.appendChild(toast);

        if (!urgent) {
            setTimeout(function () {
                toast.classList.add('is-leaving');
                toast.addEventListener('animationend', function () { toast.remove(); }, { once: true });
            }, 8000);
        }
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    // Password fields carry data-max-bytes="72": the hash only reads 72 BYTES, so accented letters and
    // emoji count for more than one. Show "x / 72" while typing and stop the form with a clear message.
    document.querySelectorAll('input[data-max-bytes]').forEach(function (input) {
        var limit = parseInt(input.getAttribute('data-max-bytes'), 10);
        var counter = document.createElement('span');
        counter.className = 'text-[11px] text-neutral-500 block mt-1 byte-counter';
        counter.setAttribute('aria-live', 'polite');
        // Inside the flex eye-toggle wrapper the counter would sit beside the input, so go after the wrapper.
        var wrap = input.closest('.password-relative-wrap');
        (wrap || input).insertAdjacentElement('afterend', counter);
        function update() {
            var bytes = new TextEncoder().encode(input.value).length;
            var tooLong = bytes > limit;
            counter.textContent = bytes + ' / ' + limit + ' bytes' + (tooLong ? ' — too long: shorten it (accented letters and emoji count as more than one)' : '');
            counter.style.color = tooLong ? '#b91c1c' : '';
            input.setCustomValidity(tooLong ? 'The password can be at most ' + limit + ' bytes.' : '');
        }
        input.addEventListener('input', update);
        update();
    });

})();

// While a form submits, its button shows a spinner and can't be pressed twice (.is-loading in input.css).
// Checked on the next tick, after every page handler has run, so a submit a page cancelled (its own
// confirm dialog, a fetch-based form) never gets a spinner. Forms posted by script (form.submit())
// fire no submit event and are untouched.
(function () {
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var btn = e.submitter || form.querySelector('button[type="submit"], button:not([type])');
        if (!btn || !btn.matches('.btn, .btn-danger, .btn-danger-solid')) return;
        setTimeout(function () {
            if (e.defaultPrevented || (form.target && form.target !== '_self')) return;
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');
            // Safety net: a response that never navigates (a download, a stalled request) frees the button.
            setTimeout(clear.bind(null, btn), 10000);
        }, 0);
    });
    function clear(btn) { btn.classList.remove('is-loading'); btn.removeAttribute('aria-busy'); }
    // Back-button restores from cache with the spinner still showing; clear it.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.is-loading[aria-busy]').forEach(clear);
    });
})();
