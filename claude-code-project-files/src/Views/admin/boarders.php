<?php
$pageTitle = 'Boarders';
ob_start();
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

// Filter vacant beds and available rooms
$vacantBeds = array_values(array_filter($beds ?? [], fn($b) => ($b['status'] ?? '') === 'vacant'));

// Map vacant beds by room_id
$vacantBedsByRoom = [];
foreach ($vacantBeds as $b) {
    $vacantBedsByRoom[(int) $b['room_id']][] = $b;
}

// Only rooms that have at least one vacant bed
$availableRooms = array_values(array_filter($rooms ?? [], fn($r) => !empty($vacantBedsByRoom[(int) $r['id']])));

// Overview statistics
$totalBoarders      = count($boarders);
$activeBoarders     = count(array_filter($boarders, fn($b) => ($b['status'] ?? '') === 'active'));
$pendingCount       = count(array_filter($boarders, fn($b) => ($b['status'] ?? '') === 'pending'));
$onNoticeCount      = count(array_filter($boarders, fn($b) => ($b['status'] ?? '') === 'on_notice'));
$movedOutCount      = count(array_filter($boarders, fn($b) => ($b['status'] ?? '') === 'moved_out'));
$pendingBoarders    = $pendingCount + $onNoticeCount;
$unassignedBoarders = count(array_filter($boarders, fn($b) => empty($b['bed_id'])));
$vacantBedsCount    = count($vacantBeds);
$totalBalancesOwed  = array_sum(array_column($boarders, 'outstanding_balance'));
?>

<style>
/* ==========================================================================
   BOARDERS PAGE ENHANCED RESPONSIVE STYLES
   Fixes layout cramping, truncations, overlapping elements, column collisions,
   and provides consistent spacing, padding, and responsive behaviors.
   ========================================================================== */

.boarders-page-wrapper {
    max-width: 82rem;
    margin-left: auto;
    margin-right: auto;
    padding: 1.5rem 1rem 3rem;
}
@media (min-width: 640px) {
    .boarders-page-wrapper {
        padding: 1.75rem 1.5rem 3.5rem;
    }
}
@media (min-width: 1024px) {
    .boarders-page-wrapper {
        padding: 2rem 2rem 4rem;
    }
}

/* Headings safety override against layout.php white-space: nowrap */
.boarders-page-wrapper h1,
.boarders-page-wrapper h2,
.boarders-page-wrapper h3,
.boarders-page-wrapper h4,
.boarders-page-wrapper h5,
.boarders-page-wrapper h6 {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
}

