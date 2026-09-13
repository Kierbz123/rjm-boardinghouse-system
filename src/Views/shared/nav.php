<?php
$role = $_SESSION['role'] ?? null;
if (!$role) {
    return;
}
$navItems = [
    'admin' => [
        '/admin/dashboard' => 'Dashboard',
        '/admin/boarders' => 'Boarders',
        '/admin/rooms' => 'Rooms & Beds',
        '/admin/payments' => 'Payments',
        '/admin/expenses' => 'Expenses',
        '/admin/penalty-rules' => 'Penalties',
        '/admin/occupancy' => 'Occupancy',
        '/staff/maintenance/history' => 'Maintenance History',
        '/staff/incidents/history' => 'Incident History',
    ],
    'staff' => [
        '/staff/dashboard' => 'Dashboard',
        '/staff/maintenance' => 'Maintenance Queue',
        '/staff/incidents' => 'Incidents',
        '/staff/maintenance/history' => 'Maintenance History',
        '/staff/incidents/history' => 'Incident History',
    ],
    'boarder' => [
        '/portal/dashboard' => 'Dashboard',
        '/portal/maintenance/new' => 'Report a Repair',
        '/portal/payments/new' => 'Pay Rent',
    ],
];
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$dashboardHome = match ($role) {
    'admin'   => '/admin/dashboard',
    'staff'   => '/staff/dashboard',
    'boarder' => '/portal/dashboard',
    default   => '/',
};
?>
<style>
    /* Suppress scrollbar on nav link row while retaining touch/trackpad horizontal scrollability */
    .nav-scroll-bar::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .nav-scroll-bar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Animation and hover effects for double-confirmation logout modal */
    @keyframes logoutModalFadeIn {
        0% { opacity: 0; }
        100% { opacity: 1; }
    }
    @keyframes logoutCardPop {
        0% { opacity: 0; transform: scale(0.9) translateY(12px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }
    .animate-logout-backdrop {
        animation: logoutModalFadeIn 180ms ease-out forwards;
    }
    .animate-logout-card {
        animation: logoutCardPop 220ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    #logout-modal-cancel:hover {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
    }
    #logout-modal-confirm:hover {
        filter: brightness(1.08);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
    }
    #logout-modal-confirm:active {
        transform: scale(0.97);
    }

    /* Mobile-specific navbar improvements */
    @media (max-width: 768px) {
        /* Hide desktop nav links on mobile */
        .desktop-nav-links {
            display: none !important;
        }

        /* Mobile menu container */
        .mobile-menu-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 9999;
            padding: 5rem 1.5rem 2rem;
            overflow-y: auto;
        }

        .mobile-menu-container.is-open {
            display: block;
            animation: mobileMenuFadeIn 200ms ease-out forwards;
        }

        @keyframes mobileMenuFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Mobile menu links */
        .mobile-nav-link {
            display: block;
            width: 100%;
            padding: 1rem 1.25rem;
            margin-bottom: 0.5rem;
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.08);
            color: white;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 150ms ease;
        }

        .mobile-nav-link:hover,
        .mobile-nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
        }

        .mobile-nav-link.active {
            background: rgba(52, 211, 153, 0.2);
            border-color: rgba(52, 211, 153, 0.4);
            color: #34d399;
        }

        /* Mobile hamburger button */
        .mobile-hamburger {
            display: flex !important;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            cursor: pointer;
            transition: all 150ms ease;
        }

        .mobile-hamburger:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .mobile-hamburger:active {
            transform: scale(0.95);
        }

        /* Close button for mobile menu */
        .mobile-menu-close {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 150ms ease;
        }

        .mobile-menu-close:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Compact user info on mobile navbar */
        .mobile-user-info {
            display: flex !important;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: #94a3b8;
        }

        /* Hide profile pill on mobile, show compact version */
        .desktop-profile-pill {
            display: none !important;
        }

        /* Mobile logout button styling */
        .mobile-logout-btn {
            width: 100%;
            padding: 1rem 1.25rem;
            margin-top: 1rem;
            border-radius: 0.75rem;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 150ms ease;
        }

        .mobile-logout-btn:hover {
            background: rgba(239, 68, 68, 0.25);
            border-color: rgba(239, 68, 68, 0.5);
            color: #f87171;
        }
    }

    @media (min-width: 769px) {
        .mobile-hamburger,
        .mobile-menu-container,
        .mobile-user-info {
            display: none !important;
        }
    }
