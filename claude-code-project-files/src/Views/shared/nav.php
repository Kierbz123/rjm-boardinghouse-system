<?php
$role = $_SESSION['role'] ?? null;
if (!$role) {
    return;
}

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$dashboardHome = match ($role) {
    'admin'   => '/admin/dashboard',
    'staff'   => '/staff/dashboard',
    'boarder' => '/portal/dashboard',
    default   => '/',
};

// Logical grouping matching the Master Prompt specifications
$navSections = [
    'admin' => [
        'Overview' => [
            ['href' => '/admin/dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['href' => '/admin/inquiry-center', 'label' => 'Inquiry Center', 'icon' => 'inquiry'],
            ['href' => '/admin/occupancy', 'label' => 'Occupancy', 'icon' => 'occupancy'],
        ],
        'Boarding' => [
            ['href' => '/admin/boarders', 'label' => 'Boarders', 'icon' => 'boarders'],
            ['href' => '/admin/rooms', 'label' => 'Rooms & Beds', 'icon' => 'rooms'],
            ['href' => '/admin/staff', 'label' => 'Staff Accounts', 'icon' => 'boarders'],
        ],
        'Finance' => [
            ['href' => '/admin/payments', 'label' => 'Payments', 'icon' => 'payments'],
            ['href' => '/admin/expenses', 'label' => 'Expenses', 'icon' => 'expenses'],
            ['href' => '/admin/penalty-rules', 'label' => 'Penalties', 'icon' => 'penalties'],
        ],
        'Records' => [
            ['href' => '/staff/maintenance/history', 'label' => 'Maintenance History', 'icon' => 'maintenance'],
            ['href' => '/staff/incidents/history', 'label' => 'Incident History', 'icon' => 'incidents'],
        ],
    ],
    'staff' => [
        'Overview' => [
            ['href' => '/staff/dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ['href' => '/admin/inquiry-center', 'label' => 'Inquiry Center', 'icon' => 'inquiry'],
        ],
        'Active Tasks' => [
            ['href' => '/staff/maintenance', 'label' => 'Maintenance Queue', 'icon' => 'maintenance'],
            ['href' => '/staff/incidents', 'label' => 'Incidents', 'icon' => 'incidents'],
        ],
        'Records' => [
            ['href' => '/staff/maintenance/history', 'label' => 'Maintenance History', 'icon' => 'maintenance'],
            ['href' => '/staff/incidents/history', 'label' => 'Incident History', 'icon' => 'incidents'],
        ],
    ],
    'boarder' => [
        'Overview' => [
            ['href' => '/portal/dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ],
        'Resident Services' => [
            ['href' => '/portal/maintenance/new', 'label' => 'Report a Repair', 'icon' => 'maintenance'],
            ['href' => '/portal/payments/new', 'label' => 'Pay Rent', 'icon' => 'payments'],
        ],
        'Community' => [
            ['href' => '/staff/incidents', 'label' => 'Incidents', 'icon' => 'incidents'],
        ],
    ],
];

// Helper to check if a section contains the currently active route
function sectionHasActiveRoute(array $items, string $currentPath): bool {
    foreach ($items as $item) {
        if ($item['href'] === $currentPath) return true;
    }
    return false;
}

// Inline SVG Icon Renderer (stroke-width 2, 18-20px)
function renderNavSvg(string $icon): string {
    return match ($icon) {
        'dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',
        'inquiry' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        'occupancy' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>',
        'boarders' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'rooms' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>',
        'payments' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>',
        'expenses' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 6v12"/></svg>',
        'penalties' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>',
        'maintenance' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
        'incidents' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>',
        default => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>',
    };
}
?>

<script>
    // Immediate early execution to prevent layout shift on reload if rail mode was active
    if (localStorage.getItem('rjm_sidebar_rail') === 'true') {
        document.body.classList.add('sidebar-rail');
    }
</script>

<style>
    /* ══════════════════════════════════════════════════════════════
       SELF-CONTAINED APP SHELL & SIDEBAR CSS
       (Explicitly declared so it NEVER depends on missing Tailwind v4 classes)
       ══════════════════════════════════════════════════════════════ */

    /* Fixed Left Sidebar Anchor */
    #app-sidebar {
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
        left: 0 !important;
        height: 100vh !important;
        max-height: 100vh !important;
        z-index: 50;
        display: flex !important;
        flex-direction: column !important;
        background-color: #0f172a; /* neutral-900 */
        border-right: 1px solid #1e293b; /* neutral-800 */
        color: #ffffff;
        box-sizing: border-box;
        overflow: hidden;
    }

    #app-main-content {
        min-width: 0;
        box-sizing: border-box;
    }

    /* ── Desktop Sidebar & Rail Transition Rules (≥1024px) ── */
    @media (min-width: 1024px) {
        #mobile-top-bar {
            display: none !important;
        }
        #mobile-menu-close {
            display: none !important;
        }
        #sidebar-rail-toggle {
            display: inline-flex !important;
        }
        #sidebar-backdrop {
            display: none !important;
        }

        #app-sidebar {
            width: 15rem; /* 240px */
            transform: none !important;
            transition: width 200ms cubic-bezier(0.16, 1, 0.3, 1);
        }
        #app-main-content {
            margin-left: 15rem !important;
            min-height: 100vh;
            transition: margin-left 200ms cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Collapsed Icon-Only Rail Mode (~64px) */
        body.sidebar-rail #app-sidebar {
            width: 4rem !important; /* 64px */
        }
        body.sidebar-rail #app-main-content {
            margin-left: 4rem !important;
        }

        /* Hide text labels, section titles, chevrons, and user info in rail mode */
        body.sidebar-rail .rail-hide {
            display: none !important;
        }
        body.sidebar-rail .sidebar-nav-item {
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        body.sidebar-rail .sidebar-header-wrap {
            justify-content: center !important;
            padding-left: 0.25rem !important;
            padding-right: 0.25rem !important;
        }
        body.sidebar-rail .rail-toggle-icon {
            transform: rotate(180deg);
        }
        body.sidebar-rail #notif-bell {
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        body.sidebar-rail .sidebar-footer-wrap {
            flex-direction: column !important;
            gap: 0.5rem !important;
            align-items: center !important;
            padding: 0.5rem 0.25rem !important;
        }
        body.sidebar-rail .desktop-profile-pill {
            justify-content: center !important;
            padding: 0.5rem !important;
            width: 100% !important;
        }
        body.sidebar-rail #logout-wrap {
            width: 100% !important;
            justify-content: center !important;
        }
        body.sidebar-rail #logout-btn {
            width: 100% !important;
            display: flex !important;
            justify-content: center !important;
        }

        /* Show hover tooltip in rail mode */
        body.sidebar-rail .sidebar-nav-item:hover .nav-tooltip,
        body.sidebar-rail #notif-bell:hover .nav-tooltip {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateX(0) !important;
        }
    }

    /* ── Mobile Layout Rules (<1024px) ── */
    @media (max-width: 1023px) {
        #mobile-top-bar {
            display: flex !important;
            position: sticky;
            top: 0;
            z-index: 30;
            background-color: #0f172a;
            border-bottom: 1px solid #1e293b;
            padding: 0.625rem 1rem;
            align-items: center;
            justify-content: space-between;
            color: #ffffff;
            height: 3.5rem;
            box-sizing: border-box;
        }
        #sidebar-rail-toggle {
            display: none !important;
        }
        #mobile-menu-close {
            display: inline-flex !important;
        }
        #sidebar-backdrop {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 45;
            display: none;
            opacity: 0;
            transition: opacity 200ms ease;
        }
        #sidebar-backdrop.is-open {
            display: block !important;
            opacity: 1 !important;
        }

        #app-sidebar {
            width: 16rem !important;
            max-width: 85vw !important;
            transform: translateX(-100%);
            transition: transform 240ms cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 50;
        }
        #app-sidebar.is-open {
            transform: translateX(0) !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
        }
        #app-main-content {
            margin-left: 0 !important;
            width: 100% !important;
        }
    }

    /* Custom thin scrollbar for nav body */
    .sidebar-scrollbar::-webkit-scrollbar { width: 4px; }
    .sidebar-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.12); border-radius: 9999px; }
    .sidebar-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.25); }
    .sidebar-scrollbar { scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, 0.12) transparent; }

    /* Nav Item Base Styling */
    .sidebar-nav-item {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: #94a3b8; /* neutral-400 */
        text-decoration: none;
        transition: all 140ms cubic-bezier(0.16, 1, 0.3, 1);
        border-left: 3px solid transparent;
        overflow: hidden;
    }
    .sidebar-nav-item:hover {
        color: #ffffff;
        background-color: rgba(255, 255, 255, 0.06);
    }

    /* Active Route: Solid distinct block/pill with bg-white/10 and emerald-400 left border */
    .sidebar-nav-item.active {
        color: #ffffff !important;
        font-weight: 600;
        background-color: rgba(255, 255, 255, 0.10) !important;
        border-left-color: #34d399 !important; /* emerald-400 */
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
    }
    .sidebar-nav-item.active .nav-icon {
        color: #34d399 !important;
    }

    /* Rotating chevron for collapsible groups */
    .section-chevron {
        transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1);
    }
    .section-group.collapsed .section-chevron {
        transform: rotate(-90deg);
    }
    .section-group.collapsed .section-items {
        display: none !important;
    }

    /* Rail mode hover tooltip */
    .nav-tooltip {
        opacity: 0;
        visibility: hidden;
        transform: translateX(-4px);
        transition: all 150ms ease-out;
        pointer-events: none;
        position: absolute;
        left: 100%;
        margin-left: 0.75rem;
        padding: 0.25rem 0.5rem;
        background-color: #1e293b;
        color: #ffffff;
        font-size: 0.75rem;
        font-weight: 500;
        border-radius: 0.375rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        border: 1px solid #334155;
        white-space: nowrap;
        z-index: 60;
    }

    /* Notification Count Badge */
    #notif-count {
        position: absolute !important;
        top: -5px !important;
        right: -7px !important;
        min-width: 18px !important;
        height: 18px !important;
        padding: 0 4px !important;
        border-radius: 9999px !important;
        background: #ef4444 !important;
        color: #ffffff !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        line-height: 18px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border: 2px solid #0f172a !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
        pointer-events: none;
        z-index: 10;
    }
    #notif-count.hidden {
        display: none !important;
    }

    /* Double-confirmation modal animations */
    @keyframes logoutModalFadeIn { 0% { opacity: 0; } 100% { opacity: 1; } }
    @keyframes logoutCardPop { 0% { opacity: 0; transform: scale(0.9) translateY(12px); } 100% { opacity: 1; transform: scale(1) translateY(0); } }
    .animate-logout-backdrop { animation: logoutModalFadeIn 180ms ease-out forwards; }
    .animate-logout-card { animation: logoutCardPop 220ms cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    #logout-modal-cancel:hover { background-color: #f1f5f9 !important; color: #0f172a !important; }
    #logout-modal-confirm:hover { filter: brightness(1.08); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25); }
    #logout-modal-confirm:active { transform: scale(0.97); }
</style>

<!-- ══════════════════════════════════════════════════════════════
     SLIM MOBILE TOP BAR (<1024px)
     ══════════════════════════════════════════════════════════════ -->
<header id="mobile-top-bar" class="bg-neutral-900 border-b border-neutral-800 text-white">
    <div class="flex items-center gap-3">
        <!-- Hamburger Button (mobile-menu-toggle) -->
        <button id="mobile-menu-toggle"
                type="button"
                class="mobile-hamburger p-1.5 rounded-md hover:bg-white/10 active:scale-95 transition-all text-neutral-300 hover:text-white cursor-pointer"
                aria-label="Open menu"
                aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <!-- Brand Link -->
        <a href="<?= htmlspecialchars($dashboardHome) ?>" class="font-semibold tracking-tight inline-flex items-center gap-2 text-sm hover:opacity-90 transition-opacity">
            <span style="color: #34d399; font-size: 0.75rem;">▲</span>
            <span class="font-bold text-white tracking-tight">RJM <span class="text-neutral-400 font-normal">Boardinghouse</span></span>
        </a>
    </div>

    <!-- Notification Bell Link Shortcut (mirrors notifications) -->
    <a href="/notifications"
       class="relative p-2 rounded-lg text-neutral-400 hover:text-white hover:bg-white/10 transition-colors"
       aria-label="View Notifications">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
        </svg>
    </a>
</header>

<!-- Mobile Overlay Backdrop -->
<div id="sidebar-backdrop" aria-hidden="true"></div>

<!-- ══════════════════════════════════════════════════════════════
     LEFT SIDEBAR NAVIGATION (Desktop Fixed ~240px / Rail ~64px + Mobile Drawer)
     ══════════════════════════════════════════════════════════════ -->
<aside id="app-sidebar" aria-label="Admin Sidebar Navigation">

    <!-- 1. Header: Brand Mark + Desktop Collapse/Expand Rail Toggle + Mobile Close -->
    <div style="height: 3.5rem; min-height: 3.5rem;" class="px-3.5 flex items-center justify-between border-b border-neutral-800 flex-shrink-0 sidebar-header-wrap">
        <!-- Brand Mark (▲ + "RJM Boardinghouse") -->
        <a href="<?= htmlspecialchars($dashboardHome) ?>"
           class="font-semibold tracking-tight inline-flex items-center gap-2 text-sm hover:opacity-90 transition-opacity flex-shrink-0 overflow-hidden"
           style="white-space: nowrap; text-decoration: none;">
            <span style="color: #34d399; font-size: 0.75rem;" class="flex-shrink-0">▲</span>
            <span class="font-bold text-white tracking-tight rail-hide">
                RJM <span class="text-neutral-400 font-normal">Boardinghouse</span>
            </span>
        </a>

        <!-- Controls: Desktop Rail Toggle & Mobile Close -->
        <div class="flex items-center gap-1">
            <!-- Collapse/Expand Rail Toggle (Desktop only) -->
            <button id="sidebar-rail-toggle"
                    type="button"
                    class="p-1.5 rounded-md text-neutral-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                    title="Toggle Compact Sidebar Rail"
                    aria-label="Toggle Sidebar Rail">
                <svg class="rail-toggle-icon transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                    <line x1="9" x2="9" y1="3" y2="21"/>
                    <path d="m14 15-3-3 3-3"/>
                </svg>
            </button>

            <!-- Close Drawer Button (Mobile only) -->
            <button id="mobile-menu-close"
                    type="button"
                    class="p-1.5 rounded-md text-neutral-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                    aria-label="Close menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>

    <!-- 2. Nav Body (Scrollable): Grouped into logical sections with labels + dividers -->
    <nav class="sidebar-scrollbar flex-1 overflow-y-auto px-4 py-5 space-y-5" aria-label="Main Navigation">
        <?php foreach ($navSections[$role] ?? [] as $sectionName => $items): ?>
            <?php $hasActive = sectionHasActiveRoute($items, $currentPath); ?>
            <div class="section-group <?= $hasActive ? 'expanded' : '' ?>" data-section="<?= htmlspecialchars($sectionName) ?>">
                <!-- Section Header with small uppercase label + rotating chevron -->
                <button type="button"
                        class="w-full flex items-center justify-between px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-neutral-400 hover:text-neutral-300 transition-colors cursor-pointer rail-hide section-toggle-btn"
                        style="background: transparent; border: none;"
                        aria-expanded="true">
                    <span><?= htmlspecialchars($sectionName) ?></span>
                    <svg class="section-chevron text-neutral-400" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>

                <!-- Section Nav Items -->
                <div class="section-items space-y-1.5 mt-1.5">
                    <?php foreach ($items as $item): ?>
                        <?php $isActive = $currentPath === $item['href']; ?>
                        <a href="<?= htmlspecialchars($item['href']) ?>"
                           class="sidebar-nav-item mobile-nav-link <?= $isActive ? 'active' : '' ?>"
                           <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <span class="nav-icon flex-shrink-0 text-neutral-400 transition-colors">
                                <?= renderNavSvg($item['icon']) ?>
                            </span>
                            <span class="rail-hide truncate"><?= htmlspecialchars($item['label']) ?></span>

                            <!-- Floating Tooltip for Desktop Rail Mode -->
                            <span class="nav-tooltip">
                                <?= htmlspecialchars($item['label']) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>


            </div>
        <?php endforeach; ?>
    </nav>

    <!-- 3. Upper Footer / Utility Cluster: Notification Bell -->
    <div class="px-4 py-2 border-t border-neutral-800/80 flex items-center justify-between flex-shrink-0">
        <!-- Notification Bell (Exact ID and structure for Playwright tests & automated polling) -->
        <div class="relative flex items-center w-full" id="notif-wrapper">
            <a id="notif-bell"
               href="/notifications"
               class="w-full relative cursor-pointer rounded-md p-2 text-neutral-400 hover:text-white hover:bg-white/10 active:scale-95 transition-all flex items-center gap-2.5 focus:outline-none <?= $currentPath === '/notifications' ? 'bg-white/10 text-white font-semibold' : '' ?>"
               aria-label="View Notifications"
               title="Notifications &amp; Activity Log"
               style="text-decoration: none;">
                <div class="relative flex-shrink-0" style="position: relative; display: inline-flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-150">
                        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
                        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
                    </svg>
                    <!-- Exact Floating Badge id="notif-count" -->
                    <span id="notif-count" class="hidden">0</span>
                </div>
                <span class="rail-hide text-xs font-medium truncate">Notifications</span>
                <!-- Floating Tooltip for Desktop Rail Mode -->
                <span class="nav-tooltip">Notifications</span>
            </a>
            <!-- Exact container id="notif-dropdown" for test compatibility -->
            <div id="notif-dropdown" class="hidden" aria-hidden="true"></div>
        </div>
    </div>

    <!-- 4. Pinned Sidebar Footer: Persistent User Profile Card & Logout -->
    <div class="px-4 py-2.5 border-t border-neutral-800 bg-neutral-900/95 flex-shrink-0" style="overflow: hidden;">
        <div class="flex items-center gap-1.5 sidebar-footer-wrap" style="min-width: 0;">
            <!-- User Profile Pill (Reused exact styling: bg-white/5 border border-neutral-700/70) -->
            <a href="/profile"
               class="desktop-profile-pill min-w-0 inline-flex items-center justify-center gap-2 px-2 py-1.5 rounded-md bg-white/5 border border-neutral-700/70 text-xs text-neutral-300 font-medium hover:text-white hover:bg-white/10 hover:border-neutral-500 transition-all duration-150 cursor-pointer overflow-hidden"
               style="flex: 1 1 0%; text-decoration: none;"
               title="View Profile &amp; Account Settings">
                <span style="display:inline-block; width:0.4rem; height:0.4rem; border-radius:50%; background:#34d399;" class="flex-shrink-0"></span>
                <div class="flex flex-col min-w-0 rail-hide">
                    <span class="truncate font-semibold text-white leading-tight"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin User') ?></span>
                    <span class="text-[10px] text-neutral-400 capitalize font-normal leading-tight">· <?= htmlspecialchars($role) ?></span>
                </div>
            </a>

            <!-- Logout Trigger Button & Form -->
            <div class="relative flex items-center flex-shrink-0 desktop-profile-pill" id="logout-wrap">
                <form method="post" action="/logout" id="logout-form" class="hidden">
                    <?= \App\Support\Csrf::field() ?>
                </form>

                <button id="logout-btn"
                        type="button"
                        class="btn btn-secondary bg-transparent border-neutral-700 text-neutral-300 hover:text-white hover:bg-white/10 !p-1.5 !text-xs transition-all duration-150 cursor-pointer rounded-md"
                        title="Logout of account"
                        aria-label="Logout">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</aside>

<!-- Compatibility wrappers and aliases for tests and scripts -->
<div id="mobile-menu" class="hidden" aria-hidden="true"></div>
<div class="mobile-user-info hidden" aria-hidden="true">
    <span style="display:inline-block; width:0.35rem; height:0.35rem; border-radius:50%; background:#34d399;"></span>
    <span><?= htmlspecialchars(substr($_SESSION['name'] ?? 'Admin User', 0, 12)) ?></span>
</div>
<form method="post" action="/logout" id="mobile-logout-form" class="hidden">
    <?= \App\Support\Csrf::field() ?>
</form>
<button id="mobile-logout-btn" type="button" class="hidden" aria-hidden="true"></button>

<!-- ══════════════════════════════════════════════════════════════
     DOUBLE CONFIRMATION LOGOUT MODAL (Exact IDs & Behavior preserved)
     ══════════════════════════════════════════════════════════════ -->
<div id="logout-modal"
     style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:999999; background:rgba(15,23,42,0.55); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;"
     role="dialog"
     aria-modal="true"
     aria-labelledby="logout-modal-heading">
    <div id="logout-confirm-card"
         style="background: rgb(255, 255, 255); border-radius: 1rem; padding: 1.5rem 1.75rem; max-width: 22rem; width: 100%; margin: 0px auto; text-align: center; box-shadow: rgba(0, 0, 0, 0.25) 0px 25px 50px -12px;">
        <!-- Big Round Icon (!) -->
        <div id="logout-modal-icon-wrap"
             style="width: 3.5rem; height: 3.5rem; border-radius: 50%; border: 2px solid rgb(239, 68, 68); color: rgb(220, 38, 38); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0px auto 0.75rem; user-select: none;">
            !
        </div>

        <!-- Title -->
        <h3 id="logout-modal-heading" style="font-size: 1.1rem; font-weight: 700; color: #0f172a; letter-spacing: -0.01em; margin: 0 0 0.375rem;">Are you sure?</h3>

        <!-- Description -->
        <p id="logout-modal-subtext" style="font-size: 0.8rem; color: #64748b; line-height: 1.6; margin: 0 0 1.5rem; padding: 0 0.5rem;">Are you sure you want to log out of your account?</p>

        <!-- Actions -->
        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; padding-top: 0.25rem;">
            <button type="button"
                    id="logout-modal-cancel"
                    style="padding: 0.5rem 1rem; border-radius: 0.75rem; font-size: 0.75rem; font-weight: 600; color: #64748b; background: transparent; border: none; cursor: pointer; transition: all 150ms;">
                Cancel
            </button>
            <button type="button"
                    id="logout-modal-confirm"
                    style="color: rgb(255, 255, 255); font-weight: 600; border-radius: 0.75rem; padding: 0.5rem 1.25rem; font-size: 0.75rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem; transition: 150ms; background: linear-gradient(135deg, rgb(239, 68, 68), rgb(220, 38, 38));">
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

    function getNotifConfig(type, message, actionUrl) {
        type = (type || '').toLowerCase();
        const messageLower = (message || '').toLowerCase();
        const isResolved = messageLower.includes('resolved') || (actionUrl && actionUrl.includes('history'));
        const isAcknowledged = messageLower.includes('acknowledged');

        if (type.includes('sos')) {
            if (isResolved) return { icon: '🛡️', badgeBg: '#ecfdf5', badgeColor: '#059669', label: 'SOS Resolved', actionText: 'View History' };
            if (isAcknowledged) return { icon: '👁️', badgeBg: '#fffbeb', badgeColor: '#d97706', label: 'SOS Acknowledged', actionText: 'View Status' };
            return { icon: '🚨', badgeBg: '#fef2f2', badgeColor: '#dc2626', label: 'Emergency SOS', actionText: 'View & Take Action' };
        }
        if (type.includes('maintenance') || type.includes('repair')) return { icon: '🔧', badgeBg: '#f0fdf4', badgeColor: '#16a34a', label: 'Maintenance', actionText: 'View Details' };
        if (type.includes('rent') || type.includes('payment') || type.includes('penalty')) return { icon: '💳', badgeBg: '#fffbeb', badgeColor: '#d97706', label: 'Billing Notice', actionText: 'View Details' };
        if (type.includes('inquiry')) return { icon: '📋', badgeBg: '#eff6ff', badgeColor: '#2563eb', label: 'Inquiry', actionText: 'View Details' };
        return { icon: '🔔', badgeBg: '#f1f5f9', badgeColor: '#475569', label: 'Notification', actionText: 'View Details' };
    }

    function updateCountBadges() {
        if (!countEl) return;
        if (cached.length > 0) {
            countEl.textContent = cached.length;
            countEl.classList.remove('hidden');
            if (bellEl) bellEl.classList.add('text-white');
            if (headerBadge) {
                headerBadge.textContent = cached.length + ' new';
                headerBadge.classList.remove('hidden');
            }
        } else {
            countEl.classList.add('hidden');
            if (bellEl) bellEl.classList.remove('text-white');
            if (headerBadge) headerBadge.classList.add('hidden');
        }
    }

    async function refresh() {
        try {
            const res = await fetch('/api/notifications/unread');
            cached = await res.json();
            updateCountBadges();
            renderDropdown();
            // The single notifications poll for the page; app.js listens for the new-item banner.
            document.dispatchEvent(new CustomEvent('rjm:notifications', { detail: cached }));
        } catch (e) { /* network retry on next interval */ }
    }

    function renderDropdown() {
        if (!dropdownEl) return;
        if (!cached.length) {
            dropdownEl.innerHTML = `
                <div class="py-10 px-4 flex flex-col items-center justify-center text-center text-neutral-400">
                    <div class="w-12 h-12 rounded-full bg-neutral-100 flex items-center justify-center text-xl mb-2 text-neutral-400">🔔</div>
                    <p class="text-xs font-semibold text-neutral-700">All caught up!</p>
                    <p class="text-[11px] text-neutral-400 mt-0.5">Nothing new right now.</p>
                </div>
            `;
            return;
        }

        dropdownEl.innerHTML = cached.map(n => {
            const conf = getNotifConfig(n.type, n.message, n.action_url);
            const timeAgo = formatTimeAgo(n.created_at);
            const actionButton = n.action_url ? `
                <a href="${escapeHtml(n.action_url)}"
                   class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-semibold bg-neutral-900 text-white hover:bg-neutral-800 transition-colors shadow-xs mt-1">
                    <span>${escapeHtml(conf.actionText || 'View Details')}</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            ` : '';
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
                            <span class="text-[10px] font-bold uppercase tracking-wider" style="color: ${conf.badgeColor};">${conf.label}</span>
                            <span class="text-[10px] text-neutral-400 flex-shrink-0">${timeAgo}</span>
                        </div>
                        <div class="text-xs text-neutral-800 leading-relaxed font-normal break-words">${escapeHtml(n.message)}</div>
                        ${actionButton}
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

    if (bellEl) {
        bellEl.setAttribute('aria-label', 'View Notifications');
    }

    if (dropdownEl) {
        dropdownEl.addEventListener('click', async (e) => {
            const item = e.target.closest('[data-id]');
            if (!item) return;
            const id = item.getAttribute('data-id');
            if (!id) return;

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

/* ── Desktop Rail Mode Toggle & Section Expand/Collapse ── */
(function () {
    const railToggle = document.getElementById('sidebar-rail-toggle');

    if (railToggle) {
        railToggle.addEventListener('click', function () {
            const isRail = document.body.classList.toggle('sidebar-rail');
            localStorage.setItem('rjm_sidebar_rail', isRail ? 'true' : 'false');
        });
    }

    // Expandable/Collapsible Navigation Groups
    const groupToggleBtns = document.querySelectorAll('.section-toggle-btn');
    groupToggleBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const group = this.closest('.section-group');
            if (!group) return;
            group.classList.toggle('collapsed');
            const isCollapsed = group.classList.contains('collapsed');
            this.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        });
    });
})();

/* ── Mobile Sidebar Drawer Toggle ── */
(function () {
    const toggleBtn   = document.getElementById('mobile-menu-toggle');
    const closeBtn    = document.getElementById('mobile-menu-close');
    const sidebar     = document.getElementById('app-sidebar');
    const backdrop    = document.getElementById('sidebar-backdrop');
    const mobileLinks = document.querySelectorAll('.mobile-nav-link');

    function openSidebar() {
        if (!sidebar || !backdrop) return;
        sidebar.classList.add('is-open');
        backdrop.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        if (!sidebar || !backdrop) return;
        sidebar.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        document.body.style.overflow = '';
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('is-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeSidebar();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    mobileLinks.forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    });
})();

/* ── Double-Confirmation Logout Modal ── */
(function () {
    const btn        = document.getElementById('logout-btn');
    const mobileBtn  = document.getElementById('mobile-logout-btn');
    const modal      = document.getElementById('logout-modal');
    const card       = document.getElementById('logout-confirm-card');
    const cancelBtn  = document.getElementById('logout-modal-cancel');
    const confirmBtn = document.getElementById('logout-modal-confirm');
    const form       = document.getElementById('logout-form');
    const mobileForm = document.getElementById('mobile-logout-form');

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

    if (mobileBtn) {
        mobileBtn.addEventListener('click', function (e) {
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

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (form) {
                form.submit();
            } else if (mobileForm) {
                mobileForm.submit();
            }
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
</script>