/* Page Banner */
.boarders-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 0.875rem;
    padding: 1.35rem 1.75rem;
    color: #ffffff;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.08);
    margin-bottom: 1.5rem;
}
@media (min-width: 640px) {
    .boarders-banner {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.boarders-banner h1 {
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: #ffffff !important;
    line-height: 1.25;
    margin: 0;
}
.boarders-banner p {
    font-size: 0.8125rem;
    color: #94a3b8;
    margin-top: 0.25rem;
}
.boarders-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #86efac;
    padding: 0.35rem 0.85rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    align-self: flex-start;
}
@media (min-width: 640px) {
    .boarders-status-pill {
        align-self: center;
    }
}

/* KPI Cards Grid */
.boarders-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.875rem;
    margin-bottom: 1.75rem;
}
@media (min-width: 640px) {
    .boarders-kpi-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }
}
@media (min-width: 1024px) {
    .boarders-kpi-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 1rem;
    }
}
.kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 1.125rem 1.125rem 1rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-width: 0;
    transition: transform 180ms ease, box-shadow 180ms ease;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.08);
}
.kpi-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
}
.kpi-label {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kpi-icon {
    font-size: 0.95rem;
    line-height: 1;
    opacity: 0.8;
}
.kpi-value {
    font-size: 1.625rem;
    font-weight: 800;
    line-height: 1.2;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    margin-top: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kpi-subtext {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Card 5 responsive span */
@media (max-width: 639px) {
    .kpi-span-mobile-2 {
        grid-column: span 2;
    }
}

/* Filter & Controls Container */
.filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 0.875rem 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}
.filter-layout {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
@media (min-width: 860px) {
    .filter-layout {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.filter-tabs-wrapper {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.375rem;
}
.filter-tab-btn {
    padding: 0.4rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 0.5rem;
    font-weight: 600;
    transition: all 140ms ease;
    cursor: pointer;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
}
.filter-tab-btn.is-active {
    background-color: #1e3a8a;
    color: #ffffff;
    border-color: #1e3a8a;
    box-shadow: 0 1px 2px rgba(30, 58, 138, 0.15);
}
.filter-tab-btn:not(.is-active) {
    background-color: #f8fafc;
    color: #475569;
    border-color: #e2e8f0;
}
.filter-tab-btn:not(.is-active):hover {
    background-color: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
}

/* Search input with verified icon padding */
.search-wrapper {
    position: relative;
    width: 100%;
}
@media (min-width: 860px) {
    .search-wrapper {
        width: 19rem;
        flex-shrink: 0;
    }
}
.search-input {
    width: 100% !important;
    height: 2.25rem !important;
    padding-left: 2.35rem !important;
    padding-right: 2.25rem !important;
    font-size: 0.8125rem !important;
    border-radius: 0.5rem !important;
    border: 1px solid #cbd5e1 !important;
    background-color: #ffffff !important;
    color: #0f172a !important;
    outline: none !important;
    box-sizing: border-box !important;
    transition: border-color 150ms ease, box-shadow 150ms ease;
}
.search-input:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
}
.search-icon-fixed {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    font-size: 0.85rem;
    color: #94a3b8;
    line-height: 1;
}
.search-clear-fixed {
    position: absolute;
    right: 0.65rem;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    padding: 0.25rem;
    font-size: 0.75rem;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
    border-radius: 0.25rem;
    transition: color 150ms;
}
.search-clear-fixed:hover {
    color: #334155;
}

/* Table Card & Scroll */
.boarders-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 0.875rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    margin-bottom: 2rem;
}
.boarders-table-scroll {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.boarders-table {
    width: 100%;
    min-width: 860px;
    border-collapse: collapse;
    table-layout: fixed;
    text-align: left;
}
.boarders-table thead tr {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}
.boarders-table th {
    padding: 0.75rem 1rem !important;
    font-size: 0.6875rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    color: #64748b !important;
    background: #f8fafc;
    vertical-align: middle;
}
.boarders-table td {
    padding: 0.75rem 1rem !important;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
.boarders-table tbody tr.boarder-main-row {
    transition: background-color 130ms ease;
    cursor: pointer;
}
.boarders-table tbody tr.boarder-main-row:hover {
    background-color: #f8fafc;
}

/* Specific Column Sizing & Behaviors */
.th-id, .td-id {
    width: 70px;
    min-width: 70px;
}
.th-resident, .td-resident {
    width: 28%;
    min-width: 230px;
    white-space: normal !important;
    overflow: visible !important;
}
.th-bed, .td-bed {
    width: 20%;
    min-width: 160px;
}
.th-status, .td-status {
    width: 13%;
    min-width: 110px;
}
.th-balance, .td-balance {
    width: 14%;
    min-width: 120px;
}
.th-actions, .td-actions {
    width: 215px;
    min-width: 215px;
    text-align: right;
    overflow: visible !important;
}

/* Action Buttons Cluster */
.boarders-actions-cluster {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    flex-wrap: nowrap;
    white-space: nowrap;
}
.tbl-btn {
    height: 1.85rem;
    padding: 0 0.65rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 0.375rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    cursor: pointer;
    transition: all 130ms ease;
    border: 1px solid transparent;
    text-decoration: none;
    box-sizing: border-box;
}
.tbl-btn-profile {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
}
.tbl-btn-profile:hover {
    background: #dbeafe;
    color: #1e40af;
    border-color: #93c5fd;
}
.tbl-btn-edit {
    background: #ffffff;
    color: #334155;
    border-color: #cbd5e1;
}
.tbl-btn-edit:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #94a3b8;
}
.tbl-btn-delete {
    background: #fef2f2;
    color: #b91c1c;
    border-color: #fecaca;
}
.tbl-btn-delete:hover {
    background: #fee2e2;
    color: #991b1b;
    border-color: #f87171;
}

/* Edit Drawer Styling */
.edit-drawer-cell {
    padding: 0 !important;
    background-color: #f8fafc;
    white-space: normal !important;
    overflow: visible !important;
}
.edit-drawer-box {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-left: 4px solid #2563eb;
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    margin: 0.75rem 1rem 1rem;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.06);
}
.edit-drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.75rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #f1f5f9;
}
.edit-grid-4 {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 0.875rem;
    margin-bottom: 0.875rem;
}
@media (min-width: 640px) {
    .edit-grid-4 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 1024px) {
    .edit-grid-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
.edit-grid-2 {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 0.875rem;
    margin-bottom: 1.25rem;
}
@media (min-width: 640px) {
    .edit-grid-2 {
        grid-template-columns: 1fr 2fr;
    }
}

/* Management Actions Bottom Grid */
.boarders-management-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1.25rem;
    align-items: stretch;
}
@media (min-width: 768px) and (max-width: 1023px) {
    .boarders-management-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .guide-card-col {
        grid-column: span 2;
    }
}
@media (min-width: 1024px) {
    .boarders-management-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
.mgmt-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-top: 3px solid #2563eb;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    padding: 1.35rem 1.25rem 1.25rem;
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.mgmt-card.accent-success {
    border-top-color: #16a34a;
}
.mgmt-card.accent-neutral {
    border-top-color: #64748b;
}
.mgmt-card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.125rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
}
.mgmt-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
}
.mgmt-card-body {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    flex: 1;
}
.mgmt-btn-wrap {
    margin-top: auto;
    padding-top: 1rem;
}

/* Form Input Control Standards */
.field-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.35rem;
}
.field-input {
    display: block;
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    color: #0f172a;
    background-color: #ffffff;
    box-sizing: border-box;
    transition: border-color 140ms ease, box-shadow 140ms ease;
}
.field-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.field-select {
    display: block;
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    color: #0f172a;
    background-color: #ffffff;
    box-sizing: border-box;
    transition: border-color 140ms ease, box-shadow 140ms ease;
}
.field-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* Password field with absolute toggle button */
.password-relative-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}
.password-input-with-toggle {
    padding-right: 2.85rem !important;
}
.password-eye-toggle {
    position: absolute;
    right: 0.45rem;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    padding: 0.35rem;
    color: #94a3b8;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.35rem;
    transition: color 150ms ease;
}
.password-eye-toggle:hover {
    color: #334155;
}

/* Assign notice box */
.assign-notice-box {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    padding: 0.75rem 0.85rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    line-height: 1.45;
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
}

/* Operations Guide Cards */
.guide-card-stack {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.guide-item-box {
    padding: 0.75rem 0.85rem;
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    font-size: 0.775rem;
    line-height: 1.5;
    color: #475569;
}
.guide-item-title {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.2rem;
    font-size: 0.8rem;
}

/* Section Header Typography */
.section-headline-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 0.875rem;
    padding-bottom: 0.65rem;
    border-bottom: 1.5px solid #e2e8f0;
}
@media (min-width: 640px) {
    .section-headline-wrap {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.section-headline-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
    margin: 0;
}
.section-headline-subtitle {
    font-size: 0.8125rem;
    color: #64748b;
    margin-top: 0.15rem;
}
.count-badge-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
    background-color: #f1f5f9;
    padding: 0.3rem 0.75rem;
    border-radius: 9999px;
    align-self: flex-start;
}
@media (min-width: 640px) {
    .count-badge-pill {
        align-self: center;
    }
}
</style>

