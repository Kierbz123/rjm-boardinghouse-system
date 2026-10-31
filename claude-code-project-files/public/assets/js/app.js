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

// A Room Type select (data-filters-beds="<bed select id>") narrows that select to the vacant beds
// whose option carries the same data-room-type. Options are rebuilt, not hidden: Safari ignores
// display:none on <option>.
(function () {
    document.querySelectorAll('select[data-filters-beds]').forEach(function (typeSelect) {
        var bedSelect = document.getElementById(typeSelect.dataset.filtersBeds);
        if (!bedSelect) return;
        var placeholder = bedSelect.options[0];
        var beds = Array.prototype.slice.call(bedSelect.options, 1);
        function filter() {
            var type = typeSelect.value, kept = bedSelect.value;
            var shown = beds.filter(function (o) { return !type || o.dataset.roomType === type; });
            bedSelect.replaceChildren.apply(bedSelect, [placeholder].concat(shown));
            placeholder.textContent = shown.length || !type ? '— Choose Vacant Bed —' : '— No vacant bed of this type —';
            bedSelect.value = shown.some(function (o) { return o.value === kept; }) ? kept : '';
        }
        typeSelect.addEventListener('change', filter);
        filter();
    });
})();

// ══════════════════════════════════════════════════════════════
// CUSTOM ASYNC CONFIRM & ALERT MODAL COMPONENT
// ══════════════════════════════════════════════════════════════
(function () {
    let modalEl = null;
    let activeResolve = null;
    let prevActiveElement = null;

    const ICONS = {
        trash: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',
        warning: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        logout: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        info: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        success: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        question: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
    };

    function ensureModal() {
        if (modalEl) return modalEl;

        const markup = `
            <div id="custom-confirm-modal" class="custom-confirm-backdrop" role="dialog" aria-modal="true" aria-labelledby="custom-confirm-title" aria-describedby="custom-confirm-subtitle">
                <div class="custom-confirm-card" role="document">
                    <div id="custom-confirm-icon" class="custom-confirm-icon-wrap" aria-hidden="true"></div>
                    <h3 id="custom-confirm-title" class="custom-confirm-title"></h3>
                    <p id="custom-confirm-subtitle" class="custom-confirm-subtitle"></p>
                    <div class="custom-confirm-actions">
                        <button type="button" id="custom-confirm-cancel-btn" class="custom-confirm-btn custom-confirm-btn-cancel">Cancel</button>
                        <button type="button" id="custom-confirm-ok-btn" class="custom-confirm-btn custom-confirm-btn-confirm">Confirm</button>
                    </div>
                </div>
            </div>
        `;
        const div = document.createElement('div');
        div.innerHTML = markup.trim();
        modalEl = div.firstElementChild;
        document.body.appendChild(modalEl);

        const cancelBtn = modalEl.querySelector('#custom-confirm-cancel-btn');
        const okBtn = modalEl.querySelector('#custom-confirm-ok-btn');

        cancelBtn.addEventListener('click', function () { closeWith(false); });
        okBtn.addEventListener('click', function () { closeWith(true); });

        // Click outside on backdrop cancels confirm / dismisses alert
        modalEl.addEventListener('click', function (e) {
            if (e.target === modalEl) {
                closeWith(false);
            }
        });

        // Keydown handler: Escape to close, Tab to trap focus
        document.addEventListener('keydown', function (e) {
            if (!modalEl || !modalEl.classList.contains('is-active')) return;

            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                closeWith(false);
                return;
            }

            if (e.key === 'Tab') {
                const focusable = Array.from(modalEl.querySelectorAll('button:not([disabled]):not([style*="display: none"])'));
                if (focusable.length === 0) return;

                const first = focusable[0];
                const last = focusable[focusable.length - 1];

                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        return modalEl;
    }

    function closeWith(val) {
        if (!modalEl || !modalEl.classList.contains('is-active')) return;

        modalEl.classList.remove('is-active');
        document.body.style.overflow = '';

        if (prevActiveElement && typeof prevActiveElement.focus === 'function') {
            try { prevActiveElement.focus(); } catch (err) {}
            prevActiveElement = null;
        }

        if (activeResolve) {
            const res = activeResolve;
            activeResolve = null;
            res(val);
        }
    }

    function showConfirmModal(opts) {
        if (typeof opts === 'string') {
            opts = { message: opts };
        }
        opts = opts || {};

        return new Promise(function (resolve) {
            ensureModal();

            // Save previous active element for focus restoration
            prevActiveElement = document.activeElement;

            const title = opts.title || 'Are you sure?';
            const message = opts.message || opts.desc || opts.text || '';
            const confirmText = opts.confirmText || opts.confirmLabel || 'Confirm';
            const cancelText = opts.cancelText || opts.cancelLabel || 'Cancel';
            const isAlert = Boolean(opts.isAlert);

            // Determine modal style and action type
            let type = opts.type || '';
            if (!type) {
                const combined = (title + ' ' + message).toLowerCase();
                if (opts.danger || combined.includes('delete') || combined.includes('remove') || combined.includes('deactivate')) {
                    type = 'danger';
                } else if (combined.includes('logout') || combined.includes('log out') || combined.includes('exit')) {
                    type = 'logout';
                } else if (combined.includes('warn') || combined.includes('recalculate') || combined.includes('caution')) {
                    type = 'warning';
                } else if (combined.includes('paid') || combined.includes('success')) {
                    type = 'success';
                } else {
                    type = 'info';
                }
            }
            if (opts.danger) {
                type = 'danger';
            }

            // Select icon SVG
            let iconSvg = '';
            if (opts.icon && ICONS[opts.icon]) {
                iconSvg = ICONS[opts.icon];
            } else if (typeof opts.icon === 'string' && opts.icon.startsWith('<svg')) {
                iconSvg = opts.icon;
            } else if (type === 'danger' || type === 'delete') {
                iconSvg = ICONS.trash;
            } else if (type === 'logout') {
                iconSvg = ICONS.logout;
            } else if (type === 'warning') {
                iconSvg = ICONS.warning;
            } else if (type === 'success') {
                iconSvg = ICONS.success;
            } else {
                iconSvg = ICONS.info;
            }

            // References
            const iconWrap = modalEl.querySelector('#custom-confirm-icon');
            const titleEl = modalEl.querySelector('#custom-confirm-title');
            const subtitleEl = modalEl.querySelector('#custom-confirm-subtitle');
            const cancelBtn = modalEl.querySelector('#custom-confirm-cancel-btn');
            const okBtn = modalEl.querySelector('#custom-confirm-ok-btn');

            // Icon styling
            iconWrap.className = 'custom-confirm-icon-wrap';
            if (type === 'danger' || type === 'delete') iconWrap.classList.add('type-danger');
            else if (type === 'warning') iconWrap.classList.add('type-warning');
            else if (type === 'success') iconWrap.classList.add('type-success');
            else if (type === 'info') iconWrap.classList.add('type-info');
            iconWrap.innerHTML = iconSvg;

            // Content
            titleEl.textContent = title;
            subtitleEl.textContent = message;
            subtitleEl.style.display = message ? '' : 'none';

            // Buttons
            cancelBtn.textContent = cancelText;
            cancelBtn.style.display = isAlert ? 'none' : '';

            okBtn.textContent = confirmText;
            okBtn.className = 'custom-confirm-btn custom-confirm-btn-confirm';
            if (type === 'danger' || type === 'delete') okBtn.classList.add('btn-danger');
            else if (type === 'warning') okBtn.classList.add('btn-warning');
            else if (type === 'success') okBtn.classList.add('btn-success');

            // Callback resolution supporting onConfirm / onCancel callbacks
            activeResolve = function (result) {
                if (result && typeof opts.onConfirm === 'function') {
                    opts.onConfirm();
                } else if (!result && typeof opts.onCancel === 'function') {
                    opts.onCancel();
                }
                resolve(result);
            };

            // Lock scrolling
            document.body.style.overflow = 'hidden';

            // Reveal modal
            modalEl.classList.add('is-active');

            // Focus management
            setTimeout(function () {
                if (isAlert) {
                    okBtn.focus();
                } else {
                    cancelBtn.focus();
                }
            }, 50);
        });
    }

    function showAlertModal(opts) {
        if (typeof opts === 'string') {
            opts = { message: opts, title: 'Notice' };
        }
        opts = opts || {};
        opts.isAlert = true;
        opts.confirmText = opts.confirmText || opts.confirmLabel || 'OK';
        return showConfirmModal(opts).then(function () {});
    }

    // Attach to window
    window.showConfirmModal = showConfirmModal;
    window.showAlertModal = showAlertModal;
    window.confirmModal = showConfirmModal;
    window.alertModal = showAlertModal;

    // Friendly polyfill/override for window.alert
    window.alert = function (message) {
        return showAlertModal(message);
    };

    // Global declarative form confirmation handler
    document.addEventListener('submit', async function (e) {
        const form = e.target;
        if (!form || !form.hasAttribute || !form.hasAttribute('data-confirm')) return;
        // Do not intercept if page has its own legacy confirm dialog handler (e.g. staff.php)
        if (document.getElementById('confirm-dialog')) return;

        if (form.__isSubmittingViaModal) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        const title = form.getAttribute('data-confirm-title') || 'Are you sure?';
        const message = form.getAttribute('data-confirm') || '';
        const confirmText = form.getAttribute('data-confirm-btn') || form.getAttribute('data-confirm-label') || 'Confirm';
        const cancelText = form.getAttribute('data-cancel-btn') || 'Cancel';
        const danger = form.hasAttribute('data-confirm-danger');
        const type = danger ? 'danger' : (form.getAttribute('data-confirm-type') || 'info');

        const confirmed = await window.showConfirmModal({
            title: title,
            message: message,
            confirmText: confirmText,
            cancelText: cancelText,
            type: type,
            danger: danger
        });

        if (confirmed) {
            form.__isSubmittingViaModal = true;
            const btn = e.submitter || form.querySelector('button[type="submit"], button:not([type])');
            if (btn && (btn.matches('.btn, .btn-danger, .btn-danger-solid') || btn.classList.contains('custom-confirm-btn'))) {
                btn.classList.add('is-loading');
                btn.setAttribute('aria-busy', 'true');
            }
            if (typeof form.requestSubmit === 'function') {
                try {
                    form.requestSubmit(e.submitter);
                } catch (err) {
                    form.submit();
                }
            } else {
                form.submit();
            }
        }
    }, true);
})();

/**
 * Digits-only fields (phone numbers). Any input marked data-digits drops everything that is not
 * a digit as it is typed or pasted; data-digits="11" also caps it at that many digits. One
 * delegated listener so a new phone field only needs the attribute, not another copy of this.
 *
 * The cap lives here and NOT in maxlength on purpose: the browser applies maxlength to the raw
 * string before this runs, so a pasted "+63 917 123 4567" would be cut to "+63 917 123" and lose
 * the last digits. Counting digits after stripping is the only count that means anything here.
 */
(function () {
    document.addEventListener('input', function (e) {
        const input = e.target;
        if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-digits')) {
            return;
        }
        const max = parseInt(input.getAttribute('data-digits') || '', 10);
        let cleaned = input.value.replace(/\D/g, '');
        if (Number.isFinite(max) && max > 0) {
            cleaned = cleaned.slice(0, max);
        }
        if (cleaned !== input.value) {
            // Keep the caret where the typing was, counting only the digits kept before it.
            const kept = input.value.slice(0, input.selectionStart ?? input.value.length).replace(/\D/g, '').length;
            input.value = cleaned;
            try {
                input.setSelectionRange(kept, kept);
            } catch (err) {
                /* a field that does not support selection (some mobile keyboards) */
            }
        }
    });
})();
