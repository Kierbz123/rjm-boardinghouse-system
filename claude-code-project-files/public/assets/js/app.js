// Shared application JS. Currently empty on purpose: all page-specific
// GSAP timelines live inline in their views (see src/Views/admin/dashboard.php,
// staff/dashboard.php, staff/maintenance_queue.php, portal/dashboard.php).
// This file is the hook for genuinely cross-page behavior as it emerges,
// so views don't each need to remember to include a new shared script tag.

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

    function showInAppNotification(data) {
        var banner = document.getElementById('poll-notification-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'poll-notification-banner';
            banner.style.cssText = [
                'position:fixed', 'top:0', 'left:0', 'right:0',
                'background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%)',
                'color:white', 'padding:1rem', 'z-index:9999',
                'display:none', 'box-shadow:0 4px 12px rgba(0,0,0,0.3)',
                'animation:pollSlideDown 0.3s ease-out'
            ].join(';');
            document.body.appendChild(banner);

            var style = document.createElement('style');
            style.textContent =
                '@keyframes pollSlideDown{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}' +
                '@keyframes pollSlideUp{from{transform:translateY(0);opacity:1}to{transform:translateY(-100%);opacity:0}}';
            document.head.appendChild(style);
        }

        var icon = getNotificationIcon(data.type_label);
        banner.innerHTML =
            '<div style="display:flex;align-items:center;gap:0.75rem;max-width:1200px;margin:0 auto;">' +
                '<span style="font-size:1.25rem;">' + icon + '</span>' +
                '<div style="flex:1;">' +
                    '<div style="font-weight:600;font-size:0.875rem;">' + escapeHtml(data.type_label) + '</div>' +
                    '<div style="font-size:0.8rem;opacity:0.9;">' + escapeHtml(data.message) + '</div>' +
                '</div>' +
                '<button onclick="this.closest(\'#poll-notification-banner\').style.display=\'none\'" ' +
                    'style="background:rgba(255,255,255,0.1);border:none;color:white;padding:0.5rem 1rem;border-radius:0.375rem;cursor:pointer;font-size:0.75rem;">' +
                    'Dismiss' +
                '</button>' +
            '</div>';

        banner.style.display = 'block';
        banner.style.animation = 'pollSlideDown 0.3s ease-out';

        setTimeout(function() {
            if (banner && banner.style.display !== 'none') {
                banner.style.animation = 'pollSlideUp 0.3s ease-out';
                setTimeout(function() { if (banner) banner.style.display = 'none'; }, 300);
            }
        }, 8000);
    }

    function getNotificationIcon(type) {
        var t = (type || '').toLowerCase();
        if (t.indexOf('sos') >= 0 || t.indexOf('emergency') >= 0) return '🚨';
        if (t.indexOf('maintenance') >= 0 || t.indexOf('repair') >= 0) return '🔧';
        if (t.indexOf('incident') >= 0) return '⚠️';
        if (t.indexOf('rent') >= 0 || t.indexOf('payment') >= 0) return '💳';
        if (t.indexOf('penalty') >= 0) return '⚡';
        return '🔔';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

})();