<div class="boarders-page-wrapper space-y-6">

    <!-- Page Banner -->
    <div class="boarders-banner">
        <div>
            <h1>Boarders</h1>
            <p>Resident directory, bed allocations &amp; account lifecycle management</p>
        </div>
        <span class="boarders-status-pill">
            <span style="width:0.45rem; height:0.45rem; border-radius:50%; background:#4ade80; display:inline-block;"></span>
            Active Directory
        </span>
    </div>

    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3.5 border border-error-500 flex items-center gap-2.5" role="alert">
            <span style="font-size:1.1rem; line-height:1;">⚠</span>
            <span class="font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-lg p-3.5 border border-emerald-300 flex items-center gap-2.5" role="alert">
            <span style="font-size:1.1rem; line-height:1;">✓</span>
            <span class="font-medium"><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards (5 Cards) -->
    <div class="boarders-kpi-grid">
        <div class="reveal-card kpi-card metric-accent-primary">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Residents</span>
                <span class="kpi-icon">👥</span>
            </div>
            <div class="kpi-value"><?= $totalBoarders ?></div>
            <div class="kpi-subtext">
                <?php if ($unassignedBoarders > 0): ?>
                    <span class="text-amber-600 font-semibold">⚠ <?= $unassignedBoarders ?> unassigned</span>
                <?php else: ?>
                    <span class="text-neutral-400">✓ All assigned to beds</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="reveal-card kpi-card metric-accent-success">
            <div class="kpi-card-header">
                <span class="kpi-label">Active Residents</span>
                <span class="kpi-icon">🟢</span>
            </div>
            <div class="kpi-value"><?= $activeBoarders ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Current active boarders</span>
            </div>
        </div>

        <div class="reveal-card kpi-card metric-accent-warning">
            <div class="kpi-card-header">
                <span class="kpi-label">Pending / Notice</span>
                <span class="kpi-icon">⏳</span>
            </div>
            <div class="kpi-value <?= $pendingBoarders > 0 ? 'text-amber-600' : 'text-neutral-900' ?>">
                <?= $pendingBoarders ?>
            </div>
            <div class="kpi-subtext">
                <span class="text-neutral-400"><?= $pendingCount ?> pending &bull; <?= $onNoticeCount ?> notice</span>
            </div>
        </div>

        <div class="reveal-card kpi-card metric-accent-info">
            <div class="kpi-card-header">
                <span class="kpi-label">Vacant Beds</span>
                <span class="kpi-icon">🛏️</span>
            </div>
            <div class="kpi-value <?= $vacantBedsCount === 0 ? 'text-error-600' : 'text-neutral-900' ?>">
                <?= $vacantBedsCount ?>
            </div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Ready for occupancy</span>
            </div>
        </div>

        <div class="reveal-card kpi-card metric-accent-warning kpi-span-mobile-2">
            <div class="kpi-card-header">
                <span class="kpi-label">Balances Owed</span>
                <span class="kpi-icon">₱</span>
            </div>
            <div class="kpi-value <?= $totalBalancesOwed > 0 ? 'text-amber-700' : 'text-emerald-700' ?>">
                ₱<?= number_format($totalBalancesOwed, 2) ?>
            </div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Cached total (synced)</span>
            </div>
        </div>
    </div>

    <!-- Boarders Directory Section -->
    <div>
        <div class="section-headline-wrap">
            <div>
                <h2 class="section-headline-title">Resident Directory</h2>
                <p class="section-headline-subtitle">Click a resident's name or Profile button to inspect full ledger, penalties, and history.</p>
            </div>
            <div class="count-badge-pill">
                <span id="filtered-count"><?= $totalBoarders ?></span> of <?= $totalBoarders ?> resident<?= $totalBoarders !== 1 ? 's' : '' ?>
            </div>
        </div>

        <!-- Filter Tabs & Search Controls -->
        <div class="filter-card">
            <div class="filter-layout">
                <!-- Status Filter Tabs -->
                <div class="filter-tabs-wrapper" id="status-filter-tabs">
                    <button type="button" data-filter="all" class="filter-tab filter-tab-btn is-active">
                        All (<?= $totalBoarders ?>)
                    </button>
                    <button type="button" data-filter="active" class="filter-tab filter-tab-btn">
                        Active (<?= $activeBoarders ?>)
                    </button>
                    <button type="button" data-filter="pending" class="filter-tab filter-tab-btn">
                        Pending (<?= $pendingCount ?>)
                    </button>
                    <button type="button" data-filter="on_notice" class="filter-tab filter-tab-btn">
                        On Notice (<?= $onNoticeCount ?>)
                    </button>
                    <button type="button" data-filter="moved_out" class="filter-tab filter-tab-btn">
                        Moved Out (<?= $movedOutCount ?>)
                    </button>
                    <a href="<?= !empty($showArchived) ? '/admin/boarders' : '/admin/boarders?archived=1' ?>" class="filter-tab">
                        <?= !empty($showArchived) ? '&larr; Current residents' : 'Show archived' ?>
                    </a>
                </div>

                <!-- Live Search Box with Verified Padded Field -->
                <div class="search-wrapper">
                    <span class="search-icon-fixed">🔍</span>
                    <input type="text" id="boarder-search" placeholder="Search by name, email, room, bed..."
                           class="search-input" autocomplete="off">
                    <button type="button" id="clear-search-btn" class="hidden search-clear-fixed" title="Clear search">✕</button>
                </div>
            </div>
        </div>

        <!-- Responsive Table Container -->
        <div class="boarders-table-card">
            <div class="boarders-table-scroll">
                <table class="boarders-table" id="boarders-table">
                    <thead>
                        <tr>
                            <th class="th-id">ID</th>
                            <th class="th-resident">Resident</th>
                            <th class="th-bed">Assigned Bed</th>
                            <th class="th-status">Status</th>
                            <th class="th-balance">Balance Owed</th>
                            <th class="th-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($boarders as $b): ?>
                        <?php
                        $status = $b['status'] ?? 'pending';
                        $badgeClass = match($status) {
                            'active'    => 'badge-success',
                            'pending'   => 'badge-warning',
                            'on_notice' => 'badge-warning',
                            'moved_out' => 'badge-neutral',
                            default     => 'badge-neutral',
                        };
                        ?>
                        <!-- Main Boarder Row -->
                        <tr class="boarder-main-row"
                            id="boarder-row-<?= (int) $b['user_id'] ?>"
                            data-id="<?= (int) $b['user_id'] ?>"
                            data-status="<?= htmlspecialchars($status) ?>"
                            data-search="<?= htmlspecialchars(strtolower($b['name'] . ' ' . $b['email'] . ' ' . ($b['room_number'] ?? '') . ' ' . ($b['bed_label'] ?? ''))) ?>"
                            title="Click row to open/close details">
                            <td class="td-id">
                                <span class="id-tag">#<?= (int) $b['user_id'] ?></span>
                            </td>
                            <td class="td-resident">
                                <a href="/admin/boarders/<?= (int) $b['user_id'] ?>"
                                   class="font-bold text-primary-700 hover:text-primary-900 hover:underline text-sm inline-flex items-center gap-1"
                                   title="View full resident profile">
                                    <?= htmlspecialchars($b['name']) ?>
                                    <span class="text-[11px] text-neutral-400">↗</span>
                                </a>
                                <div class="text-caption text-neutral-500 font-normal truncate" title="<?= htmlspecialchars($b['email']) ?>">
                                    <?= htmlspecialchars($b['email']) ?>
                                </div>
                                <?php if (!empty($b['contact_number'])): ?>
                                    <div class="text-[11px] text-neutral-400 mt-0.5">
                                        📞 <?= htmlspecialchars($b['contact_number']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="td-bed">
                                <?php if (!empty($b['bed_id'])): ?>
                                    <span class="badge badge-info font-medium">
                                        <?= htmlspecialchars(($b['room_number'] ?? 'Room') . ' / ' . ($b['bed_label'] ?? 'Bed')) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-status">
                                <span class="badge <?= $badgeClass ?> capitalize">
                                    <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
                                </span>
                            </td>
                            <td class="td-balance">
                                <?php
                                $bal = (float) ($b['outstanding_balance'] ?? 0.0);
                                $balBadge = $bal <= 0 ? 'badge-success' : ($bal > 3500 ? 'badge-error' : 'badge-warning');
                                ?>
                                <span class="badge <?= $balBadge ?> font-semibold" style="font-variant-numeric: tabular-nums;">
                                    ₱<?= number_format($bal, 2) ?>
                                </span>
                            </td>
                            <td class="td-actions">
                                <div class="boarders-actions-cluster">
                                    <a href="/admin/boarders/<?= (int) $b['user_id'] ?>"
                                       class="tbl-btn tbl-btn-profile"
                                       title="View full resident ledger &amp; profile">
                                        Profile
                                    </a>
                                    <button type="button"
                                            class="tbl-btn tbl-btn-edit edit-toggle-btn cursor-pointer"
                                            data-id="<?= (int) $b['user_id'] ?>"
                                            title="Edit basic info &amp; status">
                                        Edit
                                    </button>
                                    <?php if (($b['account_status'] ?? '') === 'archived'): ?>
                                    <form method="post" action="/admin/boarders/<?= (int) $b['user_id'] ?>/restore" class="inline">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button type="submit" class="tbl-btn tbl-btn-edit cursor-pointer" title="Allow this resident to log in again">Restore</button>
                                    </form>
                                    <?php else: ?>
                                    <button type="button"
                                            class="tbl-btn tbl-btn-delete delete-trigger-btn cursor-pointer"
                                            data-id="<?= (int) $b['user_id'] ?>"
                                            data-name="<?= htmlspecialchars($b['name']) ?>"
                                            title="Archive resident (or delete if they have no payment history)">
                                        Remove
                                    </button>
                                    <!-- Hidden delete form for confirmation submit -->
                                    <form id="delete-form-<?= (int) $b['user_id'] ?>" method="post" action="/admin/boarders/<?= (int) $b['user_id'] ?>/delete" class="hidden">
                                        <?= \App\Support\Csrf::field() ?>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <!-- Slide-Down Edit Drawer Row -->
                        <tr id="edit-row-<?= (int) $b['user_id'] ?>" class="hidden edit-drawer-row">
                            <td colspan="6" class="edit-drawer-cell">
                                <div class="edit-drawer-box">
                                    <div class="edit-drawer-header">
                                        <div class="flex items-center gap-2">
                                            <span style="font-size:1.1rem; line-height:1;">✏️</span>
                                            <h4 class="text-body-sm font-semibold text-neutral-900">
                                                Edit Resident Details &mdash; <span class="text-primary-700 font-bold"><?= htmlspecialchars($b['name']) ?></span>
                                            </h4>
                                        </div>
                                        <span class="text-caption text-neutral-400">Click row above to close</span>
                                    </div>

                                    <form id="edit-form-<?= (int) $b['user_id'] ?>" method="post" action="/admin/boarders/<?= (int) $b['user_id'] ?>/info">
                                        <?= \App\Support\Csrf::field() ?>

                                        <!-- Row 1: Contact & Personal Details (4 columns on desktop) -->
                                        <div class="edit-grid-4">
                                            <div>
                                                <label class="field-label">Full Name</label>
                                                <input name="name" value="<?= htmlspecialchars($b['name']) ?>" required class="field-input">
                                            </div>
                                            <div>
                                                <label class="field-label">Email Address</label>
                                                <input name="email" type="email" value="<?= htmlspecialchars($b['email']) ?>" required class="field-input">
                                            </div>
                                            <div>
                                                <label class="field-label">Contact Number</label>
                                                <input name="contact_number" value="<?= htmlspecialchars($b['contact_number'] ?? '') ?>" placeholder="e.g. 09123456789" class="field-input">
                                            </div>
                                            <div>
                                                <label class="field-label">Emergency Contact Number</label>
                                                <input name="emergency_contact_number" value="<?= htmlspecialchars($b['emergency_contact_number'] ?? '') ?>" placeholder="e.g. 09987654321" class="field-input">
                                            </div>
                                        </div>

                                        <!-- Row 2: Status & Note (2 columns with generous proportions) -->
                                        <div class="edit-grid-2">
                                            <div>
                                                <label class="field-label">Resident Status</label>
                                                <select name="status" class="field-select">
                                                    <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>Pending</option>
                                                    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                                                    <option value="on_notice" <?= $status === 'on_notice' ? 'selected' : '' ?>>On Notice</option>
                                                    <option value="moved_out" <?= $status === 'moved_out' ? 'selected' : '' ?>>Moved Out</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="field-label">Note (Reason for status update)</label>
                                                <input name="note" placeholder="Optional notes regarding this resident or lifecycle change" class="field-input">
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-neutral-100">
                                            <button type="button"
                                                    class="btn btn-secondary !py-1.5 !px-3.5 !text-xs cancel-edit-btn cursor-pointer"
                                                    data-id="<?= (int) $b['user_id'] ?>">
                                                Cancel
                                            </button>
                                            <button type="button"
                                                    class="btn btn-primary !py-1.5 !px-5 !text-xs update-trigger-btn cursor-pointer"
                                                    data-id="<?= (int) $b['user_id'] ?>"
                                                    data-name="<?= htmlspecialchars($b['name']) ?>">
                                                Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Dynamic filtered empty state -->
                        <tr id="no-match-row" class="hidden">
                            <td colspan="6" class="text-center" style="padding: 3.5rem 1rem;">
                                <span style="font-size:2.25rem; display:block; margin-bottom:0.75rem;">🔍</span>
                                <span class="text-sm font-semibold text-neutral-800 block">No residents match your search or filter</span>
                                <span class="text-xs text-neutral-500 block mt-1">Try adjusting your search terms or status filter.</span>
                                <button type="button" id="reset-filters-btn" class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900 hover:underline cursor-pointer">
                                    Reset all filters ↺
                                </button>
                            </td>
                        </tr>

                        <?php if (empty($boarders)): ?>
                        <tr>
                            <td colspan="6" class="text-center" style="padding: 3.5rem 1rem;">
                                <span style="font-size:2.25rem; display:block; margin-bottom:0.75rem;">🏠</span>
                                <span class="text-sm font-semibold text-neutral-800 block">No boarders registered yet</span>
                                <span class="text-xs text-neutral-500 block mt-1">Use the "Add Boarder" form below to register your first resident.</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Management Actions Section -->
    <div>
        <div class="section-headline-wrap">
            <div>
                <h2 class="section-headline-title">Management Actions</h2>
                <p class="section-headline-subtitle">Register new resident accounts, assign room/bed allocations, or review system operations rules.</p>
            </div>
        </div>

        <div class="boarders-management-grid">

            <!-- Card 1: Add Boarder Form -->
            <form method="post" action="/admin/boarders" class="mgmt-card">
                <div class="mgmt-card-header">
                    <span style="font-size:1.15rem; line-height:1;">👤</span>
                    <h3 class="mgmt-card-title">Add Boarder</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div class="mgmt-card-body">
                    <div>
                        <label class="field-label">Full Name</label>
                        <input name="name" placeholder="Juan dela Cruz" required class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Email Address</label>
                        <input name="email" type="email" placeholder="boarder@example.com" required class="field-input">
                    </div>
                    <div>
                        <label for="add-boarder-password" class="field-label">Temporary Password</label>
                        <div class="password-relative-wrap">
                            <input id="add-boarder-password"
                                   name="password"
                                   type="password"
                                   placeholder="Min. 8 characters"
                                   required
                                   class="field-input password-input-with-toggle">
                            <button type="button"
                                    id="toggle-boarder-password"
                                    class="password-eye-toggle"
                                    aria-label="Show password"
                                    title="Show / hide password"
                                    tabindex="0">
                                <!-- Eye icon (seen) -->
                                <svg id="eye-open-boarder" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                <!-- Eye-off icon (unseen) -->
                                <svg id="eye-closed-boarder" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label for="add_boarder_room" class="field-label">
                            Room <span class="font-normal text-neutral-400">(optional)</span>
                        </label>
                        <select id="add_boarder_room" name="room_id" class="field-select">
                            <option value="">— Skip room assignment —</option>
                            <?php foreach ($availableRooms as $r): ?>
                                <option value="<?= (int) $r['id'] ?>">Room <?= htmlspecialchars($r['room_number']) ?> (<?= count($vacantBedsByRoom[(int)$r['id']] ?? []) ?> vacant)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="add_boarder_bed" class="field-label">
                            Bed <span class="font-normal text-neutral-400">(optional)</span>
                        </label>
                        <select id="add_boarder_bed" name="bed_id" class="field-select">
                            <option value="">— Select a room first —</option>
                            <?php foreach ($vacantBeds as $b): ?>
                                <option value="<?= (int) $b['id'] ?>" data-room-id="<?= (int) $b['room_id'] ?>" style="display:none;">
                                    Room <?= htmlspecialchars($b['room_number']) ?> — <?= htmlspecialchars($b['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mgmt-btn-wrap">
                    <button class="btn btn-primary w-full !py-2 !text-xs font-semibold">
                        Create Boarder Account
                    </button>
                </div>
            </form>

            <!-- Card 2: Assign Boarder to Bed Form -->
            <form method="post" action="/admin/beds/assign" class="mgmt-card accent-success">
                <div class="mgmt-card-header">
                    <span style="font-size:1.15rem; line-height:1;">🛏️</span>
                    <h3 class="mgmt-card-title">Assign to Bed</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div class="mgmt-card-body">
                    <div>
                        <label class="field-label">Select Boarder</label>
                        <select name="boarder_id" required class="field-select">
                            <option value="">— Choose Boarder —</option>
                            <?php foreach ($boarders as $b): ?>
                                <option value="<?= (int) $b['user_id'] ?>">
                                    <?= htmlspecialchars($b['name']) ?> <?= !empty($b['bed_id']) ? '(Assigned: ' . htmlspecialchars(($b['room_number'] ?? '') . '/' . ($b['bed_label'] ?? '')) . ')' : '(Unassigned)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Select Vacant Bed</label>
                        <select name="bed_id" required class="field-select">
                            <option value="">— Choose Vacant Bed —</option>
                            <?php foreach ($vacantBeds as $b): ?>
                                <option value="<?= (int) $b['id'] ?>">Room <?= htmlspecialchars($b['room_number']) ?> / Bed <?= htmlspecialchars($b['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="assign-notice-box">
                        <span style="font-size: 1rem; line-height: 1;">ℹ️</span>
                        <span>Assigning a new bed to an already-assigned resident will safely relocate them and vacate their previous bed automatically.</span>
                    </div>
                </div>
                <div class="mgmt-btn-wrap">
                    <button class="btn btn-secondary w-full !py-2 !text-xs font-semibold hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300">
                        Confirm Bed Assignment
                    </button>
                </div>
            </form>

            <!-- Card 3: Operations Guide -->
            <div class="mgmt-card accent-neutral guide-card-col">
                <div class="mgmt-card-header">
                    <span style="font-size:1.15rem; line-height:1;">📋</span>
                    <h3 class="mgmt-card-title">Operations Guide</h3>
                </div>
                <div class="mgmt-card-body">
                    <div class="guide-card-stack">
                        <div class="guide-item-box">
                            <div class="guide-item-title">
                                <span>🔄</span>
                                <span>Automated Bed Synchronization</span>
                            </div>
                            When a resident is deleted or marked as <em>moved out</em>, their assigned bed is automatically released back to the vacant inventory.
                        </div>
                        <div class="guide-item-box">
                            <div class="guide-item-title">
                                <span>🏷️</span>
                                <span>Status Lifecycle Flow</span>
                            </div>
                            Residents transition across structured stages:
                            <span class="inline-block font-semibold text-neutral-800">pending &rarr; active &rarr; on notice &rarr; moved out</span>.
                        </div>
                        <div class="guide-item-box">
                            <div class="guide-item-title">
                                <span>🔀</span>
                                <span>Seamless Relocation</span>
                            </div>
                            Assigning an active resident to a new bed relocates them instantly while freeing up their prior bed with zero manual cleanup required.
                        </div>
                    </div>
                </div>
                <div class="mgmt-btn-wrap">
                    <a href="/admin/rooms" class="btn btn-secondary w-full !py-2 !text-xs font-semibold text-neutral-600 hover:text-neutral-900">
                        View All Rooms &amp; Beds &rarr;
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Double Confirmation Popout Modal -->
<div id="confirm-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:99999; background:rgba(15,23,42,0.55); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;" role="dialog" aria-modal="true" aria-labelledby="modal-heading">
    <div id="confirm-card" style="background:#fff; border-radius:1rem; padding:1.75rem 1.75rem; max-width:23rem; width:100%; margin:0 auto; text-align:center; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        
        <!-- Big Round Icon (!) -->
        <div id="modal-icon-wrap" style="width:3.5rem; height:3.5rem; border-radius:50%; border:2px solid #10b981; color:#059669; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:700; margin:0 auto 0.85rem; user-select:none;">
            !
        </div>

        <!-- Title -->
        <h3 id="modal-heading" style="font-size:1.15rem; font-weight:700; color:#0f172a; letter-spacing:-0.01em; margin:0 0 0.45rem;">Save Changes?</h3>

        <!-- Description -->
        <p id="modal-subtext" style="font-size:0.8125rem; color:#64748b; line-height:1.55; margin:0 0 1.5rem; padding:0 0.5rem;">
            Are you sure you want to proceed?
        </p>

        <!-- Actions -->
        <div style="display:flex; align-items:center; justify-content:flex-end; gap:0.75rem; padding-top:0.25rem;">
            <button type="button" id="modal-cancel" style="padding:0.55rem 1.15rem; border-radius:0.5rem; font-size:0.8125rem; font-weight:600; color:#64748b; background:#f1f5f9; border:1px solid #cbd5e1; cursor:pointer; transition:all 150ms;">
                Cancel
            </button>
            <button type="button" id="modal-confirm" style="color:#fff; font-weight:600; border-radius:0.5rem; padding:0.55rem 1.35rem; font-size:0.8125rem; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:0.375rem; transition:all 150ms; background:linear-gradient(135deg,#10b981,#059669); box-shadow: 0 2px 6px rgba(16,185,129,0.3);">
                <span id="modal-confirm-label">Yes</span>
                <span style="font-size:0.95rem;">&rarr;</span>
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    function initBoardersPage() {
        // 1. Room → Bed cascading select (Add Boarder form)
        const roomSelect = document.getElementById('add_boarder_room');
        const bedSelect  = document.getElementById('add_boarder_bed');
        if (roomSelect && bedSelect) {
            const allBedOptions = Array.from(bedSelect.querySelectorAll('option[data-room-id]'));
            roomSelect.addEventListener('change', function() {
                const selectedRoomId = this.value;
                bedSelect.value = '';
                if (!selectedRoomId) {
                    bedSelect.innerHTML = '<option value="">— Select a room first —</option>';
                    return;
                }
                const matchingBeds = allBedOptions.filter(opt => opt.getAttribute('data-room-id') === selectedRoomId);
                bedSelect.innerHTML = '<option value="">— Choose vacant bed —</option>';
                if (matchingBeds.length === 0) {
                    bedSelect.innerHTML = '<option value="">— No vacant beds in this room —</option>';
                } else {
                    matchingBeds.forEach(opt => {
                        const clone = opt.cloneNode(true);
                        clone.style.display = '';
                        bedSelect.appendChild(clone);
                    });
                }
            });
        }

        // 2. Password visibility toggle
        const toggleBtn     = document.getElementById('toggle-boarder-password');
        const passwordInput = document.getElementById('add-boarder-password');
        const eyeOpen       = document.getElementById('eye-open-boarder');
        const eyeClosed     = document.getElementById('eye-closed-boarder');
        if (toggleBtn && passwordInput && eyeOpen && eyeClosed) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                eyeOpen.classList.toggle('hidden', isPassword);
                eyeClosed.classList.toggle('hidden', !isPassword);
                toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        }

        // 3. Edit drawer helpers
        let activeOpenId = null;

        function openDrawer(id) {
            if (activeOpenId && activeOpenId !== id) closeDrawer(activeOpenId);
            const row     = document.getElementById('edit-row-' + id);
            const mainRow = document.getElementById('boarder-row-' + id);
            if (!row) return;
            row.classList.remove('hidden');
            const box = row.querySelector('.edit-drawer-box');
            if (window.gsap && box) {
                gsap.fromTo(box, { opacity: 0, y: -10 }, { opacity: 1, y: 0, duration: 0.22, ease: 'power2.out' });
            }
            if (mainRow) mainRow.style.backgroundColor = '#f1f5f9';
            activeOpenId = id;
        }

        function closeDrawer(id) {
            const row     = document.getElementById('edit-row-' + id);
            const mainRow = document.getElementById('boarder-row-' + id);
            if (!row || row.classList.contains('hidden')) return;
            const box = row.querySelector('.edit-drawer-box');
            if (window.gsap && box) {
                gsap.to(box, { opacity: 0, y: -10, duration: 0.18, ease: 'power2.in', onComplete: () => row.classList.add('hidden') });
            } else {
                row.classList.add('hidden');
            }
            if (mainRow) mainRow.style.backgroundColor = '';
            if (activeOpenId === id) activeOpenId = null;
        }

        // 4. Modal helpers
        let pendingAction = null;

        function showConfirmModal(cfg) {
            const modal = document.getElementById('confirm-modal');
            if (!modal) { if (typeof cfg.onConfirm === 'function') cfg.onConfirm(); return; }

            const icon = document.getElementById('modal-icon-wrap');
            const head = document.getElementById('modal-heading');
            const sub  = document.getElementById('modal-subtext');
            const btn  = document.getElementById('modal-confirm');
            const lbl  = document.getElementById('modal-confirm-label');

            if (head) head.textContent = cfg.title;
            if (sub)  sub.textContent  = cfg.desc;
            if (lbl)  lbl.textContent  = cfg.confirmLabel || 'Yes';

            const isDelete = cfg.type === 'delete';
            if (icon) {
                icon.style.borderColor = isDelete ? '#ef4444' : '#10b981';
                icon.style.color       = isDelete ? '#dc2626' : '#059669';
            }
            if (btn)  btn.style.background = isDelete
                ? 'linear-gradient(135deg,#ef4444,#dc2626)'
                : 'linear-gradient(135deg,#10b981,#059669)';

            pendingAction = cfg.onConfirm;
            modal.style.display = 'flex';

            const card = document.getElementById('confirm-card');
            if (window.gsap && card) {
                gsap.fromTo(card,
                    { opacity: 0, scale: 0.9, y: 12 },
                    { opacity: 1, scale: 1,   y: 0,  duration: 0.25, ease: 'back.out(1.7)' }
                );
            }
        }

        function hideConfirmModal() {
            const modal = document.getElementById('confirm-modal');
            if (modal) modal.style.display = 'none';
            pendingAction = null;
        }

        // 5. Delegated listener on document
        document.addEventListener('click', function(e) {

            // Edit toggle button
            const editToggle = e.target.closest('.edit-toggle-btn');
            if (editToggle) {
                e.stopPropagation();
                const id  = editToggle.getAttribute('data-id');
                const row = document.getElementById('edit-row-' + id);
                (row && !row.classList.contains('hidden')) ? closeDrawer(id) : openDrawer(id);
                return;
            }

            // Cancel edit button
            const cancelEdit = e.target.closest('.cancel-edit-btn');
            if (cancelEdit) {
                e.stopPropagation();
                closeDrawer(cancelEdit.getAttribute('data-id'));
                return;
            }

            // UPDATE button — show confirmation modal
            const updateBtn = e.target.closest('.update-trigger-btn');
            if (updateBtn) {
                e.preventDefault();
                e.stopPropagation();
                const id   = updateBtn.getAttribute('data-id');
                const name = updateBtn.getAttribute('data-name');
                const form = document.getElementById('edit-form-' + id);
                showConfirmModal({
                    type: 'update',
                    title: 'Save Changes?',
                    desc:  "Are you sure you want to update " + name + "'s resident details?",
                    confirmLabel: 'Save',
                    onConfirm: function() { if (form) form.submit(); }
                });
                return;
            }

            // DELETE button — show confirmation modal
            const deleteBtn = e.target.closest('.delete-trigger-btn');
            if (deleteBtn) {
                e.preventDefault();
                e.stopPropagation();
                const id   = deleteBtn.getAttribute('data-id');
                const name = deleteBtn.getAttribute('data-name');
                const form = document.getElementById('delete-form-' + id);
                showConfirmModal({
                    type: 'delete',
                    title: 'Remove Resident?',
                    desc:  "Remove " + name + "? Their bed is freed and their login disabled. If they have any payment or penalty records they are archived (records kept, can be restored); otherwise the account is deleted.",
                    confirmLabel: 'Remove',
                    onConfirm: function() { if (form) form.submit(); }
                });
                return;
            }

            // Modal Cancel button
            if (e.target.closest('#modal-cancel')) {
                hideConfirmModal();
                return;
            }

            // Modal Confirm button (Yes →)
            if (e.target.closest('#modal-confirm')) {
                const action = pendingAction;
                hideConfirmModal();
                if (typeof action === 'function') action();
                return;
            }

            // Click backdrop to close modal
            const modal = document.getElementById('confirm-modal');
            if (modal && modal.style.display !== 'none' && e.target === modal) {
                hideConfirmModal();
                return;
            }

            // Click main boarder row to close open drawer if open
            const mainRow = e.target.closest('.boarder-main-row');
            if (mainRow && !e.target.closest('button, a, input, select, textarea')) {
                const id     = mainRow.getAttribute('data-id');
                const drawer = document.getElementById('edit-row-' + id);
                if (drawer && !drawer.classList.contains('hidden')) {
                    closeDrawer(id);
                } else {
                    openDrawer(id);
                }
            }
        });

        // 6. Live Search & Status Filter Tabs with URL Query Persistence
        const searchInput    = document.getElementById('boarder-search');
        const clearSearchBtn = document.getElementById('clear-search-btn');
        const filterTabs     = document.querySelectorAll('#status-filter-tabs .filter-tab');
        const countDisplay   = document.getElementById('filtered-count');
        const noMatchRow     = document.getElementById('no-match-row');
        const resetBtn       = document.getElementById('reset-filters-btn');
        const mainRows       = Array.from(document.querySelectorAll('.boarder-main-row'));

        let currentFilter = 'all';

        function applyFilters() {
            const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
            if (clearSearchBtn) {
                clearSearchBtn.classList.toggle('hidden', query === '');
            }

            let visibleCount = 0;
            mainRows.forEach(row => {
                const id = row.getAttribute('data-id');
                const rowStatus = row.getAttribute('data-status') || '';
                const rowSearch = row.getAttribute('data-search') || '';

                const matchesStatus = (currentFilter === 'all') || (rowStatus === currentFilter);
                const matchesSearch = (query === '') || (rowSearch.indexOf(query) !== -1);

                if (matchesStatus && matchesSearch) {
                    row.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.classList.add('hidden');
                    const drawer = document.getElementById('edit-row-' + id);
                    if (drawer) drawer.classList.add('hidden');
                }
            });

            if (countDisplay) countDisplay.textContent = visibleCount;
            if (noMatchRow) noMatchRow.classList.toggle('hidden', visibleCount > 0);

            // Update URL without page reload
            const params = new URLSearchParams(window.location.search);
            if (currentFilter !== 'all') {
                params.set('status', currentFilter);
            } else {
                params.delete('status');
            }
            if (query !== '') {
                params.set('q', query);
            } else {
                params.delete('q');
            }
            const qs = params.toString();
            const newUrl = window.location.pathname + (qs ? '?' + qs : '');
            window.history.replaceState(null, '', newUrl);
        }

        function setFilter(status) {
            currentFilter = status;
            filterTabs.forEach(tab => {
                const isActive = tab.getAttribute('data-filter') === status;
                tab.classList.toggle('is-active', isActive);
            });
            applyFilters();
        }

        if (filterTabs.length > 0) {
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    setFilter(this.getAttribute('data-filter') || 'all');
                });
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                if (searchInput) searchInput.value = '';
                applyFilters();
                if (searchInput) searchInput.focus();
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                if (searchInput) searchInput.value = '';
                setFilter('all');
            });
        }

        // Initialize from URL params on load
        const initialParams = new URLSearchParams(window.location.search);
        const initialStatus = initialParams.get('status');
        const initialQuery  = initialParams.get('q');
        if (initialQuery && searchInput) {
            searchInput.value = initialQuery;
        }
        if (initialStatus && ['active', 'pending', 'on_notice', 'moved_out'].includes(initialStatus)) {
            setFilter(initialStatus);
        } else {
            applyFilters();
        }
    }

    // Run immediately
    initBoardersPage();
})();

if (window.gsap && window.ScrollTrigger) {
    let mm = gsap.matchMedia();
    mm.add({
        animate: "(prefers-reduced-motion: no-preference)",
        reduce:  "(prefers-reduced-motion: reduce)",
    }, (context) => {
        if (context.conditions.animate) {
            gsap.from(".reveal-card", {
                y: 18, opacity: 0, duration: 0.45, stagger: 0.06, ease: "power2.out",
                scrollTrigger: { trigger: ".boarders-kpi-grid", start: "top 95%", once: true },
            });
        } else {
            gsap.set(".reveal-card", { opacity: 1, y: 0 });
        }
    });
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
