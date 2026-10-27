<?php
$pageTitle = 'Boarders';
ob_start();
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$vacantBeds = array_values(array_filter($beds ?? [], fn($b) => ($b['status'] ?? '') === 'vacant'));

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

<?php require __DIR__ . '/_boarders_shared_css.php'; ?>
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
    color: #9d9b97;
    margin-top: 0.25rem;
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
    border: 1px solid #e6e5e2;
    border-radius: 0.75rem;
    padding: 1.125rem 1.125rem 1rem;
    box-shadow: 0 1px 3px rgba(10, 10, 10, 0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-width: 0;
    transition: transform 180ms ease, box-shadow 180ms ease;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px -2px rgba(10, 10, 10, 0.08);
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
    color: #6b6b6b;
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
    color: #0a0a0a;
    font-variant-numeric: tabular-nums;
    margin-top: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kpi-subtext {
    font-size: 0.75rem;
    color: #9d9b97;
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
    border: 1px solid #e6e5e2;
    border-top: 3px solid #b15f2c;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(10, 10, 10, 0.05);
    padding: 1.35rem 1.25rem 1.25rem;
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.mgmt-card.accent-success {
    border-top-color: #2f6f4e;
}
.mgmt-card.accent-neutral {
    border-top-color: #6b6b6b;
}
.mgmt-card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.125rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f0ee;
}
.mgmt-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0a0a0a;
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
    color: #9d9b97;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.35rem;
    transition: color 150ms ease;
}
.password-eye-toggle:hover {
    color: #3b3a38;
}

/* Assign notice box */
.assign-notice-box {
    background: #eef6f1;
    border: 1px solid #d7ebdf;
    color: #245a3f;
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
    border: 1px solid #e6e5e2;
    background: #f8f7f5;
    font-size: 0.775rem;
    line-height: 1.5;
    color: #555452;
}
.guide-item-title {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 700;
    color: #0a0a0a;
    margin-bottom: 0.2rem;
    font-size: 0.8rem;
}
</style>

