<?php
$pageTitle = 'Rooms & Beds';
ob_start();
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

// Compute overview statistics
$totalRooms    = count($rooms);
$totalBeds     = count($beds);
$occupiedBeds  = count(array_filter($beds, fn($b) => ($b['status'] ?? '') === 'occupied'));
$vacantBeds    = count(array_filter($beds, fn($b) => ($b['status'] ?? '') === 'vacant'));
$occupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100) : 0;

$vacantBedsList = array_values(array_filter($beds, fn($b) => ($b['status'] ?? '') === 'vacant'));
usort($vacantBedsList, function ($a, $b) {
    $roomCmp = strnatcasecmp((string) $a['room_number'], (string) $b['room_number']);
    if ($roomCmp !== 0) {
        return -$roomCmp;
    }
    return strnatcasecmp((string) $a['label'], (string) $b['label']);
});

// Group beds by room for directory & occupancy statistics
$bedsByRoom = [];
foreach ($beds as $b) {
    $roomId = (int) $b['room_id'];
    if (!isset($bedsByRoom[$roomId])) {
        $bedsByRoom[$roomId] = [
            'room_number' => $b['room_number'],
            'room_id'     => $roomId,
            'beds'        => []
        ];
    }
    $bedsByRoom[$roomId]['beds'][] = $b;
}

$assignableBoarders = array_filter($boarders, fn($b) => ($b['status'] ?? '') !== 'moved_out');
?>

<style>
/* ==========================================================================
   ROOMS & BEDS ENHANCED MODERN LAYOUT & NAVIGATION
   Refined to make navigation easier, organized, and clean.
   ========================================================================== */

.rooms-page-wrapper {
    max-width: 82rem;
    margin-left: auto;
    margin-right: auto;
    padding: 1.5rem 1rem 3.5rem;
}
@media (min-width: 640px) {
    .rooms-page-wrapper {
        padding: 1.75rem 1.5rem 4rem;
    }
}
@media (min-width: 1024px) {
    .rooms-page-wrapper {
        padding: 2rem 2rem 4.5rem;
    }
}

/* Headings safety override against layout.php white-space: nowrap */
.rooms-page-wrapper h1,
.rooms-page-wrapper h2,
.rooms-page-wrapper h3,
.rooms-page-wrapper h4,
.rooms-page-wrapper h5,
.rooms-page-wrapper h6 {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
}

/* Page Banner */
.rooms-banner {
    background: linear-gradient(135deg, #0a0a0a 0%, #1f1f1e 100%);
    border-radius: 0.875rem;
    padding: 1.35rem 1.75rem;
    color: #ffffff;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    box-shadow: 0 4px 16px -2px rgba(10, 10, 10, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.08);
    margin-bottom: 1.5rem;
}
@media (min-width: 768px) {
    .rooms-banner {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.rooms-banner h1 {
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: #ffffff !important;
    line-height: 1.25;
    margin: 0;
}
.rooms-banner p {
    font-size: 0.8125rem;
    color: #9d9b97;
    margin-top: 0.25rem;
}

/* Quick Action Shortcuts inside Banner */
.banner-quick-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}
.banner-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.85rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 160ms ease;
    cursor: pointer;
    border: 1px solid transparent;
}
.banner-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #f1f0ee;
    border-color: rgba(255, 255, 255, 0.15);
}
.banner-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
    transform: translateY(-1px);
}
.banner-btn-primary {
    background: #b15f2c;
    color: #ffffff;
    border-color: #bd6b36;
}
.banner-btn-primary:hover {
    background: #cf8047;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(177, 95, 44, 0.35);
}

/* Sticky Sub-Navigation Bar */
.rooms-sticky-nav {
    position: sticky;
    top: 1rem;
    z-index: 35;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid #e6e5e2;
    border-radius: 0.75rem;
    padding: 0.5rem 0.75rem;
    box-shadow: 0 4px 18px -4px rgba(10, 10, 10, 0.08);
    margin-bottom: 1.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    transition: box-shadow 180ms ease;
}
@media (min-width: 1024px) {
    .rooms-sticky-nav {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.rooms-nav-list {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
    margin: 0;
    scrollbar-width: none;
}
.rooms-nav-list::-webkit-scrollbar {
    display: none;
}
.rooms-nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.8rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #555452;
    text-decoration: none;
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: all 150ms ease;
}
.rooms-nav-tab:hover {
    color: #0a0a0a;
    background: #f1f0ee;
}
.rooms-nav-tab.active {
    background: #0a0a0a;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(10, 10, 10, 0.2);
}
.rooms-nav-tab.active .rooms-nav-badge {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}
.rooms-nav-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    background: #e6e5e2;
    color: #555452;
    line-height: 1.2;
    transition: all 150ms ease;
}

/* Nav Toolbar Controls */
.nav-toolbar-controls {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-shrink: 0;
}
.nav-search-wrap {
    position: relative;
    width: 100%;
    max-width: 14rem;
}
.nav-search-input {
    width: 100%;
    padding: 0.38rem 1.75rem 0.38rem 1.85rem;
    font-size: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid #d4d2ce;
    background: #f8f7f5;
    color: #0a0a0a;
    box-sizing: border-box;
    transition: all 140ms ease;
}
.nav-search-input:focus {
    background: #ffffff;
    border-color: #b15f2c;
    box-shadow: 0 0 0 3px rgba(177, 95, 44, 0.15);
    outline: none;
}
.nav-search-icon {
    position: absolute;
    left: 0.6rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9d9b97;
    pointer-events: none;
}
.nav-search-clear {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #9d9b97;
    font-size: 0.85rem;
    cursor: pointer;
    padding: 0;
    display: none;
}
.nav-search-clear.visible {
    display: block;
}

/* View Mode Switcher (Scroll vs Focus Tab) */
.view-mode-toggle {
    display: inline-flex;
    align-items: center;
    background: #f1f0ee;
    padding: 0.2rem;
    border-radius: 0.5rem;
    border: 1px solid #e6e5e2;
    gap: 0.15rem;
}
.view-mode-btn {
    padding: 0.25rem 0.6rem;
    font-size: 0.7rem;
    font-weight: 600;
    color: #6b6b6b;
    border-radius: 0.35rem;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 130ms ease;
    white-space: nowrap;
}
.view-mode-btn:hover {
    color: #0a0a0a;
}
.view-mode-btn.active {
    background: #ffffff;
    color: #0a0a0a;
    box-shadow: 0 1px 3px rgba(10, 10, 10, 0.1);
}

/* Sections Styling */
.rooms-section {
    scroll-margin-top: 5.5rem;
    transition: opacity 180ms ease;
}
.rooms-section.focus-hidden {
    display: none !important;
}

/* Section Header Bar */
.section-headline-bar {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    margin-bottom: 1.125rem;
    padding-bottom: 0.65rem;
    border-bottom: 1.5px solid #e6e5e2;
}
@media (min-width: 640px) {
    .section-headline-bar {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.section-headline-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #0a0a0a;
    letter-spacing: -0.01em;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.section-headline-badge {
    font-size: 0.75rem;
    font-weight: 600;
    color: #555452;
    background-color: #f1f0ee;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    align-self: flex-start;
}
@media (min-width: 640px) {
    .section-headline-badge {
        align-self: center;
    }
}

/* Action Studio Tabs & Panels */
.action-studio-card {
    background: #ffffff;
    border: 1px solid #e6e5e2;
    border-radius: 0.875rem;
    box-shadow: 0 1px 4px rgba(10, 10, 10, 0.04);
    overflow: hidden;
}
.action-studio-header {
    background: #fbfbf9;
    border-bottom: 1px solid #e6e5e2;
    padding: 0.85rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
@media (min-width: 640px) {
    .action-studio-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.action-tab-group {
    display: inline-flex;
    align-items: center;
    background: #f1f0ee;
    padding: 0.25rem;
    border-radius: 0.6rem;
    gap: 0.25rem;
    overflow-x: auto;
    max-width: 100%;
}
.action-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.85rem;
    border-radius: 0.45rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #555452;
    background: transparent;
    border: none;
    cursor: pointer;
    white-space: nowrap;
    transition: all 140ms ease;
}
.action-tab-btn:hover {
    color: #0a0a0a;
}
.action-tab-btn.active {
    background: #ffffff;
    color: #0a0a0a;
    box-shadow: 0 1px 4px rgba(10, 10, 10, 0.12);
}
.action-layout-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: #6b6b6b;
}

/* Action Grid vs Tabbed Form View */
.action-panels-container.mode-tabbed .action-panel-item {
    display: none;
}
.action-panels-container.mode-tabbed .action-panel-item.active {
    display: block;
}
.action-panels-container.mode-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1.25rem;
    padding: 1.25rem;
}
@media (min-width: 768px) and (max-width: 1023px) {
    .action-panels-container.mode-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 1024px) {
    .action-panels-container.mode-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
.action-panels-container.mode-grid .action-panel-item {
    display: flex !important;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #e6e5e2;
    border-radius: 0.75rem;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(10, 10, 10, 0.04);
}

/* Individual Action Panel Styling */
.action-panel-item {
    padding: 1.5rem;
}
.action-panel-title-bar {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding-bottom: 0.85rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid #f1f0ee;
}
.action-panel-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    background: #fbf4ef;
    color: #b15f2c;
}
.action-panel-icon.icon-emerald {
    background: #f0fdf4;
    color: #15803d;
}
.action-panel-icon.icon-blue {
    background: #eff6ff;
    color: #1d4ed8;
}

/* Visual Occupancy Bar for Rooms */
.visual-occupancy-bar {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.2rem;
    background: #f1f0ee;
    border-radius: 0.4rem;
    max-width: 7.5rem;
}
.occupancy-segment {
    flex: 1;
    height: 0.45rem;
    border-radius: 0.2rem;
    background: #d4d2ce;
    transition: background-color 150ms ease;
}
.occupancy-segment.segment-occupied {
    background: #b15f2c;
}
.occupancy-segment.segment-vacant {
    background: #86efac;
}
.occupancy-segment.segment-empty-slot {
    background: #e6e5e2;
    border: 1px dashed #b5b3ad;
}

/* Beds Drawer Styling */
.beds-drawer-row {
    background-color: #fbfbf9;
}
.beds-drawer-box {
    background: #ffffff;
    border: 1px solid #e6e5e2;
    border-radius: 0.75rem;
    padding: 1.125rem 1.25rem;
    box-shadow: 0 2px 8px -2px rgba(10, 10, 10, 0.05);
}

/* Chevron Rotation on Manage Beds */
.beds-toggle-btn svg.chevron-arrow {
    transition: transform 200ms ease;
}
.beds-toggle-btn[aria-expanded="true"] svg.chevron-arrow {
    transform: rotate(180deg);
}

/* Floating Back to Top Button */
.floating-top-btn {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    z-index: 50;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.6rem 0.95rem;
    border-radius: 9999px;
    background: #0a0a0a;
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    box-shadow: 0 6px 20px -2px rgba(10, 10, 10, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.2);
    cursor: pointer;
    opacity: 0;
    transform: translateY(12px);
    pointer-events: none;
    transition: opacity 200ms ease, transform 200ms ease, background 150ms ease;
}
.floating-top-btn.visible {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}
.floating-top-btn:hover {
    background: #262626;
    transform: translateY(-2px);
}

/* Center the duplicate room alert dialog precisely in the viewport */
dialog#room-exists-dialog {
    position: fixed !important;
    top: 50% !important;
    left: 50% !important;
    bottom: auto !important;
    right: auto !important;
    transform: translate(-50%, -50%) !important;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    background: transparent !important;
    width: min(24rem, calc(100vw - 2rem)) !important;
    max-width: min(24rem, calc(100vw - 2rem)) !important;
    max-height: calc(100dvh - 2rem) !important;
    overflow: visible !important;
    box-shadow: none !important;
    z-index: 99999 !important;
}
dialog#room-exists-dialog::backdrop {
    position: fixed !important;
    inset: 0 !important;
    background: rgba(10, 10, 10, 0.55) !important;
    backdrop-filter: blur(4px) !important;
    -webkit-backdrop-filter: blur(4px) !important;
}
dialog#room-exists-dialog .modal-card {
    margin: 0 auto !important;
    max-width: 24rem !important;
    width: 100% !important;
    box-sizing: border-box !important;
    position: relative !important;
}
</style>