</style>

<nav class="bg-neutral-900 text-white shadow-md sticky top-0 z-50 border-b border-neutral-800">
    <div style="max-width: 82rem;" class="mx-auto px-4 sm:px-6 py-2.5 flex items-center justify-between gap-4 w-full">
        <!-- Brand Logo (always single line, never squished) -->
        <a href="<?= htmlspecialchars($dashboardHome) ?>"
           class="font-semibold tracking-tight inline-flex items-center gap-2 text-sm hover:opacity-90 transition-opacity flex-shrink-0"
           style="white-space: nowrap;">
            <span style="color: #34d399; font-size: 0.75rem;">▲</span>
            <span class="font-bold text-white tracking-tight">RJM <span class="text-neutral-400 font-normal">Boardinghouse</span></span>
        </a>

        <?php if ($role): ?>
            <!-- Center Navigation Links (no ugly scrollbars, shrink-safe items) - Desktop only -->
            <div class="nav-scroll-bar desktop-nav-links flex items-center gap-1 overflow-x-auto min-w-0 flex-1 px-1 py-0.5">
                <?php foreach ($navItems[$role] ?? [] as $href => $label): ?>
                    <?php $isActive = $currentPath === $href; ?>
                    <a href="<?= htmlspecialchars($href) ?>"
                       class="px-2.5 py-1.5 rounded-md transition-all text-xs font-medium <?= $isActive ? 'bg-white/10 text-white font-semibold shadow-xs' : 'text-neutral-400 hover:text-white hover:bg-white/5' ?>"
                       style="white-space: nowrap; flex-shrink: 0;"
                       <?= $isActive ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Right Controls Cluster (never wraps or collides) -->
            <div class="flex items-center gap-3 text-sm flex-shrink-0">
                <!-- Mobile Hamburger Button -->
                <button id="mobile-menu-toggle" class="mobile-hamburger" aria-label="Open menu" aria-expanded="false">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <!-- Notifications Bell (Direct link to /notifications) -->
                <div class="relative flex items-center" id="notif-wrapper">
                    <a id="notif-bell"
                       href="/notifications"
                       class="relative cursor-pointer rounded-lg p-2 text-neutral-400 hover:text-white hover:bg-white/10 active:scale-95 transition-all flex items-center justify-center focus:outline-none <?= $currentPath === '/notifications' ? 'bg-white/15 text-white ring-1 ring-white/20' : '' ?>"
                       aria-label="Notifications"
                       title="Notifications &amp; Activity Log">
                        <!-- Modern SVG Bell Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-150">
                            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                        </svg>
                        <!-- Floating Badge with exact id="notif-count" -->
                        <span id="notif-count"
                              class="badge badge-error hidden absolute -top-1 -right-1 min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center border-2 border-neutral-900 shadow-sm leading-none"></span>
                    </a>
                    <!-- Hidden container with id="notif-dropdown" for automated background polling / test compatibility -->
                    <div id="notif-dropdown" class="hidden" aria-hidden="true"></div>
                </div>

                <!-- Mobile User Info (compact) -->
                <div class="mobile-user-info">
                    <span style="display:inline-block; width:0.35rem; height:0.35rem; border-radius:50%; background:#34d399;"></span>
                    <span><?= htmlspecialchars(substr($_SESSION['name'] ?? '', 0, 12)) ?></span>
                </div>

                <!-- User Profile Pill (clickable link to /profile) - Desktop only -->
                <a href="/profile"
                   class="desktop-profile-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white/5 border border-neutral-700/70 text-xs text-neutral-300 font-medium hover:text-white hover:bg-white/10 hover:border-neutral-500 transition-all duration-150 cursor-pointer <?= $currentPath === '/profile' ? 'bg-white/15 text-white ring-1 ring-white/20' : '' ?>"
                   style="white-space: nowrap; text-decoration: none;"
                   title="View Profile &amp; Account Settings">
                    <span style="display:inline-block; width:0.4rem; height:0.4rem; border-radius:50%; background:#34d399;"></span>
                    <span><?= htmlspecialchars($_SESSION['name'] ?? '') ?></span>
                    <span class="text-neutral-400 capitalize font-normal">· <?= htmlspecialchars($role) ?></span>
                </a>

                <!-- Logout Trigger - Desktop only -->
                <div class="relative flex items-center flex-shrink-0 desktop-profile-pill" id="logout-wrap">
                    <form method="post" action="/logout" id="logout-form" class="hidden">
                        <?= \App\Support\Csrf::field() ?>
                    </form>

                    <button id="logout-btn" type="button"
                            class="btn btn-secondary bg-transparent border-neutral-700 text-neutral-300 hover:text-white hover:bg-white/10 !py-1 !px-3 !text-xs transition-all duration-150 cursor-pointer"
                            style="white-space: nowrap;">
                        Logout
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mobile Menu Container -->
    <div id="mobile-menu" class="mobile-menu-container">
        <button id="mobile-menu-close" class="mobile-menu-close" aria-label="Close menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <div style="max-width: 24rem; margin: 0 auto;">
            <h2 style="font-size: 1.25rem; font-weight: 700; color: white; margin-bottom: 0.5rem;">
                <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>
            </h2>
            <p style="font-size: 0.875rem; color: #94a3b8; margin-bottom: 2rem;">
                <?= htmlspecialchars(ucfirst($role)) ?> Account
            </p>

            <!-- Mobile Navigation Links -->
            <div style="display: flex; flex-direction: column;">
                <?php foreach ($navItems[$role] ?? [] as $href => $label): ?>
                    <?php $isActive = $currentPath === $href; ?>
                    <a href="<?= htmlspecialchars($href) ?>"
                       class="mobile-nav-link <?= $isActive ? 'active' : '' ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Mobile Logout Button -->
            <form method="post" action="/logout" id="mobile-logout-form" class="hidden">
                <?= \App\Support\Csrf::field() ?>
            </form>
            <button id="mobile-logout-btn" type="button" class="mobile-logout-btn">
                <span style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Logout</span>
                </span>
            </button>
        </div>
    </div>

    <?php if ($role): ?>
        <!-- Double Confirmation Logout Modal -->
        <div id="logout-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:99999; background:rgba(15,23,42,0.55); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;" role="dialog" aria-modal="true" aria-labelledby="logout-modal-heading">
            <div id="logout-confirm-card" style="background: rgb(255, 255, 255); border-radius: 1rem; padding: 1.5rem 1.75rem; max-width: 22rem; width: 100%; margin: 0px auto; text-align: center; box-shadow: rgba(0, 0, 0, 0.25) 0px 25px 50px -12px;">
                
                <!-- Big Round Icon (!) -->
                <div id="logout-modal-icon-wrap" style="width: 3.5rem; height: 3.5rem; border-radius: 50%; border: 2px solid rgb(239, 68, 68); color: rgb(220, 38, 38); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0px auto 0.75rem; user-select: none;">
                    !
                </div>

                <!-- Title -->
                <h3 id="logout-modal-heading" style="font-size: 1.1rem; font-weight: 700; color: #0f172a; letter-spacing: -0.01em; margin: 0 0 0.375rem;">Are you sure?</h3>

                <!-- Description -->
                <p id="logout-modal-subtext" style="font-size: 0.8rem; color: #64748b; line-height: 1.6; margin: 0 0 1.5rem; padding: 0 0.5rem;">Are you sure you want to log out of your account?</p>

                <!-- Actions -->
                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; padding-top: 0.25rem;">
                    <button type="button" id="logout-modal-cancel" style="padding: 0.5rem 1rem; border-radius: 0.75rem; font-size: 0.75rem; font-weight: 600; color: #64748b; background: transparent; border: none; cursor: pointer; transition: all 150ms;">
                        Cancel
                    </button>
                    <button type="button" id="logout-modal-confirm" style="color: rgb(255, 255, 255); font-weight: 600; border-radius: 0.75rem; padding: 0.5rem 1.25rem; font-size: 0.75rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem; transition: 150ms; background: linear-gradient(135deg, rgb(239, 68, 68), rgb(220, 38, 38));">
                        <span id="logout-modal-confirm-label">Yes</span>
                        <span style="font-size: 0.95rem;">&rarr;</span>
                    </button>
                </div>
            </div>
        </div>
        <script>
        /* ── Notifications Logic ── */
        (function () {
            const csrfToken = <?= json_encode(\App\Support\Csrf::token()) ?>;
            const countEl = document.getElementById('notif-count');
            const dropdownEl = document.getElementById('notif-dropdown');
            const listBody = document.getElementById('notif-list-body');
            const headerBadge = document.getElementById('notif-header-badge');
            const bellEl = document.getElementById('notif-bell');
            const containerEl = document.getElementById('notif-wrapper');
            let cached = [];

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function formatTimeAgo(dateStr) {
                if (!dateStr) return '';
                const date = new Date(dateStr.replace(/-/g, '/'));
                const now = new Date();
                const diffSec = Math.floor((now - date) / 1000);
                if (diffSec < 60) return 'Just now';
                const diffMin = Math.floor(diffSec / 60);
                if (diffMin < 60) return diffMin + 'm ago';
                const diffHours = Math.floor(diffMin / 60);
                if (diffHours < 24) return diffHours + 'h ago';
                const diffDays = Math.floor(diffHours / 24);
                if (diffDays < 7) return diffDays + 'd ago';
                return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
            }

            function getNotifConfig(type) {
                type = (type || '').toLowerCase();
                if (type.includes('sos')) {
                    return {
                        icon: '🚨',
                        badgeBg: '#fef2f2',
                        badgeColor: '#dc2626',
                        label: 'Emergency SOS'
                    };
                }
                if (type.includes('maintenance') || type.includes('repair')) {
                    return {
                        icon: '🔧',
                        badgeBg: '#f0fdf4',
                        badgeColor: '#16a34a',
                        label: 'Maintenance'
                    };
                }
                if (type.includes('rent') || type.includes('payment') || type.includes('penalty')) {
                    return {
                        icon: '💳',
                        badgeBg: '#fffbeb',
                        badgeColor: '#d97706',
                        label: 'Billing Notice'
                    };
                }
                return {
                    icon: '🔔',
                    badgeBg: '#f1f5f9',
                    badgeColor: '#475569',
                    label: 'Notification'
                };
            }

            function updateCountBadges() {
                if (cached.length > 0) {
                    countEl.textContent = cached.length;
                    countEl.classList.remove('hidden');
                    bellEl.classList.add('text-white');
                    if (headerBadge) {
                        headerBadge.textContent = cached.length + ' new';
                        headerBadge.classList.remove('hidden');
                    }
                } else {
                    countEl.classList.add('hidden');
                    bellEl.classList.remove('text-white');
                    if (headerBadge) {
                        headerBadge.classList.add('hidden');
                    }
                }
            }

            async function refresh() {
                try {
                    const res = await fetch('/api/notifications/unread');
                    cached = await res.json();
                    updateCountBadges();
                    if (!dropdownEl.classList.contains('hidden')) {
                        renderDropdown();
                    }
                } catch (e) { /* network retry on next interval */ }
            }

            function renderDropdown() {
                if (!listBody) return;
                if (!cached.length) {
                    listBody.innerHTML = `
                        <div class="py-10 px-4 flex flex-col items-center justify-center text-center text-neutral-400">
                            <div class="w-12 h-12 rounded-full bg-neutral-100 flex items-center justify-center text-xl mb-2 text-neutral-400">
                                🔔
                            </div>
                            <p class="text-xs font-semibold text-neutral-700">All caught up!</p>
                            <p class="text-[11px] text-neutral-400 mt-0.5">Nothing new right now.</p>
                        </div>
                    `;
                    return;
                }

                listBody.innerHTML = cached.map(n => {
                    const conf = getNotifConfig(n.type);
                    const timeAgo = formatTimeAgo(n.created_at);
                    return `
                        <div class="notif-item p-3.5 hover:bg-neutral-50/90 cursor-pointer transition-colors flex items-start gap-3 text-left group relative"
                             data-id="${n.id}"
                             title="Click to mark as read">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 text-sm shadow-xs mt-0.5"
                                 style="background: ${conf.badgeBg}; color: ${conf.badgeColor}; border: 1px solid ${conf.badgeColor}25;">
                                ${conf.icon}
                            </div>
                            <div class="flex-1 min-w-0 pr-2">
                                <div class="flex items-center justify-between gap-2 mb-0.5">
                                    <span class="text-[10px] font-bold uppercase tracking-wider" style="color: ${conf.badgeColor};">
                                        ${conf.label}
                                    </span>
                                    <span class="text-[10px] text-neutral-400 flex-shrink-0">${timeAgo}</span>
                                </div>
                                <div class="text-xs text-neutral-800 leading-relaxed font-normal break-words">${escapeHtml(n.message)}</div>
                            </div>
                            <button type="button"
                                    class="opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity p-1 rounded hover:bg-neutral-200/70 text-neutral-400 hover:text-emerald-700 flex-shrink-0 cursor-pointer mt-0.5"
                                    title="Mark as read">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </button>
                        </div>
                    `;
                }).join('');
            }

            function openDropdown() {
                renderDropdown();
                dropdownEl.classList.remove('hidden');
                dropdownEl.classList.add('animate-notif-dropdown');
                bellEl.setAttribute('aria-expanded', 'true');
                bellEl.classList.add('bg-white/15', 'text-white');
            }

            function closeDropdown() {
                dropdownEl.classList.add('hidden');
                dropdownEl.classList.remove('animate-notif-dropdown');
                bellEl.setAttribute('aria-expanded', 'false');
                bellEl.classList.remove('bg-white/15');
                if (cached.length === 0) {
                    bellEl.classList.remove('text-white');
                }
            }

            if (bellEl && dropdownEl) {
                bellEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (dropdownEl.classList.contains('hidden')) {
                        openDropdown();
                    } else {
                        closeDropdown();
                    }
                });

                bellEl.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        bellEl.click();
                    }
                });

                // Dismiss if clicked outside
                document.addEventListener('click', (e) => {
                    if (containerEl && !containerEl.contains(e.target) && !dropdownEl.classList.contains('hidden')) {
                        closeDropdown();
                    }
                });

                // Dismiss on Escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && !dropdownEl.classList.contains('hidden')) {
                        closeDropdown();
                        bellEl.focus();
                    }
                });

                // Handle clicks inside dropdown (item click or mark-all click)
                dropdownEl.addEventListener('click', async (e) => {
                    // Mark all read button
                    if (e.target.closest('#notif-mark-all')) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (!cached.length) return;
                        const toMark = [...cached];
                        cached = [];
                        renderDropdown();
                        updateCountBadges();

                        for (const n of toMark) {
                            try {
                                await fetch('/api/notifications/' + n.id + '/read', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'csrf_token=' + encodeURIComponent(csrfToken),
                                });
                            } catch (err) {}
                        }
                        refresh();
                        return;
                    }

                    // Individual notification item click
                    const item = e.target.closest('[data-id]');
                    if (!item) return;
                    const id = item.getAttribute('data-id');
                    if (!id) return;

                    // Optimistic UI response
                    item.style.opacity = '0.35';
                    item.style.pointerEvents = 'none';

                    try {
                        const res = await fetch('/api/notifications/' + id + '/read', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'csrf_token=' + encodeURIComponent(csrfToken),
                        });
                        const data = await res.json();
                        if (!res.ok || !data.ok) {
                            item.style.opacity = '1';
                            item.style.pointerEvents = '';
                            return;
                        }
                    } catch (err) {
                        item.style.opacity = '1';
                        item.style.pointerEvents = '';
                        return;
                    }

                    cached = cached.filter(n => String(n.id) !== String(id));
                    renderDropdown();
                    updateCountBadges();
                });
            }

            refresh();
            setInterval(refresh, 15000);
        })();

        /* ── Double-Confirmation Logout Modal ── */
        (function () {
            const btn        = document.getElementById('logout-btn');
            const modal      = document.getElementById('logout-modal');
            const card       = document.getElementById('logout-confirm-card');
            const cancelBtn  = document.getElementById('logout-modal-cancel');
            const confirmBtn = document.getElementById('logout-modal-confirm');
            const form       = document.getElementById('logout-form');

            function showLogoutModal() {
                if (!modal) return;
                modal.style.display = 'flex';
                modal.classList.add('animate-logout-backdrop');
                if (window.gsap && card) {
                    gsap.fromTo(card,
                        { opacity: 0, scale: 0.9, y: 12 },
                        { opacity: 1, scale: 1, y: 0, duration: 0.25, ease: 'back.out(1.7)' }
                    );
                } else if (card) {
                    card.classList.add('animate-logout-card');
                }
                if (cancelBtn) cancelBtn.focus();
            }

            function hideLogoutModal() {
                if (!modal) return;
                modal.style.display = 'none';
                modal.classList.remove('animate-logout-backdrop');
                if (card) card.classList.remove('animate-logout-card');
                if (btn) btn.focus();
            }

            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showLogoutModal();
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    hideLogoutModal();
                });
            }

            if (confirmBtn && form) {
                confirmBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    form.submit();
                });
            }

            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        hideLogoutModal();
                    }
                });
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.style.display !== 'none') {
                    hideLogoutModal();
                }
            });
        })();

        /* ── Mobile Menu Toggle ── */
        (function () {
            const toggleBtn = document.getElementById('mobile-menu-toggle');
            const closeBtn = document.getElementById('mobile-menu-close');
            const menu = document.getElementById('mobile-menu');
            const mobileLogoutBtn = document.getElementById('mobile-logout-btn');
            const mobileLogoutForm = document.getElementById('mobile-logout-form');

            function openMenu() {
                if (!menu) return;
                menu.classList.add('is-open');
                document.body.style.overflow = 'hidden';
                if (toggleBtn) {
                    toggleBtn.setAttribute('aria-expanded', 'true');
                }
            }

            function closeMenu() {
                if (!menu) return;
                menu.classList.remove('is-open');
                document.body.style.overflow = '';
                if (toggleBtn) {
                    toggleBtn.setAttribute('aria-expanded', 'false');
                }
            }

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (menu.classList.contains('is-open')) {
                        closeMenu();
                    } else {
                        openMenu();
                    }
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeMenu();
                });
            }

            // Close menu when clicking on nav links
            const mobileLinks = document.querySelectorAll('.mobile-nav-link');
            mobileLinks.forEach(function (link) {
                link.addEventListener('click', function () {
                    closeMenu();
                });
            });

            // Mobile logout button
            if (mobileLogoutBtn && mobileLogoutForm) {
                mobileLogoutBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    mobileLogoutForm.submit();
                });
            }

            // Close menu on backdrop click
            if (menu) {
                menu.addEventListener('click', function (e) {
                    if (e.target === menu) {
                        closeMenu();
                    }
                });
            }

            // Close menu on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && menu && menu.classList.contains('is-open')) {
                    closeMenu();
                }
            });
        })();
        </script>
    <?php endif; ?>
</nav>