<div class="boarders-page-wrapper space-y-6">

    <!-- Page Banner -->
    <div class="boarders-banner">
        <div>
            <h1>Boarders</h1>
            <p>Register residents, place them in a bed, and see the house at a glance</p>
        </div>
        <a href="/admin/staff?view=boarders" class="btn btn-secondary btn-sm"><?= \App\Support\Icon::svg('users', 14) ?> Resident directory</a>
    </div>

    <?php $userTab = ''; // the Boarders tab is the Resident Directory; this page is reached from the sidebar
    require __DIR__ . '/_user_tabs.php'; ?>

    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3.5 border border-error-500 flex items-center gap-2.5" role="alert">
            <?= \App\Support\Icon::svg('alert-triangle', 18) ?>
            <span class="font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-lg p-3.5 border border-emerald-300 flex items-center gap-2.5" role="alert">
            <?= \App\Support\Icon::svg('check', 18) ?>
            <span class="font-medium"><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($conversion['error'])): ?>
        <div role="alert" style="padding:0.85rem 1rem; border-radius:0.75rem; font-size:0.875rem; background:#fbf6e8; border:1px solid #efdfb4; color:#7a5300;">
            <?= htmlspecialchars($conversion['error']) ?>
            <?php if (!empty($conversion['user_id'])): ?>
                <a href="/admin/boarders/<?= (int) $conversion['user_id'] ?>" style="font-weight:600; text-decoration:underline;">Open their profile</a>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($conversion['inquiry'])): ?>
        <?php $inq = $conversion['inquiry']; ?>
        <div role="status" style="padding:0.85rem 1rem; border-radius:0.75rem; font-size:0.875rem; background:#fbf4ef; border:1px solid #efd9c8; color:#7c3f19; display:flex; flex-wrap:wrap; gap:0.35rem 1rem; align-items:baseline; justify-content:space-between;">
            <span>
                <strong>Registering inquiry #<?= (int) $inq['id'] ?></strong> —
                <?= htmlspecialchars($inq['name']) ?> asked about <?= htmlspecialchars($inq['room_type'] ?: 'a room') ?>.
                Their details are filled in below. Check them, set a temporary password, then press Create Boarder Account.
                <?php if ($conversion['notice']): ?><br><span style="color:#9a6700;"><?= htmlspecialchars($conversion['notice']) ?></span><?php endif; ?>
            </span>
            <span style="white-space:nowrap;">
                <a href="/admin/inquiries/<?= (int) $inq['id'] ?>" style="font-weight:600; text-decoration:underline;">Back to inquiry</a> ·
                <a href="/admin/boarders" style="font-weight:600; text-decoration:underline;">Cancel</a>
            </span>
        </div>
        <?php if ($ex = $conversion['existing']): ?>
            <div role="alert" style="padding:0.85rem 1rem; border-radius:0.75rem; font-size:0.875rem; background:#fbf6e8; border:1px solid #efdfb4; color:#7a5300; display:flex; flex-wrap:wrap; gap:0.5rem 1rem; align-items:center; justify-content:space-between;">
                <span>
                    <strong><?= htmlspecialchars($inq['email']) ?> already has an account:</strong>
                    <?= htmlspecialchars($ex['name']) ?> (<?= htmlspecialchars($ex['role']) ?>). A second account with the same email cannot be created.
                    <?= $ex['role'] === 'boarder' ? 'If this is the same person, link the inquiry to them instead.' : 'Use a different email below if this is someone else.' ?>
                </span>
                <span style="display:flex; gap:0.75rem; align-items:center; white-space:nowrap;">
                    <?php if ($ex['role'] === 'boarder'): ?>
                        <a href="/admin/boarders/<?= (int) $ex['id'] ?>" style="font-weight:600; text-decoration:underline;">Open their profile</a>
                        <form method="post" action="/admin/inquiries/<?= (int) $inq['id'] ?>/link-boarder" style="margin:0;">
                            <?= \App\Support\Csrf::field() ?>
                            <button class="btn btn-primary !py-1.5 !px-3 !text-xs font-semibold">Link inquiry to <?= htmlspecialchars($ex['name']) ?></button>
                        </form>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- KPI Metric Cards (5 Cards) -->
    <div class="boarders-kpi-grid">
        <div class="reveal-card kpi-card metric-accent-primary">
            <div class="kpi-card-header">
                <span class="kpi-label">Total Residents</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('users') ?></span>
            </div>
            <div class="kpi-value"><?= $totalBoarders ?></div>
            <div class="kpi-subtext">
                <?php if ($unassignedBoarders > 0): ?>
                    <span class="text-amber-600 font-semibold"><?= \App\Support\Icon::svg('alert-triangle') ?> <?= $unassignedBoarders ?> unassigned</span>
                <?php else: ?>
                    <span class="text-neutral-400"><?= \App\Support\Icon::svg('check') ?> All assigned to beds</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="reveal-card kpi-card metric-accent-success">
            <div class="kpi-card-header">
                <span class="kpi-label">Active Residents</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('circle-dot') ?></span>
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
                <span class="kpi-icon"><?= \App\Support\Icon::svg('bed') ?></span>
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
            <form method="post" action="/admin/boarders" class="mgmt-card" id="add-boarder">
                <div class="mgmt-card-header">
                    <?= \App\Support\Icon::svg('user', 18) ?>
                    <h3 class="mgmt-card-title">Add Boarder<?= $addForm['inquiry_id'] ? ' — from inquiry #' . $addForm['inquiry_id'] : '' ?></h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <?php if ($addForm['inquiry_id']): ?>
                    <input type="hidden" name="inquiry_id" value="<?= $addForm['inquiry_id'] ?>">
                <?php endif; ?>
                <div class="mgmt-card-body">
                    <?php if (!empty($conversion['notice'])): ?>
                        <p role="note" style="font-size:0.75rem; line-height:1.45; background:#fbf6e8; border:1px solid #efdfb4; color:#7a5300; padding:0.6rem 0.75rem; border-radius:0.5rem;"><?= htmlspecialchars($conversion['notice']) ?></p>
                    <?php endif; ?>
                    <div>
                        <label for="add-boarder-name" class="field-label">Full Name</label>
                        <input id="add-boarder-name" name="name" placeholder="Juan dela Cruz" required maxlength="150" class="field-input" value="<?= htmlspecialchars($addForm['name']) ?>">
                    </div>
                    <div>
                        <label for="add-boarder-email" class="field-label">Email Address</label>
                        <input id="add-boarder-email" name="email" type="email" placeholder="boarder@example.com" required maxlength="150" class="field-input" value="<?= htmlspecialchars($addForm['email']) ?>">
                    </div>
                    <div>
                        <label for="add-boarder-contact" class="field-label">Contact Number <span class="font-normal text-neutral-400">(optional)</span></label>
                        <input id="add-boarder-contact" name="contact_number" type="tel" placeholder="+63 9XX XXX XXXX" maxlength="20" class="field-input" value="<?= htmlspecialchars($addForm['contact_number']) ?>">
                    </div>
                    <div>
                        <label for="add-boarder-password" class="field-label">Temporary Password</label>
                        <div class="password-relative-wrap">
                            <input id="add-boarder-password"
                                   name="password"
                                   type="password" data-max-bytes="72"
                                   placeholder="At least 15 characters"
                                   required<?= $addForm['inquiry_id'] ? ' autofocus' : '' ?>
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
                    <?= \App\Support\Icon::svg('bed', 18) ?>
                    <h3 class="mgmt-card-title">Assign to Bed</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div class="mgmt-card-body">
                    <div>
                        <label class="field-label">Select Boarder</label>
                        <select name="boarder_id" required class="field-select">
                            <option value="">— Choose Boarder —</option>
                            <?php foreach ($boarders as $b): ?>
                                <option value="<?= (int) $b['user_id'] ?>" <?= (int) $b['user_id'] === $assignPrefill['boarder_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['name']) ?> <?= !empty($b['bed_id']) ? '(Assigned: ' . htmlspecialchars(($b['room_number'] ?? '') . '/' . ($b['bed_label'] ?? '')) . ')' : '(Unassigned)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="assign-room-type" class="field-label">Room Type</label>
                        <select id="assign-room-type" class="field-select" data-filters-beds="assign-bed">
                            <option value="">— All room types —</option>
                            <?php foreach ($roomTypes as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $assignPrefill['room_type_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?> (<?= (int) $t['vacant_bed_count'] ?> vacant)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="assign-bed" class="field-label">Select Vacant Bed</label>
                        <select id="assign-bed" name="bed_id" required class="field-select">
                            <option value="">— Choose Vacant Bed —</option>
                            <?php foreach ($vacantBeds as $b): ?>
                                <option value="<?= (int) $b['id'] ?>" data-room-type="<?= (int) ($b['room_type_id'] ?? 0) ?>">Room <?= htmlspecialchars($b['room_number']) ?> / Bed <?= htmlspecialchars($b['label']) ?></option>
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
                    <?= \App\Support\Icon::svg('clipboard', 18) ?>
                    <h3 class="mgmt-card-title">Operations Guide</h3>
                </div>
                <div class="mgmt-card-body">
                    <div class="guide-card-stack">
                        <div class="guide-item-box">
                            <div class="guide-item-title">
                                <span><?= \App\Support\Icon::svg('refresh') ?></span>
                                <span>Automated Bed Synchronization</span>
                            </div>
                            When a resident is archived or marked as <em>moved out</em>, their assigned bed is automatically released back to the vacant inventory.
                        </div>
                        <div class="guide-item-box">
                            <div class="guide-item-title">
                                <span><?= \App\Support\Icon::svg('tag') ?></span>
                                <span>Status Lifecycle Flow</span>
                            </div>
                            Residents transition across structured stages:
                            <span class="inline-block font-semibold text-neutral-800">pending &rarr; active &rarr; on notice &rarr; moved out</span>.
                        </div>
                        <div class="guide-item-box">
                            <div class="guide-item-title">
                                <span><?= \App\Support\Icon::svg('arrow-left-right') ?></span>
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

<script>
(function() {
    function initBoardersPage() {
        // 1. Password visibility toggle
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
    }

    // Run immediately
    initBoardersPage();
})();

</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