<div class="rooms-page-wrapper space-y-6">

    <!-- Executive Page Banner with Quick Launch Command Bar -->
    <header class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span>Rooms &amp; Beds</span>
                </h1>
                <p class="dash-hero-sub">Physical property configuration, capacity management, and bed slot inventory.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#8cc2a2; border:1px solid rgba(255,255,255,0.15); padding:0.35rem 0.85rem; font-size:0.75rem; font-weight:600; border-radius:9999px; display:inline-flex; align-items:center;">
                    <span class="live-pulse-dot" style="margin-right:0.45rem;"></span>
                    Inventory Active
                </span>
                <span class="badge" style="background:rgba(255,255,255,0.05); color:#a3a19b; border:1px solid rgba(255,255,255,0.1); padding:0.35rem 0.75rem; font-size:0.75rem; border-radius:9999px; display:inline-flex; align-items:center; gap:0.35rem;">
                    <?= \App\Support\Icon::svg('calendar', 13) ?>
                    <span><?= date('l, M j, Y') ?></span>
                </span>
            </div>
        </div>

        <!-- Quick Launch Command Bar -->
        <div class="dash-cmd-bar">
            <span style="font-size:0.6875rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:rgba(255,255,255,0.5); margin-right:0.35rem;">
                Quick Launch:
            </span>
            <button type="button" class="dash-cmd-btn highlight quick-jump-action" data-action="room" title="Quickly add a new room unit">
                <?= \App\Support\Icon::svg('home', 14) ?>
                <span>Add Room</span>
            </button>
            <button type="button" class="dash-cmd-btn quick-jump-action" data-action="bed" title="Quickly add bed slots">
                <?= \App\Support\Icon::svg('bed', 14) ?>
                <span>Add Bed</span>
            </button>
            <button type="button" class="dash-cmd-btn quick-jump-action" data-action="assign" title="Assign a boarder to a vacant bed">
                <?= \App\Support\Icon::svg('link', 14) ?>
                <span>Assign Boarder</span>
            </button>
            <a href="#beds-directory" class="dash-cmd-btn" title="Jump to beds directory">
                <?= \App\Support\Icon::svg('layers', 14) ?>
                <span>Beds Directory (<?= $totalBeds ?>)</span>
            </a>
            <a href="#rooms-config" class="dash-cmd-btn" title="Jump to room configurations">
                <?= \App\Support\Icon::svg('settings', 14) ?>
                <span>Room Config (<?= $totalRooms ?>)</span>
            </a>
            <a href="#electricity" class="dash-cmd-btn" title="Jump to electricity utility billing">
                <?= \App\Support\Icon::svg('zap', 14) ?>
                <span>Electricity</span>
            </a>
        </div>
    </header>

    <!-- Flash Notifications -->
    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3.5 border border-error-500 flex items-center gap-2.5 shadow-2xs" role="alert">
            <?= \App\Support\Icon::svg('alert-triangle', 18) ?>
            <span class="font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-lg p-3.5 border border-emerald-300 flex items-center gap-2.5 shadow-2xs" role="alert">
            <?= \App\Support\Icon::svg('check', 18) ?>
            <span class="font-medium"><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- 2. KPI Cards: 4 Cards with Deep Links & Operational Insights -->
    <div class="kpi-grid">
        <a href="#rooms-config" class="reveal-card kpi-card metric-accent-primary" title="Jump to room inventory and configurations">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Rooms</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('building') ?></span>
            </div>
            <div class="kpi-value"><?= $totalRooms ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-500">Configured units</span>
            </div>
        </a>
        <a href="#beds-directory" class="reveal-card kpi-card metric-accent-info" title="Jump to bed inventory directory">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Capacity</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('bed') ?></span>
            </div>
            <div class="kpi-value"><?= $totalBeds ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-500">Total bed slots</span>
            </div>
        </a>
        <a href="/admin/staff?view=boarders&status=active" class="reveal-card kpi-card metric-accent-warning" title="View active residents in directory">
            <div class="kpi-card-header">
                <span class="kpi-label">Occupied Beds</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('users') ?></span>
            </div>
            <div class="kpi-value"><?= $occupiedBeds ?></div>
            <div class="kpi-subtext">
                <span class="text-amber-700 font-semibold"><?= $occupancyRate ?>% filled</span>
            </div>
        </a>
        <a href="#beds-directory" class="reveal-card kpi-card metric-accent-success" title="View vacant beds ready for assignment" id="kpi-vacant-beds">
            <div class="kpi-card-header">
                <span class="kpi-label">Vacant Beds</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('circle-dot') ?></span>
            </div>
            <div class="kpi-value <?= $vacantBeds === 0 ? 'text-error-600' : 'text-neutral-900' ?>"><?= $vacantBeds ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-500">Ready for occupancy</span>
            </div>
        </a>
    </div>

    <!-- 3. Sticky Sub-Navigation Bar ("Rooms Command Bar") -->
    <div class="rooms-sticky-nav" id="rooms-command-bar">
        <!-- Segmented Tab Navigation -->
        <nav class="rooms-nav-list" id="rooms-subnav" aria-label="Rooms section navigation">
            <button type="button" class="rooms-nav-tab active" data-target="all" title="View complete overview">
                <?= \App\Support\Icon::svg('layers', 14) ?>
                <span>All Overview</span>
            </button>
            <button type="button" class="rooms-nav-tab" data-target="beds-directory" title="Jump to Beds Directory">
                <?= \App\Support\Icon::svg('bed', 14) ?>
                <span>Beds Directory</span>
                <span class="rooms-nav-badge"><?= $totalBeds ?></span>
            </button>
            <button type="button" class="rooms-nav-tab" data-target="rooms-config" title="Jump to Room Configurations">
                <?= \App\Support\Icon::svg('sliders', 14) ?>
                <span>Room Config</span>
                <span class="rooms-nav-badge"><?= $totalRooms ?></span>
            </button>
            <button type="button" class="rooms-nav-tab" data-target="management-actions" title="Jump to Quick Actions">
                <?= \App\Support\Icon::svg('plus-circle', 14) ?>
                <span>Action Studio</span>
                <span class="rooms-nav-badge">3</span>
            </button>
            <button type="button" class="rooms-nav-tab" data-target="room-types" title="Jump to Room Types & Offerings">
                <?= \App\Support\Icon::svg('tag', 14) ?>
                <span>Room Types</span>
                <span class="rooms-nav-badge"><?= count($roomTypes) ?></span>
            </button>
            <button type="button" class="rooms-nav-tab" data-target="electricity" title="Jump to Electricity Bills">
                <?= \App\Support\Icon::svg('zap', 14) ?>
                <span>Electricity</span>
            </button>
        </nav>

        <!-- View Mode Switcher: "Scroll All" vs "Focus Single Tab" -->
        <div class="nav-toolbar-controls">
            <div class="view-mode-toggle" id="view-mode-toggle" title="Switch between scrollable overview and focused single-tab workspace">
                <button type="button" class="view-mode-btn active" data-mode="scroll">
                    Scroll View
                </button>
                <button type="button" class="view-mode-btn" data-mode="focus">
                    Focus Tab
                </button>
            </div>
        </div>
    </div>

    <!-- 4. Beds Directory Section (Core Operational View) -->
    <section id="beds-directory" class="rooms-section">
        <div class="section-headline-bar">
            <div>
                <h2 class="section-headline-title">
                    <?= \App\Support\Icon::svg('bed', 20) ?>
                    <span>Beds Directory</span>
                </h2>
                <p class="text-caption text-neutral-500 mt-0.5">Real-time room occupancy, assigned resident directory, and bed slot management</p>
            </div>
            
            <!-- Filter Pills & Expand All Toggle -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex bg-neutral-100 p-1 rounded-lg border border-neutral-200 text-xs font-semibold" id="beds-filter-chips">
                    <button type="button" class="beds-filter-btn px-2.5 py-1 rounded-md bg-white text-neutral-900 shadow-2xs" data-filter="all">
                        All (<?= $totalRooms ?>)
                    </button>
                    <button type="button" class="beds-filter-btn px-2.5 py-1 rounded-md text-neutral-600 hover:text-neutral-900" data-filter="vacant">
                        Vacant (<?= $vacantBeds ?>)
                    </button>
                    <button type="button" class="beds-filter-btn px-2.5 py-1 rounded-md text-neutral-600 hover:text-neutral-900" data-filter="occupied">
                        Occupied (<?= $occupiedBeds ?>)
                    </button>
                </div>
                <button type="button" id="toggle-all-beds-drawers" class="btn btn-secondary !py-1 !px-2.5 !text-xs font-semibold">
                    Expand All Drawers
                </button>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm" style="min-width: 780px;" id="beds-directory-table">
                    <thead>
                        <tr class="bg-neutral-50/70 border-b border-neutral-200">
                            <th style="width: 24%;" class="py-3 px-4 text-left font-bold text-neutral-700">Room Unit</th>
                            <th style="width: 20%;" class="py-3 px-4 text-left font-bold text-neutral-700">Setup &amp; Capacity</th>
                            <th style="width: 22%;" class="py-3 px-4 text-left font-bold text-neutral-700">Live Occupancy</th>
                            <th style="width: 16%;" class="py-3 px-4 text-left font-bold text-neutral-700">Availability</th>
                            <th style="width: 18%; text-align: right;" class="py-3 px-4 font-bold text-neutral-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="beds-directory-tbody">
                        <?php 
                        foreach ($rooms as $r): 
                            $rId = (int) $r['id'];
                            $roomBeds = $bedsByRoom[$rId]['beds'] ?? [];
                            $roomOccupied = count(array_filter($roomBeds, fn($b) => ($b['status'] ?? '') === 'occupied'));
                            $roomVacant = count($roomBeds) - $roomOccupied;
                            $roomCapacity = (int) $r['capacity'];
                            $roomBedCount = count($roomBeds);
                            $hasVacancy = $roomVacant > 0;
                            $hasOccupied = $roomOccupied > 0;
                            $isFull = ($roomBedCount > 0 && $roomVacant === 0);
                        ?>
                        <!-- Main Room Row -->
                        <tr class="room-main-row transition-colors cursor-pointer border-b border-neutral-100 hover:bg-neutral-50/80"
                            id="room-row-<?= $rId ?>"
                            data-id="<?= $rId ?>"
                            data-room-number="<?= htmlspecialchars($r['room_number']) ?>"
                            data-floor="<?= htmlspecialchars($r['floor'] ?? '') ?>"
                            data-room-type="<?= htmlspecialchars($r['room_type_name'] ?? '') ?>"
                            data-has-vacant="<?= $hasVacancy ? '1' : '0' ?>"
                            data-has-occupied="<?= $hasOccupied ? '1' : '0' ?>"
                            data-is-full="<?= $isFull ? '1' : '0' ?>"
                            title="Click row to view bed slots and residents">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-lg bg-neutral-100 flex items-center justify-center text-neutral-700 font-bold text-xs flex-shrink-0 border border-neutral-200">
                                        <?= htmlspecialchars($r['room_number']) ?>
                                    </div>
                                    <div>
                                        <span class="font-bold text-neutral-900 block text-sm">
                                            Room <?= htmlspecialchars($r['room_number']) ?>
                                            <?php if (!empty($r['floor'])): ?>
                                                <span class="text-caption font-semibold text-neutral-500 bg-neutral-100 px-1.5 py-0.5 rounded ml-1">F<?= htmlspecialchars($r['floor']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="text-caption text-neutral-500 font-normal">
                                            <?= htmlspecialchars($r['room_type_name'] ?? 'Custom Unit') ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-neutral-800 font-medium">
                                    <?= $roomBedCount ?> of <?= $roomCapacity ?> bed<?= $roomCapacity !== 1 ? 's' : '' ?>
                                </div>
                                <?php if ($roomBedCount < $roomCapacity): ?>
                                    <span class="text-caption text-amber-700 font-medium block">
                                        <?= $roomCapacity - $roomBedCount ?> not set up yet
                                    </span>
                                <?php else: ?>
                                    <span class="text-caption text-neutral-400 block">Fully configured</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="visual-occupancy-bar" title="<?= $roomOccupied ?> of <?= $roomBedCount ?> beds occupied">
                                        <?php for ($i = 0; $i < $roomCapacity; $i++): ?>
                                            <?php if ($i < $roomOccupied): ?>
                                                <span class="occupancy-segment segment-occupied" title="Bed occupied"></span>
                                            <?php elseif ($i < $roomBedCount): ?>
                                                <span class="occupancy-segment segment-vacant" title="Bed vacant"></span>
                                            <?php else: ?>
                                                <span class="occupancy-segment segment-empty-slot" title="Bed not yet created"></span>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-xs font-mono font-semibold text-neutral-700 whitespace-nowrap">
                                        <?= $roomOccupied ?> / <?= $roomBedCount ?>
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($roomBedCount === 0): ?>
                                    <span class="badge badge-info">No beds yet</span>
                                <?php elseif ($roomOccupied > 0 && $roomVacant > 0): ?>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="badge badge-warning"><?= $roomOccupied ?> Occupied</span>
                                        <span class="badge badge-success"><?= $roomVacant ?> Vacant</span>
                                    </div>
                                <?php elseif ($roomVacant > 0): ?>
                                    <span class="badge badge-success"><?= $roomVacant ?> Vacant</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Fully Occupied</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4" style="text-align: right;">
                                <div class="action-cluster" style="justify-content: flex-end;">
                                    <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs beds-toggle-btn cursor-pointer inline-flex items-center gap-1.5" data-id="<?= $rId ?>" aria-expanded="false" aria-controls="beds-row-<?= $rId ?>">
                                        <span>Manage Beds (<?= $roomBedCount ?>)</span>
                                        <svg class="chevron-arrow w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                    <form method="post" action="/admin/rooms/<?= $rId ?>/delete" class="inline"
                                          data-confirm="Only allowed if it has no beds."
                                          data-confirm-title="Delete Room <?= htmlspecialchars($r['room_number']) ?>?"
                                          data-confirm-btn="Delete"
                                          data-confirm-danger>
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn-danger !py-1 !px-2.5 !text-xs" title="Delete room">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Slide-Down Beds Drawer Row -->
                        <tr id="beds-row-<?= $rId ?>" class="hidden beds-drawer-row border-b border-neutral-200">
                            <td colspan="5" style="padding: 0.85rem 1.25rem 1.35rem;">
                                <div class="beds-drawer-box">
                                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-neutral-100">
                                        <div class="flex items-center gap-2">
                                            <?= \App\Support\Icon::svg('bed', 18) ?>
                                            <h4 class="text-body-sm font-semibold text-neutral-900">
                                                Bed Slots in Room <?= htmlspecialchars($r['room_number']) ?> &mdash;
                                                <span class="text-primary-700 font-bold"><?= $roomBedCount ?> of <?= $roomCapacity ?> bed<?= $roomCapacity !== 1 ? 's' : '' ?></span>
                                            </h4>
                                        </div>
                                        <span class="text-caption text-neutral-400">Click room row or button above to close</span>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="w-full text-body-sm">
                                            <thead>
                                                <tr style="background:#f8f7f5; border-bottom:1px solid #e6e5e2;">
                                                    <th style="padding:0.5rem 0.75rem; font-size:0.6875rem; text-align: left;" class="font-bold text-neutral-700">Bed Label / Renaming</th>
                                                    <th style="padding:0.5rem 0.75rem; font-size:0.6875rem; text-align: left;" class="font-bold text-neutral-700">Status</th>
                                                    <th style="padding:0.5rem 0.75rem; font-size:0.6875rem; text-align: left;" class="font-bold text-neutral-700">Assigned Boarder</th>
                                                    <th style="padding:0.5rem 0.75rem; font-size:0.6875rem; text-align: right;" class="font-bold text-neutral-700">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($roomBeds as $b): ?>
                                                <tr class="border-b border-neutral-100 hover:bg-neutral-50/50 <?= ($b['status'] ?? '') === 'occupied' ? 'bg-amber-50/40' : '' ?>">
                                                    <td style="padding:0.55rem 0.75rem;" class="font-semibold text-neutral-800">
                                                        <form method="post" action="/admin/beds/<?= (int) $b['id'] ?>/update" class="inline-flex items-center gap-1.5">
                                                            <?= \App\Support\Csrf::field() ?>
                                                            <label class="sr-only" for="bed-label-<?= (int) $b['id'] ?>">Bed label</label>
                                                            <input id="bed-label-<?= (int) $b['id'] ?>" name="label" value="<?= htmlspecialchars($b['label']) ?>"
                                                                   required maxlength="20" class="input input-sm !py-1 text-xs" style="width: 8rem;">
                                                            <button class="btn btn-secondary !py-0.5 !px-2.5 !text-xs font-semibold" title="Save the new bed label">Rename</button>
                                                        </form>
                                                    </td>
                                                    <td style="padding:0.55rem 0.75rem;">
                                                        <span class="badge <?= ($b['status'] ?? '') === 'occupied' ? 'badge-warning' : 'badge-success' ?>">
                                                            <?= htmlspecialchars($b['status'] ?? 'vacant') ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding:0.55rem 0.75rem;">
                                                        <?php if (!empty($b['boarder_name'])): ?>
                                                            <div class="inline-flex items-center gap-1.5">
                                                                <span class="h-5 w-5 rounded-full bg-neutral-200 text-neutral-700 text-[10px] font-bold flex items-center justify-center">
                                                                    <?= strtoupper(substr($b['boarder_name'], 0, 1)) ?>
                                                                </span>
                                                                <span class="text-neutral-900 font-semibold text-xs"><?= htmlspecialchars($b['boarder_name']) ?></span>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-neutral-400 text-xs italic">Vacant slot (ready for boarder)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding:0.55rem 0.75rem; text-align: right;">
                                                        <form method="post" action="/admin/beds/<?= (int) $b['id'] ?>/delete" class="inline"
                                                              data-confirm="Only allowed if vacant."
                                                              data-confirm-title="Delete this bed?"
                                                              data-confirm-btn="Delete"
                                                              data-confirm-danger>
                                                            <?= \App\Support\Csrf::field() ?>
                                                            <button class="btn-danger !py-0.5 !px-2.5 !text-xs" title="Delete bed">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($roomBeds)): ?>
                                                <tr>
                                                    <td colspan="4" class="p-4 text-center text-neutral-400 text-xs italic">
                                                        No beds configured in this room yet. Use the "Add Bed" action below to create bed slots.
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="beds-filter-empty" class="hidden">
                            <td colspan="5" class="p-8 text-center text-neutral-500">
                                <div class="empty-icon !w-10 !h-10 text-neutral-400 !bg-neutral-100 mx-auto mb-2 flex items-center justify-center rounded-full">
                                    <?= \App\Support\Icon::svg('bed', 20) ?>
                                </div>
                                <p class="font-semibold text-neutral-800 text-sm">No rooms match this filter</p>
                                <p class="text-caption text-neutral-500 mt-0.5">Switch filter to "All" or "Vacant" to view available rooms.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- 5. Room Configurations Section (Property & Rate Setup) -->
    <section id="rooms-config" class="rooms-section">
        <div class="section-headline-bar">
            <div>
                <h2 class="section-headline-title">
                    <?= \App\Support\Icon::svg('sliders', 20) ?>
                    <span>Room Configurations</span>
                </h2>
                <p class="text-caption text-neutral-500 mt-0.5">Physical property attributes, pricing models, capacity boundaries, and live unit updates</p>
            </div>
            
            <!-- Group By & Sort By Dropdowns Toolbar -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="flex items-center gap-1.5 bg-neutral-50 px-2.5 py-1 rounded-lg border border-neutral-200">
                    <label for="room-group-by" class="text-caption font-bold text-neutral-600 whitespace-nowrap">Group:</label>
                    <select id="room-group-by" class="input input-sm font-medium !py-0.5 !px-2 !text-xs !bg-white border-neutral-300 rounded shadow-2xs cursor-pointer">
                        <option value="none" <?= ($groupBy ?? 'none') === 'none' ? 'selected' : '' ?>>None (All Rooms)</option>
                        <option value="floor" <?= ($groupBy ?? '') === 'floor' ? 'selected' : '' ?>>By Floor</option>
                        <option value="type" <?= ($groupBy ?? '') === 'type' ? 'selected' : '' ?>>By Room Type</option>
                        <option value="capacity" <?= ($groupBy ?? '') === 'capacity' ? 'selected' : '' ?>>By Capacity</option>
                    </select>
                </div>
                <div class="flex items-center gap-1.5 bg-neutral-50 px-2.5 py-1 rounded-lg border border-neutral-200">
                    <label for="room-sort-by" class="text-caption font-bold text-neutral-600 whitespace-nowrap">Sort:</label>
                    <select id="room-sort-by" class="input input-sm font-medium !py-0.5 !px-2 !text-xs !bg-white border-neutral-300 rounded shadow-2xs cursor-pointer">
                        <option value="room_desc" <?= ($sort ?? 'room_desc') === 'room_desc' ? 'selected' : '' ?>>Room # (18 → 1)</option>
                        <option value="room_asc" <?= ($sort ?? '') === 'room_asc' ? 'selected' : '' ?>>Room # (1 → 18)</option>
                        <option value="floor_desc" <?= ($sort ?? '') === 'floor_desc' ? 'selected' : '' ?>>Floor (High → Low)</option>
                        <option value="floor_asc" <?= ($sort ?? '') === 'floor_asc' ? 'selected' : '' ?>>Floor (Low → High)</option>
                        <option value="type_asc" <?= ($sort ?? '') === 'type_asc' ? 'selected' : '' ?>>Room Type (A → Z)</option>
                        <option value="capacity_desc" <?= ($sort ?? '') === 'capacity_desc' ? 'selected' : '' ?>>Capacity (High → Low)</option>
                        <option value="capacity_asc" <?= ($sort ?? '') === 'capacity_asc' ? 'selected' : '' ?>>Capacity (Low → High)</option>
                        <option value="price_desc" <?= ($sort ?? '') === 'price_desc' ? 'selected' : '' ?>>Rent (High → Low)</option>
                        <option value="price_asc" <?= ($sort ?? '') === 'price_asc' ? 'selected' : '' ?>>Rent (Low → High)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <?php if (empty($rooms)): ?>
            <div class="empty-state" style="padding:3.5rem 0;">
                <span class="empty-icon"><?= \App\Support\Icon::svg('home') ?></span>
                <span class="text-sm font-semibold text-neutral-800">No rooms configured yet</span>
                <span class="text-xs text-neutral-500 mt-1">Use the "Add Room" form in the Action Studio below to configure your first room unit.</span>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm" style="min-width: 860px;" id="rooms-config-table">
                    <thead>
                        <tr class="bg-neutral-50/70 border-b border-neutral-200">
                            <th style="width: 14%;" class="py-3 px-4 font-bold text-neutral-700">Room #</th>
                            <th style="width: 11%;" class="py-3 px-4 font-bold text-neutral-700">Floor</th>
                            <th style="width: 23%;" class="py-3 px-4 font-bold text-neutral-700">Room Type Offering</th>
                            <th style="width: 16%;" class="py-3 px-4 font-bold text-neutral-700">Max Capacity</th>
                            <th style="width: 19%;" class="py-3 px-4 font-bold text-neutral-700">Rent per Bed / mo</th>
                            <th style="width: 17%; text-align: right;" class="py-3 px-4 font-bold text-neutral-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rooms-config-tbody">
                        <?php foreach ($rooms as $r): ?>
                        <tr class="room-config-row border-b border-neutral-100 hover:bg-neutral-50/60 transition-colors"
                            data-room-id="<?= (int)$r['id'] ?>"
                            data-room-number="<?= htmlspecialchars($r['room_number']) ?>"
                            data-floor="<?= htmlspecialchars($r['floor'] ?? '') ?>"
                            data-room-type="<?= htmlspecialchars($r['room_type_name'] ?? 'No room type') ?>"
                            data-capacity="<?= (int)$r['capacity'] ?>"
                            data-price="<?= (float)$r['base_price'] ?>">
                            <td class="py-2.5 px-4">
                                <?php $csrf_field = \App\Support\Csrf::field(); ?>
                                <form id="room-update-<?= (int)$r['id'] ?>" method="post" action="/admin/rooms/<?= (int) $r['id'] ?>/update">
                                    <?= $csrf_field ?>
                                </form>
                                <input form="room-update-<?= (int)$r['id'] ?>" name="room_number" value="<?= htmlspecialchars($r['room_number']) ?>"
                                       class="input input-sm font-bold text-neutral-900 !bg-white focus:!bg-amber-50/20" style="width:5.5rem;" title="Room number">
                            </td>
                            <td class="py-2.5 px-4">
                                <input form="room-update-<?= (int)$r['id'] ?>" name="floor" value="<?= htmlspecialchars($r['floor'] ?? '') ?>"
                                       class="input input-sm !bg-white" style="width:4.5rem;" placeholder="—" title="Floor">
                            </td>
                            <td class="py-2.5 px-4">
                                <select form="room-update-<?= (int)$r['id'] ?>" name="room_type_id" data-room-type class="input input-sm !bg-white" style="max-width:13.5rem;" title="Room type">
                                    <option value="">— None —</option>
                                    <?php foreach ($roomTypes as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>" data-capacity="<?= (int) $t['capacity'] ?>" data-rate="<?= \App\Models\RoomType::ratePerBed($t) ?>" <?= (int) ($r['room_type_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="py-2.5 px-4">
                                <div class="flex items-center gap-1.5">
                                    <input form="room-update-<?= (int)$r['id'] ?>" name="capacity" type="number" min="1" max="50" value="<?= (int) $r['capacity'] ?>" <?= $r['room_type_id'] ? 'readonly' : '' ?>
                                           class="input input-sm font-semibold !bg-white" style="width:4.5rem;" title="Capacity">
                                    <span class="text-caption text-neutral-400">beds</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-neutral-500 font-semibold text-xs">₱</span>
                                    <input form="room-update-<?= (int)$r['id'] ?>" name="base_price" type="number" step="0.01" min="0" value="<?= htmlspecialchars($r['base_price']) ?>" <?= $r['room_type_id'] ? 'readonly' : '' ?>
                                           class="input input-sm font-semibold font-mono !bg-white" style="width:7.5rem;" title="Base price">
                                </div>
                            </td>
                            <td class="py-2.5 px-4">
                                <div class="action-cluster" style="justify-content: flex-end;">
                                    <button type="submit" form="room-update-<?= (int)$r['id'] ?>" class="btn btn-primary !py-1 !px-3 !text-xs font-semibold">
                                        Save
                                    </button>
                                    <form method="post" action="/admin/rooms/<?= (int) $r['id'] ?>/delete"
                                          data-confirm="Only allowed if it has no beds."
                                          data-confirm-title="Delete Room <?= htmlspecialchars($r['room_number']) ?>?"
                                          data-confirm-btn="Delete"
                                          data-confirm-danger>
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn-danger !py-1 !px-2.5 !text-xs font-semibold" title="Delete room">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- 6. Management Actions: Action Studio -->
    <section id="management-actions" class="rooms-section">
        <div class="section-headline-bar">
            <div>
                <h2 class="section-headline-title">
                    <?= \App\Support\Icon::svg('plus-circle', 20) ?>
                    <span>Action Studio</span>
                </h2>
                <p class="text-caption text-neutral-500 mt-0.5">Streamlined workflows to add rooms, configure batch bed slots, and assign boarders</p>
            </div>
            <div class="action-layout-toggle">
                <span>View layout:</span>
                <div class="inline-flex bg-neutral-100 p-0.5 rounded-lg border border-neutral-200">
                    <button type="button" class="action-layout-btn active px-2.5 py-0.5 text-xs font-semibold rounded bg-white text-neutral-900 shadow-2xs" data-layout="tabbed">
                        Tabbed View
                    </button>
                    <button type="button" class="action-layout-btn px-2.5 py-0.5 text-xs font-semibold rounded text-neutral-600 hover:text-neutral-900" data-layout="grid">
                        3-Column Grid
                    </button>
                </div>
            </div>
        </div>

        <div class="action-studio-card">
            <!-- Studio Tab Switcher -->
            <div class="action-studio-header" id="action-studio-header">
                <div class="action-tab-group" id="action-studio-tabs" role="tablist">
                    <button type="button" class="action-tab-btn active" data-tab="add-room" role="tab" aria-selected="true">
                        <?= \App\Support\Icon::svg('home', 15) ?>
                        <span>1. Add Room Unit</span>
                    </button>
                    <button type="button" class="action-tab-btn" data-tab="add-bed" role="tab" aria-selected="false">
                        <?= \App\Support\Icon::svg('bed', 15) ?>
                        <span>2. Add Beds to Room</span>
                    </button>
                    <button type="button" class="action-tab-btn" data-tab="assign-boarder" role="tab" aria-selected="false">
                        <?= \App\Support\Icon::svg('link', 15) ?>
                        <span>3. Assign Resident to Bed</span>
                    </button>
                </div>
                <div class="text-caption text-neutral-500 hidden sm:block">
                    Select a workflow above or use the layout switcher
                </div>
            </div>

            <!-- Action Panels Container -->
            <div class="action-panels-container mode-tabbed" id="action-panels-container">

                <!-- Action 1: Add Room Form -->
                <div class="action-panel-item active" id="action-panel-add-room">
                    <div class="action-panel-title-bar">
                        <span class="action-panel-icon"><?= \App\Support\Icon::svg('home', 18) ?></span>
                        <div>
                            <h3 class="text-body font-bold text-neutral-900">Add Room Unit</h3>
                            <p class="text-caption text-neutral-500">Create a physical unit with floor designation, capacity, and linked rate</p>
                        </div>
                    </div>

                    <form method="post" action="/admin/rooms" class="space-y-4 max-w-2xl" id="add-room-form">
                        <?= \App\Support\Csrf::field() ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600 mb-1" for="add-room-number">
                                    Room Number <span class="text-error-600">*</span>
                                </label>
                                <input type="number" step="1" min="1" name="room_number" id="add-room-number" placeholder="e.g. 101" required class="input font-mono" inputmode="numeric">
                                <p id="room-number-feedback" class="text-xs mt-1 font-medium hidden"></p>
                            </div>
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600 mb-1" for="add-room-floor">
                                    Floor
                                </label>
                                <input type="number" step="1" min="1" name="floor" id="add-room-floor" placeholder="e.g. 1" class="input font-mono" inputmode="numeric">
                            </div>
                        </div>

                        <div>
                            <label class="block text-caption font-semibold text-neutral-600 mb-1">
                                Room Type Offering
                            </label>
                            <select name="room_type_id" class="input" data-room-type>
                                <option value="">— Not one of the offerings (Custom setup) —</option>
                                <?php foreach ($roomTypes as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>" data-capacity="<?= (int) $t['capacity'] ?>" data-rate="<?= \App\Models\RoomType::ratePerBed($t) ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600 mb-1">Capacity (Beds)</label>
                                <input name="capacity" type="number" min="1" max="50" value="2" required class="input font-mono">
                            </div>
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600 mb-1">Monthly Rent per Bed (₱)</label>
                                <input name="base_price" type="number" step="0.01" min="0" value="3500" required class="input font-mono font-semibold">
                            </div>
                        </div>

                        <div class="pt-2">
                            <button class="btn btn-primary !py-2.5 px-5 !text-xs font-semibold" id="create-room-btn">
                                <?= \App\Support\Icon::svg('plus', 14) ?>
                                <span>Create Room Unit</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Action 2: Add Bed Form -->
                <div class="action-panel-item" id="action-panel-add-bed">
                    <div class="action-panel-title-bar">
                        <span class="action-panel-icon icon-emerald"><?= \App\Support\Icon::svg('bed', 18) ?></span>
                        <div>
                            <h3 class="text-body font-bold text-neutral-900">Add Beds to Room</h3>
                            <p class="text-caption text-neutral-500">Create individual or batch sequential bed slots within an existing room</p>
                        </div>
                    </div>

                    <form method="post" action="/admin/beds" class="space-y-4 max-w-2xl">
                        <?= \App\Support\Csrf::field() ?>
                        <div>
                            <label class="block text-caption font-semibold text-neutral-600 mb-1" for="add-bed-room">
                                Target Room <span class="text-error-600">*</span>
                            </label>
                            <select name="room_id" id="add-bed-room" required class="input">
                                <option value="">— Choose Room —</option>
                                <?php foreach ($rooms as $r): ?>
                                    <option value="<?= (int) $r['id'] ?>">Room <?= htmlspecialchars($r['room_number']) ?> (<?= htmlspecialchars($r['room_type_name'] ?? 'Custom') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600 mb-1" for="add-bed-count">Number of Beds to Create</label>
                                <input type="number" name="count" id="add-bed-count" min="1" max="50" value="1" required class="input font-mono" placeholder="1">
                            </div>
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600 mb-1" for="add-bed-label">Bed Label or Prefix</label>
                                <input name="label" id="add-bed-label" placeholder="e.g. Bed A, Bed 1, or Bed" required class="input">
                            </div>
                        </div>

                        <div id="add-bed-preview" class="hidden text-caption text-neutral-600 bg-neutral-50 rounded-lg p-3 border border-neutral-200">
                            <span class="font-semibold text-neutral-700">Preview:</span> <span id="add-bed-preview-text" class="font-mono text-emerald-700 font-semibold"></span>
                        </div>

                        <div class="pt-2">
                            <button class="btn btn-primary !py-2.5 px-5 !text-xs font-semibold" id="add-bed-submit">
                                <?= \App\Support\Icon::svg('plus', 14) ?>
                                <span>Add Bed</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Action 3: Assign Boarder to Bed Form -->
                <div class="action-panel-item" id="action-panel-assign-boarder">
                    <div class="action-panel-title-bar">
                        <span class="action-panel-icon icon-blue"><?= \App\Support\Icon::svg('link', 18) ?></span>
                        <div>
                            <h3 class="text-body font-bold text-neutral-900">Assign Resident to Bed</h3>
                            <p class="text-caption text-neutral-500">Allocate an active or pending boarder into an available vacant bed</p>
                        </div>
                    </div>

                    <form method="post" action="/admin/beds/assign" class="space-y-4 max-w-2xl">
                        <?= \App\Support\Csrf::field() ?>
                        <div>
                            <label for="rooms-assign-boarder" class="block text-caption font-semibold text-neutral-600 mb-1">
                                Select Boarder <span class="text-error-600">*</span>
                            </label>
                            <select id="rooms-assign-boarder" name="boarder_id" required class="input">
                                <option value="">— Choose Boarder —</option>
                                <?php foreach ($assignableBoarders as $b): ?>
                                    <option value="<?= (int) $b['user_id'] ?>">
                                        <?= htmlspecialchars($b['name']) ?> <?= !empty($b['bed_id']) ? '(Currently: ' . htmlspecialchars(($b['room_number'] ?? '') . '/' . ($b['bed_label'] ?? '')) . ')' : '(Unassigned)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$assignableBoarders): ?>
                                <p class="text-caption text-neutral-400 mt-1">
                                    No boarders available to assign. Add one on the <a href="/admin/boarders" class="text-primary-600 font-medium hover:underline">Boarders page</a>.
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="rooms-assign-type" class="block text-caption font-semibold text-neutral-600 mb-1">
                                    Filter by Room Type
                                </label>
                                <select id="rooms-assign-type" class="input" data-filters-beds="rooms-assign-bed">
                                    <option value="">— All room types —</option>
                                    <?php foreach ($roomTypes as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= (int) $t['vacant_bed_count'] ?> vacant)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="rooms-assign-bed" class="block text-caption font-semibold text-neutral-600 mb-1">
                                    Select Vacant Bed <span class="text-error-600">*</span>
                                </label>
                                <select id="rooms-assign-bed" name="bed_id" required class="input">
                                    <option value="">— Choose Vacant Bed —</option>
                                    <?php foreach ($vacantBedsList as $b):
                                        $label = trim((string) ($b['label'] ?? ''));
                                        $bedText = str_starts_with(strtolower($label), 'bed') ? $label : 'Bed ' . $label;
                                    ?>
                                        <option value="<?= (int) $b['id'] ?>" data-room-type="<?= (int) ($b['room_type_id'] ?? 0) ?>">Room <?= htmlspecialchars($b['room_number']) ?> / <?= htmlspecialchars($bedText) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="text-caption text-neutral-600 bg-neutral-50 rounded-lg p-3 border border-neutral-200">
                            Reassigning an active resident will safely relocate them to the new bed and immediately free up their previous bed slot.
                        </div>

                        <div class="pt-2">
                            <button class="btn btn-secondary !py-2.5 px-5 !text-xs font-semibold">
                                <?= \App\Support\Icon::svg('link', 14) ?>
                                <span>Assign Bed Slot</span>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </section>

    <!-- 7. Room Types: Offerings advertised on website ("Choose how you live") -->
    <section id="room-types" class="rooms-section">
        <div class="section-headline-bar">
            <div>
                <h2 class="section-headline-title">
                    <?= \App\Support\Icon::svg('tag', 20) ?>
                    <span>Room Types &amp; Public Offerings</span>
                </h2>
                <p class="text-caption text-neutral-500 mt-0.5">Catalog tiers shown on the public site and inquiry center. Updates automatically sync across all linked rooms.</p>
            </div>
            <span class="section-headline-badge"><?= count($roomTypes) ?> Active Offerings</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($roomTypes as $t): ?>
            <div class="card p-5 flex flex-col justify-between border border-neutral-200 hover:border-neutral-300 transition-shadow hover:shadow-sm" style="gap:0.75rem;">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <span class="text-caption font-bold text-neutral-500 uppercase tracking-wider"><?= htmlspecialchars($t['capacity_label']) ?></span>
                        <?php if (!empty($t['badge'])): ?>
                            <span class="text-caption font-bold" style="background:#fbf4ef; color:#b15f2c; padding:0.2rem 0.65rem; border-radius:999px;">
                                <?= htmlspecialchars($t['badge']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-body-sm font-bold text-neutral-900 mb-1"><?= htmlspecialchars($t['name']) ?></h3>
                    <p class="text-lg font-bold text-neutral-900 font-mono tracking-tight">
                        <?= htmlspecialchars(\App\Models\RoomType::priceLabel($t)) ?>
                    </p>
                    <p class="text-caption text-neutral-500 mt-0.5">
                        <?= (int) $t['capacity'] === 1 ? 'Exclusive: 1 bed per room' : (int) $t['capacity'] . ' beds per room' ?> &bull; billed ₱<?= number_format(\App\Models\RoomType::ratePerBed($t), 2) ?> / mo per resident
                    </p>
                    
                    <ul class="text-body-sm text-neutral-600 mt-3 space-y-1" style="list-style:disc; padding-left:1.15rem;">
                        <?php foreach ($t['feature_list'] as $feature): ?>
                            <li><?= htmlspecialchars($feature) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div>
                    <div class="p-2.5 rounded-lg bg-neutral-50 border border-neutral-100 my-2">
                        <p class="text-caption <?= (int) $t['room_count'] === 0 ? 'text-amber-700' : 'text-neutral-600' ?> font-semibold">
                            <?php if ((int) $t['room_count'] === 0): ?>
                                No physical room linked yet, so nothing of this type can be assigned.
                            <?php else: ?>
                                <?= (int) $t['room_count'] ?> room<?= (int) $t['room_count'] !== 1 ? 's' : '' ?> &bull; <?= (int) $t['bed_count'] ?> bed<?= (int) $t['bed_count'] !== 1 ? 's' : '' ?> &bull; <?= (int) $t['vacant_bed_count'] ?> vacant
                            <?php endif; ?>
                        </p>
                    </div>

                    <details class="group">
                        <summary class="text-caption font-bold text-neutral-700 hover:text-primary-600 cursor-pointer py-1 flex items-center justify-between">
                            <span>Edit offering settings</span>
                            <span class="text-xs group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <form method="post" action="/admin/room-types/<?= (int) $t['id'] ?>/update" class="space-y-2.5 mt-2 pt-2 border-t border-neutral-100">
                            <?= \App\Support\Csrf::field() ?>
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600">Name</label>
                                <input name="name" value="<?= htmlspecialchars($t['name']) ?>" required maxlength="100" class="input input-sm">
                            </div>
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600">Capacity Description</label>
                                <input name="capacity_label" value="<?= htmlspecialchars($t['capacity_label']) ?>" required maxlength="60" class="input input-sm">
                            </div>
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600">Residents per room</label>
                                <input name="capacity" type="number" min="1" max="50" value="<?= (int) $t['capacity'] ?>" required class="input input-sm font-mono">
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="flex-1">
                                    <label class="block text-caption font-semibold text-neutral-600">Price (₱ / mo)</label>
                                    <input name="price" type="number" step="0.01" min="0" value="<?= htmlspecialchars($t['price']) ?>" required class="input input-sm font-mono">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-caption font-semibold text-neutral-600">Charged</label>
                                    <select name="price_unit" class="input input-sm">
                                        <option value="room" <?= $t['price_unit'] === 'room' ? 'selected' : '' ?>>per room</option>
                                        <option value="bed" <?= $t['price_unit'] === 'bed' ? 'selected' : '' ?>>per bed</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600">Badge <span class="font-normal text-neutral-400">(optional)</span></label>
                                <input name="badge" value="<?= htmlspecialchars($t['badge'] ?? '') ?>" maxlength="40" class="input input-sm">
                            </div>
                            <label class="text-caption font-semibold text-neutral-700 flex items-center gap-2 cursor-pointer py-1">
                                <input type="checkbox" name="is_popular" value="1" <?= $t['is_popular'] ? 'checked' : '' ?>>
                                <span>Highlight as most popular</span>
                            </label>
                            <div>
                                <label class="block text-caption font-semibold text-neutral-600">Features <span class="font-normal text-neutral-400">(one per line)</span></label>
                                <textarea name="features" rows="4" required class="input input-sm"><?= htmlspecialchars(implode("\n", $t['feature_list'])) ?></textarea>
                            </div>
                            <button class="btn btn-primary w-full !py-2 !text-xs font-semibold">Save offering</button>
                        </form>
                    </details>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 8. Electricity Bills Section -->
    <section id="electricity" class="rooms-section">
        <div class="section-headline-bar">
            <div>
                <h2 class="section-headline-title">
                    <?= \App\Support\Icon::svg('zap', 20) ?>
                    <span>Electricity Bills</span>
                </h2>
                <p class="text-caption text-neutral-500 mt-0.5">Utility bills applied to rooms, split equally among active residents and due with their next rent</p>
            </div>
            <span class="section-headline-badge"><?= count($electricityBills) ?> Recorded Bills</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
            <!-- Left: Enter Room Bill Form -->
            <form method="post" action="/admin/electricity" class="card p-5 space-y-4" data-testid="electricity-form">
                <div class="flex items-center gap-2 pb-2 border-b border-neutral-100">
                    <span class="action-panel-icon icon-emerald"><?= \App\Support\Icon::svg('zap', 18) ?></span>
                    <div>
                        <h3 class="text-body-sm font-bold text-neutral-900">Enter a Room's Bill</h3>
                        <p class="text-caption text-neutral-500">Calculates equal resident shares</p>
                    </div>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div>
                    <label for="electricity-room" class="block text-caption font-semibold text-neutral-600 mb-1">Target Room</label>
                    <select name="room_id" id="electricity-room" required class="input">
                        <option value="">— Choose Room —</option>
                        <?php foreach ($rooms as $r): $n = $residentsPerRoom[(int) $r['id']] ?? 0; ?>
                            <option value="<?= (int) $r['id'] ?>" data-residents="<?= $n ?>" <?= $n === 0 ? 'disabled' : '' ?>>
                                Room <?= htmlspecialchars($r['room_number']) ?> (<?= $n ?> resident<?= $n === 1 ? '' : 's' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="electricity-label" class="block text-caption font-semibold text-neutral-600 mb-1">Billing Period / Label</label>
                    <input name="label" id="electricity-label" maxlength="60" required class="input" placeholder="e.g. September 2026">
                </div>
                <div>
                    <label for="electricity-amount" class="block text-caption font-semibold text-neutral-600 mb-1">Room's Total Bill (₱)</label>
                    <input name="amount" id="electricity-amount" type="number" step="0.01" min="1" max="1000000" required class="input font-mono font-semibold">
                </div>
                <p id="electricity-share" class="text-caption text-neutral-600 bg-neutral-50 rounded-md p-2.5 border border-neutral-200" aria-live="polite">
                    Each resident's share appears here.
                </p>
                <button class="btn btn-primary w-full !py-2.5 !text-xs font-semibold">
                    Split and Bill the Residents
                </button>
            </form>

            <!-- Right: Recorded Electricity Bills Table -->
            <div class="card overflow-hidden lg:col-span-2">
                <div class="p-3.5 bg-neutral-50/70 border-b border-neutral-200 font-bold text-neutral-800 text-xs flex items-center justify-between">
                    <span>Recent Electricity Ledger</span>
                    <span class="text-neutral-500 font-normal">Removed bills automatically reverse resident shares</span>
                </div>
                <?php if (empty($electricityBills)): ?>
                    <div class="empty-state" style="padding:3rem 0;">
                        <span class="empty-icon"><?= \App\Support\Icon::svg('zap') ?></span>
                        <span class="text-sm font-semibold text-neutral-800">No electricity bills entered yet</span>
                        <span class="text-xs text-neutral-500 mt-1">Each share is billed together with that resident's next rent cycle.</span>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-body-sm" data-testid="electricity-bills">
                            <thead>
                                <tr class="border-b border-neutral-200 bg-neutral-50/40">
                                    <th class="py-2.5 px-3.5 text-left font-bold text-neutral-700">Room</th>
                                    <th class="py-2.5 px-3.5 text-left font-bold text-neutral-700">Period</th>
                                    <th class="py-2.5 px-3.5 text-left font-bold text-neutral-700">Total Bill</th>
                                    <th class="py-2.5 px-3.5 text-left font-bold text-neutral-700">Each Resident Share</th>
                                    <th class="py-2.5 px-3.5 text-left font-bold text-neutral-700">Recorded</th>
                                    <th class="py-2.5 px-3.5 text-right font-bold text-neutral-700">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($electricityBills as $bill): ?>
                                    <tr class="border-b border-neutral-100 hover:bg-neutral-50/50">
                                        <td class="py-2.5 px-3.5 font-bold text-neutral-900">Room <?= htmlspecialchars($bill['room_number']) ?></td>
                                        <td class="py-2.5 px-3.5"><?= htmlspecialchars($bill['label']) ?></td>
                                        <td class="py-2.5 px-3.5 font-mono font-semibold">₱<?= number_format((float) $bill['amount'], 2) ?></td>
                                        <td class="py-2.5 px-3.5 font-mono text-neutral-700">₱<?= number_format((float) $bill['share'], 2) ?> × <?= (int) $bill['residents'] ?></td>
                                        <td class="py-2.5 px-3.5 text-neutral-500 text-xs"><?= date('M j, Y', strtotime($bill['created_at'])) ?></td>
                                        <td class="py-2.5 px-3.5 text-right">
                                            <form method="post" action="/admin/electricity/<?= (int) $bill['id'] ?>/delete" class="action-cluster" style="justify-content: flex-end;"
                                                  data-confirm="Every resident's share of it is removed and their balance recalculated."
                                                  data-confirm-title="Remove this electricity bill?"
                                                  data-confirm-btn="Remove"
                                                  data-confirm-danger>
                                                <?= \App\Support\Csrf::field() ?>
                                                <button class="btn-danger !py-1 !px-2.5 !text-xs font-semibold" title="Remove a bill entered by mistake">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

</div>

<!-- Floating Back to Top Button -->
<button type="button" id="floating-top-btn" class="floating-top-btn" aria-label="Back to navigation">
    <?= \App\Support\Icon::svg('arrow-up', 14) ?>
    <span>Top</span>
</button>

<!-- Duplicate Room Alert Dialog Popout -->
<dialog id="room-exists-dialog" class="dialog" aria-labelledby="room-exists-title" aria-modal="true">
    <div class="modal-card text-center relative" style="max-width: 24rem; margin: 0 auto;">
        <button type="button" class="absolute top-3 right-3 text-neutral-400 hover:text-neutral-600 transition-colors p-1 rounded-md" data-close-dialog aria-label="Close dialog">
            <?= \App\Support\Icon::svg('x', 16) ?>
        </button>
        <div class="modal-icon modal-icon-danger" style="margin-bottom: 0.75rem;">
            <?= \App\Support\Icon::svg('alert-triangle', 24) ?>
        </div>
        <h3 id="room-exists-title" class="modal-title text-base font-bold text-neutral-900">Room Already Exists</h3>
        <p id="room-exists-message" class="modal-text text-body-sm text-neutral-600 mb-3"></p>
        
        <div class="bg-amber-50 border border-amber-200/90 rounded-lg p-3 text-left mb-4 shadow-2xs">
            <div class="flex items-center gap-1.5 text-xs font-bold text-amber-900 mb-1">
                <?= \App\Support\Icon::svg('door-open', 14) ?>
                <span>Existing Unit Record</span>
            </div>
            <p id="room-exists-detail" class="text-caption font-mono text-amber-800 ml-5"></p>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn btn-primary w-full !py-2.5 !text-xs font-semibold" data-close-dialog id="room-exists-close-btn">
                Change Room Number
            </button>
        </div>
    </div>
</dialog>

<script>
/* ==========================================================================
   ROOMS PAGE ENHANCED INTERACTIVE SCRIPT
   Provides sticky subnav, mode switching, drawer management, live filters,
   and strict duplicate protection.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {

    /* ── 1. Navigation Tabs & View Mode (Scroll View vs Focus Tab) ── */
    const navTabs = document.querySelectorAll('.rooms-nav-tab');
    const sections = document.querySelectorAll('.rooms-section');
    const viewModeBtns = document.querySelectorAll('.view-mode-btn');
    let currentMode = 'scroll'; // 'scroll' or 'focus'

    function setActiveTab(targetId) {
        navTabs.forEach(t => {
            const isMatch = t.getAttribute('data-target') === targetId;
            t.classList.toggle('active', isMatch);
        });
    }

    function switchSection(targetId, smoothly = true) {
        if (targetId === 'all') {
            if (currentMode === 'focus') {
                setViewMode('scroll');
            }
            window.scrollTo({ top: 0, behavior: smoothly ? 'smooth' : 'auto' });
            setActiveTab('all');
            return;
        }

        if (currentMode === 'focus') {
            sections.forEach(sec => {
                if (sec.id === targetId) {
                    sec.classList.remove('focus-hidden');
                } else {
                    sec.classList.add('focus-hidden');
                }
            });
            setActiveTab(targetId);
            window.scrollTo({ top: 200, behavior: smoothly ? 'smooth' : 'auto' });
        } else {
            const el = document.getElementById(targetId);
            if (el) {
                el.scrollIntoView({ behavior: smoothly ? 'smooth' : 'auto', block: 'start' });
                setActiveTab(targetId);
            }
        }
    }

    function setViewMode(mode) {
        currentMode = mode;
        viewModeBtns.forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-mode') === mode);
        });

        if (mode === 'scroll') {
            sections.forEach(sec => sec.classList.remove('focus-hidden'));
        } else {
            // Find currently active tab or default to beds-directory
            const activeTab = document.querySelector('.rooms-nav-tab.active');
            let target = activeTab ? activeTab.getAttribute('data-target') : 'beds-directory';
            if (target === 'all') target = 'beds-directory';
            switchSection(target, false);
        }
    }

    viewModeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const mode = btn.getAttribute('data-mode');
            if (mode) setViewMode(mode);
        });
    });

    navTabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            const target = tab.getAttribute('data-target');
            if (target) {
                switchSection(target);
                if (history.replaceState && target !== 'all') {
                    history.replaceState(null, null, '#' + target);
                }
            }
        });
    });

    // Check deep link hash on page load
    const hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById(hash)) {
        setTimeout(() => {
            switchSection(hash, false);
        }, 80);
    }

    /* ── 2. Scroll Spy (Updates active tab while scrolling in scroll mode) ── */
    const floatingTopBtn = document.getElementById('floating-top-btn');

    window.addEventListener('scroll', () => {
        const scrollPos = window.scrollY || window.pageYOffset;

        // Floating top button visibility
        if (floatingTopBtn) {
            if (scrollPos > 400) {
                floatingTopBtn.classList.add('visible');
            } else {
                floatingTopBtn.classList.remove('visible');
            }
        }

        if (currentMode !== 'scroll') return;

        if (scrollPos < 300) {
            setActiveTab('all');
            return;
        }

        let currentSectionId = null;
        sections.forEach(sec => {
            const top = sec.offsetTop - 140;
            const height = sec.offsetHeight;
            if (scrollPos >= top && scrollPos < top + height) {
                currentSectionId = sec.id;
            }
        });

        if (currentSectionId) {
            setActiveTab(currentSectionId);
        }
    }, { passive: true });

    if (floatingTopBtn) {
        floatingTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* ── 3. Action Studio: Switcher between Add Room, Add Bed, Assign Boarder ── */
    const actionTabBtns = document.querySelectorAll('.action-tab-btn');
    const actionPanels = document.querySelectorAll('.action-panel-item');
    const actionLayoutBtns = document.querySelectorAll('.action-layout-btn');
    const actionContainer = document.getElementById('action-panels-container');
    const actionHeader = document.getElementById('action-studio-header');

    function switchActionTab(tabId) {
        actionTabBtns.forEach(btn => {
            const isMatch = btn.getAttribute('data-tab') === tabId;
            btn.classList.toggle('active', isMatch);
            btn.setAttribute('aria-selected', isMatch ? 'true' : 'false');
        });

        actionPanels.forEach(panel => {
            const isMatch = panel.id === `action-panel-${tabId}`;
            panel.classList.toggle('active', isMatch);
        });
    }

    actionTabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            if (tabId) switchActionTab(tabId);
        });
    });

    actionLayoutBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const layout = btn.getAttribute('data-layout');
            actionLayoutBtns.forEach(b => b.classList.toggle('active', b === btn));
            if (actionContainer) {
                if (layout === 'grid') {
                    actionContainer.className = 'action-panels-container mode-grid';
                } else {
                    actionContainer.className = 'action-panels-container mode-tabbed';
                }
            }
        });
    });

    // Banner quick jump triggers (Add Room, Add Bed, Assign Boarder)
    document.querySelectorAll('.quick-jump-action').forEach(btn => {
        btn.addEventListener('click', () => {
            const action = btn.getAttribute('data-action');
            switchSection('management-actions');
            if (action === 'room') {
                switchActionTab('add-room');
                const inp = document.getElementById('add-room-number');
                if (inp) inp.focus();
            } else if (action === 'bed') {
                switchActionTab('add-bed');
                const inp = document.getElementById('add-bed-room');
                if (inp) inp.focus();
            } else if (action === 'assign') {
                switchActionTab('assign-boarder');
                const inp = document.getElementById('rooms-assign-boarder');
                if (inp) inp.focus();
            }
        });
    });

    /* ── 4. Beds Directory Status Filter Pills ── */
    const bedsRows = document.querySelectorAll('#beds-directory-tbody .room-main-row');
    const filterBtns = document.querySelectorAll('.beds-filter-btn');

    let currentBedFilter = 'all';

    function applyBedFilter() {
        let visibleCount = 0;
        bedsRows.forEach(row => {
            const hasVacant = row.getAttribute('data-has-vacant') === '1';
            const hasOccupied = row.getAttribute('data-has-occupied') === '1';
            const rowId = row.getAttribute('data-id');
            const drawerRow = document.getElementById(`beds-row-${rowId}`);

            let matchesFilter = true;
            if (currentBedFilter === 'vacant') {
                matchesFilter = hasVacant;
            } else if (currentBedFilter === 'occupied') {
                matchesFilter = hasOccupied;
            }

            if (matchesFilter) {
                row.style.display = '';
                visibleCount++;
                if (currentBedFilter === 'occupied' && drawerRow) {
                    drawerRow.classList.remove('hidden');
                    row.classList.add('bg-neutral-50');
                    const toggleBtn = row.querySelector('.beds-toggle-btn');
                    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
                }
            } else {
                row.style.display = 'none';
                if (drawerRow) drawerRow.classList.add('hidden');
            }
        });

        const emptyRow = document.getElementById('beds-filter-empty');
        if (emptyRow) {
            emptyRow.classList.toggle('hidden', visibleCount > 0);
        }
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => {
                b.classList.remove('bg-white', 'text-neutral-900', 'shadow-2xs');
                b.classList.add('text-neutral-600');
            });
            btn.classList.add('bg-white', 'text-neutral-900', 'shadow-2xs');
            btn.classList.remove('text-neutral-600');
            currentBedFilter = btn.getAttribute('data-filter') || 'all';
            applyBedFilter();
        });
    });

    /* ── 5. Beds Toggle Drawer Logic ── */
    const bedsToggleBtns = document.querySelectorAll('.beds-toggle-btn');
    const roomMainRows = document.querySelectorAll('.room-main-row');
    const bedsDrawerRows = document.querySelectorAll('.beds-drawer-row');
    const toggleAllBtn = document.getElementById('toggle-all-beds-drawers');
    let allExpanded = false;

    function toggleDrawer(roomId) {
        const idNum = parseInt(roomId, 10);
        const drawerRow = document.getElementById(`beds-row-${idNum}`);
        const mainRow = document.getElementById(`room-row-${idNum}`);
        if (!drawerRow || !mainRow) return;

        const isCurrentlyHidden = drawerRow.classList.contains('hidden');
        drawerRow.classList.toggle('hidden', !isCurrentlyHidden);
        mainRow.classList.toggle('bg-neutral-50', isCurrentlyHidden);
        const btn = mainRow.querySelector('.beds-toggle-btn');
        if (btn) btn.setAttribute('aria-expanded', isCurrentlyHidden ? 'true' : 'false');
    }

    bedsToggleBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const roomId = btn.getAttribute('data-id');
            if (roomId) toggleDrawer(roomId);
        });
    });

    roomMainRows.forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target.closest('button, a, input, select, textarea, form, label')) return;
            const roomId = row.getAttribute('data-id');
            if (roomId) toggleDrawer(roomId);
        });
    });

    if (toggleAllBtn) {
        toggleAllBtn.addEventListener('click', () => {
            allExpanded = !allExpanded;
            bedsDrawerRows.forEach(drawer => {
                drawer.classList.toggle('hidden', !allExpanded);
            });
            bedsToggleBtns.forEach(btn => {
                btn.setAttribute('aria-expanded', allExpanded ? 'true' : 'false');
            });
            roomMainRows.forEach(row => {
                row.classList.toggle('bg-neutral-50', allExpanded);
            });
            toggleAllBtn.textContent = allExpanded ? 'Collapse All Drawers' : 'Expand All Drawers';
        });
    }

});

/* ── 6. Electricity: Show each resident's share before the bill is entered ── */
(function () {
    const room = document.getElementById('electricity-room');
    const amount = document.getElementById('electricity-amount');
    const out = document.getElementById('electricity-share');
    if (!room || !amount || !out) return;
    function update() {
        const n = parseInt((room.selectedOptions[0] || {dataset: {}}).dataset.residents || '0', 10);
        const total = parseFloat(amount.value);
        out.textContent = n > 0 && total > 0
            ? '₱' + (total / n).toFixed(2) + ' each for ' + n + ' resident' + (n === 1 ? '' : 's') + ', due with their next rent.'
            : "Each resident's share appears here.";
    }
    room.addEventListener('change', update);
    amount.addEventListener('input', update);
})();

/* ── 7. Batch Bed Label Live Preview ── */
(function () {
    const countInput = document.getElementById('add-bed-count');
    const labelInput = document.getElementById('add-bed-label');
    const previewEl  = document.getElementById('add-bed-preview');
    const previewTxt = document.getElementById('add-bed-preview-text');
    const submitBtn  = document.getElementById('add-bed-submit');

    function updatePreview() {
        if (!countInput || !labelInput) return;
        const count = Math.max(1, parseInt(countInput.value, 10) || 1);
        const label = labelInput.value.trim();

        if (submitBtn) {
            submitBtn.textContent = count > 1 ? `Add ${count} Beds` : 'Add Bed';
        }

        if (!label) {
            if (previewEl) previewEl.classList.add('hidden');
            return;
        }

        let generated = [];
        if (count === 1) {
            generated = [label];
        } else {
            const letterMatch = label.match(/^(?:(.*?[ \t\-_#])([A-Za-z])|([A-Za-z]))$/);
            const numberMatch = label.match(/^(.*?[ \t\-_#]?)(\d+)$/);

            if (letterMatch) {
                const prefix    = letterMatch[3] ? '' : letterMatch[1];
                const startChar = letterMatch[3] ? letterMatch[3] : letterMatch[2];
                const startCode = startChar.charCodeAt(0);
                const isUpper   = startChar === startChar.toUpperCase();
                const maxCode   = isUpper ? 90 : 122;
                for (let i = 0; i < count; i++) {
                    const code = startCode + i;
                    generated.push(code <= maxCode ? prefix + String.fromCharCode(code) : prefix + (i + 1));
                }
            } else if (numberMatch) {
                const prefix = numberMatch[1];
                const startNum = parseInt(numberMatch[2], 10);
                for (let i = 0; i < count; i++) {
                    generated.push(prefix + (startNum + i));
                }
            } else {
                const prefix = label.trim() + ' ';
                for (let i = 0; i < count; i++) {
                    generated.push(prefix + (i + 1));
                }
            }
        }

        if (previewEl && previewTxt) {
            previewEl.classList.remove('hidden');
            if (generated.length <= 5) {
                previewTxt.textContent = generated.join(', ');
            } else {
                previewTxt.textContent = generated.slice(0, 3).join(', ') + ', ... ' + generated[generated.length - 1] + ` (${generated.length} beds total)`;
            }
        }
    }

    if (countInput && labelInput) {
        countInput.addEventListener('input', updatePreview);
        labelInput.addEventListener('input', updatePreview);
        countInput.addEventListener('change', updatePreview);
    }
})();

/* ── 8. Room Type sets Capacity and Rent Autofill & Readonly ── */
document.querySelectorAll('select[data-room-type]').forEach((select) => {
    const scope = select.form ?? select.closest('form');
    const field = (name) => select.getAttribute('form')
        ? document.querySelector(`[form="${select.getAttribute('form')}"][name="${name}"]`)
        : scope.querySelector(`[name="${name}"]`);
    const capacity = field('capacity'), rate = field('base_price');
    const sync = () => {
        const opt = select.selectedOptions[0];
        const typed = Boolean(opt && opt.value);
        if (typed) { capacity.value = opt.dataset.capacity; rate.value = opt.dataset.rate; }
        capacity.readOnly = rate.readOnly = typed;
        capacity.title = rate.title = typed ? 'Set by the room type' : '';
    };
    select.addEventListener('change', sync);
    sync();
});

/* ── 9. Room Configurations: Interactive Group By & Sort By ── */
(() => {
    const groupSelect = document.getElementById('room-group-by');
    const sortSelect = document.getElementById('room-sort-by');
    const tbody = document.getElementById('rooms-config-tbody');
    if (!groupSelect || !sortSelect || !tbody) return;

    function applySortAndGroup() {
        const groupBy = groupSelect.value;
        const sortBy = sortSelect.value;

        const rows = Array.from(tbody.querySelectorAll('.room-config-row'));
        if (!rows.length) return;

        rows.sort((a, b) => {
            const numA = (a.querySelector('input[name="room_number"]')?.value || a.dataset.roomNumber || '').trim();
            const numB = (b.querySelector('input[name="room_number"]')?.value || b.dataset.roomNumber || '').trim();
            const floorA = (a.querySelector('input[name="floor"]')?.value || a.dataset.floor || '').trim();
            const floorB = (b.querySelector('input[name="floor"]')?.value || b.dataset.floor || '').trim();
            const typeA = (a.querySelector('select[name="room_type_id"] option:checked')?.textContent || a.dataset.roomType || '').trim();
            const typeB = (b.querySelector('select[name="room_type_id"] option:checked')?.textContent || b.dataset.roomType || '').trim();
            const capA = parseInt(a.querySelector('input[name="capacity"]')?.value || a.dataset.capacity || '0', 10);
            const capB = parseInt(b.querySelector('input[name="capacity"]')?.value || b.dataset.capacity || '0', 10);
            const priceA = parseFloat(a.querySelector('input[name="base_price"]')?.value || a.dataset.price || '0');
            const priceB = parseFloat(b.querySelector('input[name="base_price"]')?.value || b.dataset.price || '0');

            const cmpRoomAsc = numA.localeCompare(numB, undefined, { numeric: true, sensitivity: 'base' });
            const cmpRoomDesc = numB.localeCompare(numA, undefined, { numeric: true, sensitivity: 'base' });

            switch (sortBy) {
                case 'room_asc':
                    return cmpRoomAsc;
                case 'room_desc':
                    return cmpRoomDesc;
                case 'floor_asc': {
                    const cmp = floorA.localeCompare(floorB, undefined, { numeric: true, sensitivity: 'base' });
                    return cmp !== 0 ? cmp : cmpRoomAsc;
                }
                case 'floor_desc': {
                    const cmp = floorB.localeCompare(floorA, undefined, { numeric: true, sensitivity: 'base' });
                    return cmp !== 0 ? cmp : cmpRoomDesc;
                }
                case 'type_asc': {
                    const cmp = typeA.localeCompare(typeB);
                    return cmp !== 0 ? cmp : cmpRoomAsc;
                }
                case 'capacity_desc':
                    return capB !== capA ? capB - capA : cmpRoomDesc;
                case 'capacity_asc':
                    return capA !== capB ? capA - capB : cmpRoomAsc;
                case 'price_desc':
                    return priceB !== priceA ? priceB - priceA : cmpRoomDesc;
                case 'price_asc':
                    return priceA !== priceB ? priceA - priceB : cmpRoomAsc;
                default:
                    return cmpRoomDesc;
            }
        });

        tbody.innerHTML = '';

        if (groupBy === 'none') {
            rows.forEach(r => tbody.appendChild(r));
        } else {
            const groups = new Map();
            rows.forEach(r => {
                let key = '';
                if (groupBy === 'floor') {
                    const fl = (r.querySelector('input[name="floor"]')?.value || r.dataset.floor || '').trim();
                    key = fl ? `Floor ${fl}` : 'Unassigned Floor';
                } else if (groupBy === 'type') {
                    const t = (r.querySelector('select[name="room_type_id"] option:checked')?.textContent || r.dataset.roomType || '').trim();
                    key = (t && t !== '— None —') ? t : 'No Room Type';
                } else if (groupBy === 'capacity') {
                    const cap = parseInt(r.querySelector('input[name="capacity"]')?.value || r.dataset.capacity || '0', 10);
                    key = cap === 1 ? '1 Bed (Solo)' : `${cap} Beds Capacity`;
                }
                if (!groups.has(key)) groups.set(key, []);
                groups.get(key).push(r);
            });

            let groupEntries = Array.from(groups.entries());
            if (groupBy === 'floor') {
                const isDesc = sortBy.startsWith('floor_desc');
                groupEntries.sort((a, b) => {
                    const cmp = a[0].localeCompare(b[0], undefined, { numeric: true, sensitivity: 'base' });
                    return isDesc ? -cmp : cmp;
                });
            } else if (groupBy === 'capacity') {
                const isDesc = sortBy.startsWith('capacity_desc');
                groupEntries.sort((a, b) => {
                    const capA = parseInt(a[0], 10) || 0;
                    const capB = parseInt(b[0], 10) || 0;
                    return isDesc ? capB - capA : capA - capB;
                });
            }

            groupEntries.forEach(([groupName, groupRows]) => {
                const totalBeds = groupRows.reduce((sum, r) => sum + (parseInt(r.querySelector('input[name="capacity"]')?.value || r.dataset.capacity || '0', 10) || 0), 0);
                const headerTr = document.createElement('tr');
                headerTr.className = 'room-group-header-row bg-neutral-100/90 border-y border-neutral-200/80';
                headerTr.innerHTML = `
                    <td colspan="6" class="py-2.5 px-4 text-xs font-semibold text-neutral-800">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-accent"></span>
                                <span class="font-bold uppercase tracking-wider text-neutral-900">${groupName}</span>
                            </span>
                            <span class="text-caption font-semibold text-neutral-500 bg-white px-2.5 py-0.5 rounded-full border border-neutral-200 shadow-2xs">
                                ${groupRows.length} room${groupRows.length !== 1 ? 's' : ''} · ${totalBeds} bed${totalBeds !== 1 ? 's' : ''} total
                            </span>
                        </div>
                    </td>
                `;
                tbody.appendChild(headerTr);
                groupRows.forEach(r => tbody.appendChild(r));
            });
        }

        const url = new URL(window.location);
        url.searchParams.set('sort', sortBy);
        url.searchParams.set('group_by', groupBy);
        window.history.replaceState({}, '', url);

        try {
            localStorage.setItem('admin_rooms_sort', sortBy);
            localStorage.setItem('admin_rooms_group', groupBy);
        } catch (e) {}
    }

    groupSelect.addEventListener('change', applySortAndGroup);
    sortSelect.addEventListener('change', applySortAndGroup);

    const urlParams = new URLSearchParams(window.location.search);
    const initialSort = urlParams.get('sort') || localStorage.getItem('admin_rooms_sort');
    const initialGroup = urlParams.get('group_by') || localStorage.getItem('admin_rooms_group');
    if (initialSort && sortSelect.querySelector(`option[value="${initialSort}"]`)) {
        sortSelect.value = initialSort;
    }
    if (initialGroup && groupSelect.querySelector(`option[value="${initialGroup}"]`)) {
        groupSelect.value = initialGroup;
    }
    if (sortSelect.value !== 'room_desc' || groupSelect.value !== 'none') {
        applySortAndGroup();
    }
})();

/* ── 10. Add Room: Strict Integer & Duplicate Room Popout Dialog ── */
(() => {
    const form = document.getElementById('add-room-form');
    const roomInput = document.getElementById('add-room-number');
    const floorInput = document.getElementById('add-room-floor');
    const dialog = document.getElementById('room-exists-dialog');
    const dialogMessage = document.getElementById('room-exists-message');
    const dialogDetail = document.getElementById('room-exists-detail');
    const feedback = document.getElementById('room-number-feedback');
    const closeBtn = document.getElementById('room-exists-close-btn');

    if (!form || !roomInput || !dialog) return;

    // Existing rooms configured in the system
    const existingRooms = <?= json_encode(array_map(fn($r) => [
        'id'       => (int) $r['id'],
        'number'   => (string) $r['room_number'],
        'floor'    => (string) ($r['floor'] ?? ''),
        'typeName' => (string) ($r['room_type_name'] ?? 'Custom Unit'),
        'capacity' => (int) $r['capacity'],
    ], $rooms)) ?>;

    // Strict integer keystroke filter (block '.', 'e', 'E', '+', '-', ',')
    [roomInput, floorInput].forEach((inp) => {
        if (!inp) return;
        inp.addEventListener('keydown', (e) => {
            if (['.', 'e', 'E', '+', '-', ','].includes(e.key)) {
                e.preventDefault();
            }
        });
        inp.addEventListener('input', () => {
            inp.value = inp.value.replace(/[^0-9]/g, '');
            checkLiveDuplicate();
        });
    });

    // Auto-infer floor from 3-digit room number (e.g. 101 -> Floor 1, 201 -> Floor 2)
    roomInput.addEventListener('blur', () => {
        const val = roomInput.value.trim();
        if (floorInput && !floorInput.value && /^[1-9][0-9]{2}$/.test(val)) {
            floorInput.value = val[0];
            checkLiveDuplicate();
        }
    });

    if (floorInput) {
        floorInput.addEventListener('input', checkLiveDuplicate);
    }

    function findExisting(num) {
        if (!num) return null;
        const trimmed = String(num).trim();
        return existingRooms.find(r => 
            String(r.number).trim() === trimmed || 
            (parseInt(r.number, 10) === parseInt(trimmed, 10) && !isNaN(parseInt(trimmed, 10)))
        ) || null;
    }

    function checkLiveDuplicate() {
        const num = roomInput.value.trim();
        const currentFloor = floorInput ? floorInput.value.trim() : '';

        if (!num) {
            if (feedback) feedback.classList.add('hidden');
            roomInput.classList.remove('border-error-500', 'text-error-700', 'focus:ring-error-500');
            return null;
        }

        const match = findExisting(num);
        if (match) {
            roomInput.classList.add('border-error-500', 'text-error-700', 'focus:ring-error-500');
            if (feedback) {
                feedback.classList.remove('hidden');
                feedback.className = 'text-xs mt-1 font-medium text-error-600';
                const matchFloor = match.floor ? String(match.floor).trim() : '';
                const matchFloorText = matchFloor ? `Floor ${matchFloor}` : 'another floor';

                if (currentFloor && matchFloor && String(currentFloor) !== matchFloor) {
                    const suggestedNum = `${currentFloor}01`;
                    feedback.textContent = `Room ${match.number} is already taken on ${matchFloorText}. Room numbers are unique across all floors (e.g. use ${suggestedNum} for Floor ${currentFloor}).`;
                } else if (currentFloor && matchFloor && String(currentFloor) === matchFloor) {
                    feedback.textContent = `Room ${match.number} on ${matchFloorText} already exists.`;
                } else if (matchFloor) {
                    feedback.textContent = `Room ${match.number} already exists on ${matchFloorText}. Room numbers must be unique across all floors.`;
                } else {
                    feedback.textContent = `Room ${match.number} already exists in the system.`;
                }
            }
            return match;
        } else {
            roomInput.classList.remove('border-error-500', 'text-error-700', 'focus:ring-error-500');
            if (feedback) {
                feedback.classList.add('hidden');
            }
            return null;
        }
    }

    function refocusRoomInput() {
        setTimeout(() => {
            roomInput.focus();
            roomInput.select();
        }, 50);
    }

    // Intercept submit: Cancel attempt if room already exists and show popout modal
    form.addEventListener('submit', (e) => {
        const roomVal = roomInput.value.trim();
        const floorVal = floorInput ? floorInput.value.trim() : '';

        if (!roomVal) {
            e.preventDefault();
            roomInput.focus();
            return;
        }

        const match = findExisting(roomVal);
        if (match) {
            e.preventDefault();

            const matchFloor = match.floor ? String(match.floor).trim() : '';
            const matchFloorText = matchFloor ? `Floor ${matchFloor}` : 'an unassigned floor';

            let msg = '';
            if (floorVal && matchFloor && String(floorVal) !== matchFloor) {
                const suggestedNum = `${floorVal}01`;
                msg = `Room ${roomVal} is already taken on ${matchFloorText}. Room numbers must be unique across the entire building, so Floor ${floorVal} cannot reuse Room ${roomVal}. Please use a unique number like ${suggestedNum}.`;
            } else if (floorVal && matchFloor && String(floorVal) === matchFloor) {
                msg = `Room ${roomVal} on Floor ${floorVal} already exists in the system. You cannot create a duplicate room unit on the same floor.`;
            } else if (matchFloor) {
                msg = `Room ${roomVal} already exists on ${matchFloorText}. Room numbers must be unique across all floors.`;
            } else {
                msg = `Room ${roomVal} already exists in the system. Please specify a different room number.`;
            }

            dialogMessage.textContent = msg;
            dialogDetail.textContent = `Room ${match.number} · ${matchFloorText} · ${match.typeName} (${match.capacity} bed${match.capacity !== 1 ? 's' : ''})`;

            dialog.showModal();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            dialog.close();
            refocusRoomInput();
        });
    }

    dialog.querySelectorAll('[data-close-dialog]').forEach(btn => {
        btn.addEventListener('click', () => {
            dialog.close();
            refocusRoomInput();
        });
    });

    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) {
            dialog.close();
            refocusRoomInput();
        }
    });

    dialog.addEventListener('close', refocusRoomInput);

    <?php if ($error && str_contains(strtolower($error), 'already exists')): ?>
    dialogMessage.textContent = <?= json_encode($error) ?>;
    dialogDetail.textContent = 'Please choose a different room number to proceed.';
    dialog.showModal();
    <?php endif; ?>
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
