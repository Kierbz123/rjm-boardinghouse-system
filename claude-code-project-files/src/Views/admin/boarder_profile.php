<?php
$pageTitle = 'Resident Profile — ' . htmlspecialchars($boarder['name']);
ob_start();

$error   = $_SESSION['flash_error']   ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);
$info    = $_SESSION['flash_info']    ?? null; unset($_SESSION['flash_info']);

$id = (int) $boarder['user_id'];
$status = $boarder['status'] ?? 'pending';

$statusBadgeClass = match($status) {
    'active'    => 'badge-success',
    'pending'   => 'badge-warning',
    'on_notice' => 'badge-warning',
    'moved_out' => 'badge-neutral',
    default     => 'badge-neutral',
};

$totalOutstanding = (float) ($balanceDetails['total_outstanding'] ?? 0.0);
$rentDue = (float) ($balanceDetails['rent_due'] ?? 0.0);
$penaltiesDue = (float) ($balanceDetails['penalties_due'] ?? 0.0);
$basePrice = (float) ($balanceDetails['base_price'] ?? 0.0);
$cachedBalance = (float) ($boarder['outstanding_balance'] ?? 0.0);

$totalPaid = (float) ($paymentSummary['total_paid'] ?? 0.0);
$paymentCount = (int) ($paymentSummary['count'] ?? 0);
$pendingPayments = (int) ($paymentSummary['pending_count'] ?? 0);
?>

<style>
/* =========================================================
   BOARDER PROFILE ENHANCED RESPONSIVE STYLES
   Fixes layout cramping, overlapping inputs, squashed dates,
   and provides consistent spacing and responsive alignments.
   ========================================================= */

.profile-wrapper {
    max-width: 82rem;
    margin: 0 auto;
    padding: 1.5rem 1.25rem 3.5rem;
}

/* Headings safety override against layout.php white-space: nowrap */
.profile-wrapper h1,
.profile-wrapper h2,
.profile-wrapper h3,
.profile-wrapper h4,
.profile-wrapper h5,
.profile-wrapper h6 {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
}

/* Header Banner */
.profile-banner {
    background: linear-gradient(135deg, #0a0a0a 0%, #1f1f1e 100%);
    border-radius: 1rem;
    padding: 1.5rem 1.75rem;
    color: #ffffff;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    box-shadow: 0 4px 20px -2px rgba(10, 10, 10, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.08);
}
@media (min-width: 768px) {
    .profile-banner {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
.profile-banner-balance {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.875rem;
    padding: 0.875rem 1.25rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}
@media (min-width: 768px) {
    .profile-banner-balance {
        align-items: flex-end;
        text-align: right;
    }
}

/* Two-Column Top Grid: Personal Details & Bed/Room Assignment */
.profile-main-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-top: 1.5rem;
}
@media (min-width: 1024px) {
    .profile-main-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: stretch;
    }
}

/* Base Card */
.profile-card {
    background: #ffffff;
    border: 1px solid #e6e5e2;
    border-radius: 1rem;
    box-shadow: 0 1px 3px 0 rgba(10, 10, 10, 0.06), 0 1px 2px -1px rgba(10, 10, 10, 0.04);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    overflow: visible !important;
}
.profile-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.875rem;
    border-bottom: 1px solid #f1f0ee;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.profile-card-title-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.profile-card-icon {
    width: 2.375rem;
    height: 2.375rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.625rem;
    background: #f1f0ee;
    font-size: 1.125rem;
    flex-shrink: 0;
}

/* Form Sections and Fieldsets */
.profile-sub-section {
    padding-top: 1.125rem;
    border-top: 1px solid #f1f0ee;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}
.profile-sub-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.profile-section-tag {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #6b6b6b;
}

/* Responsive 2-Col Form Row */
.profile-form-grid-2 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}
@media (min-width: 560px) {
    .profile-form-grid-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* Form Groups & Inputs */
.profile-field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}
.profile-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #3b3a38;
    line-height: 1.25;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.profile-input {
    width: 100%;
    min-height: 2.5rem; /* 40px */
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.35;
    color: #0a0a0a;
    background-color: #ffffff;
    border: 1px solid #d4d2ce;
    border-radius: 0.5rem;
    box-sizing: border-box;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.profile-input:focus {
    outline: none;
    border-color: #bd6b36;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.profile-input::placeholder {
    color: #9d9b97;
}
select.profile-input {
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.65rem center;
    background-repeat: no-repeat;
    background-size: 1.25em 1.25em;
    padding-right: 2.25rem;
    appearance: none;
    -webkit-appearance: none;
}
input[type="date"].profile-input {
    min-height: 2.5rem;
    cursor: pointer;
    line-height: normal;
}

/* Callout Notice Boxes */
.profile-notice-box {
    border-radius: 0.75rem;
    padding: 0.8125rem 1rem;
    font-size: 0.75rem;
    line-height: 1.45;
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
}
.profile-notice-amber {
    background-color: #fbf6e8;
    border: 1px solid #f4e7c2;
    color: #92400e;
}
.profile-notice-blue {
    background-color: #fbf4ef;
    border: 1px solid #ebc6ac;
    color: #7a4119;
}

/* Action Footer / Buttons */
.profile-card-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f0ee;
    margin-top: auto;
    overflow: visible !important;
}
.profile-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    min-height: 2.375rem;
    padding: 0.5rem 1.125rem;
    font-size: 0.8125rem;
    font-weight: 600;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
    text-decoration: none;
}
.profile-btn-primary {
    background-color: #b15f2c;
    color: #ffffff;
    border: 1px solid #97501f;
    box-shadow: 0 1px 2px rgba(177, 95, 44, 0.15);
}
.profile-btn-primary:hover {
    background-color: #97501f;
    box-shadow: 0 2px 4px rgba(177, 95, 44, 0.25);
    transform: translateY(-1px);
}
.profile-btn-secondary {
    background-color: #ffffff;
    color: #3b3a38;
    border: 1px solid #d4d2ce;
    box-shadow: 0 1px 2px rgba(10, 10, 10, 0.05);
}
.profile-btn-secondary:hover {
    background-color: #f8f7f5;
    border-color: #9d9b97;
    color: #0a0a0a;
    transform: translateY(-1px);
}

/* Stat Tiles in Bed/Room Assignment */
.profile-stat-tiles {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.875rem;
}
@media (min-width: 500px) {
    .profile-stat-tiles {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
.profile-stat-tile {
    background-color: #f8f7f5;
    border: 1px solid #e6e5e2;
    border-radius: 0.75rem;
    padding: 0.875rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}
.profile-stat-tile-label {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #6b6b6b;
}
.profile-stat-tile-value {
    font-size: 1rem;
    font-weight: 700;
    color: #0a0a0a;
    line-height: 1.25;
}

/* Penalties Form Layout */
.penalty-form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}
@media (min-width: 640px) {
    .penalty-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 900px) {
    .penalty-form-grid {
        grid-template-columns: 2fr 1fr 1.2fr;
    }
}
.penalty-form-full-col {
    grid-column: 1 / -1;
}

/* Tables */
.profile-table-container {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #e6e5e2;
    border-radius: 0.75rem;
    background: #ffffff;
}
.profile-table {
    width: 100%;
    min-width: 640px;
    border-collapse: collapse;
    table-layout: auto !important;
}
.profile-table th {
    background-color: #f8f7f5;
    color: #555452;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 0.75rem 1rem !important;
    text-align: left;
    border-bottom: 1px solid #e6e5e2;
    white-space: nowrap !important;
}
.profile-table td {
    padding: 0.75rem 1rem !important;
    font-size: 0.8125rem;
    color: #3b3a38;
    border-bottom: 1px solid #f1f0ee;
    vertical-align: middle;
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
}
.profile-table tr:hover td {
    background-color: #f8f7f5;
}

/* Timeline */
.profile-timeline {
    position: relative;
    padding-left: 2.25rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.profile-timeline::before {
    content: '';
    position: absolute;
    left: 0.625rem;
    top: 0.5rem;
    bottom: 0.5rem;
    width: 2px;
    background-color: #e6e5e2;
}
.profile-timeline-node {
    position: relative;
}
.profile-timeline-dot {
    position: absolute;
    left: -2.25rem;
    top: 0.75rem;
    width: 0.875rem;
    height: 0.875rem;
    border-radius: 9999px;
    background-color: #b15f2c;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 2px #f5e3d6;
}
.profile-timeline-dot.created {
    background-color: #6b6b6b;
    box-shadow: 0 0 0 2px #e6e5e2;
}
.profile-timeline-card {
    background-color: #f8f7f5;
    border: 1px solid #e6e5e2;
    border-radius: 0.75rem;
    padding: 0.875rem 1.125rem;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

/* Textarea */
.profile-textarea {
    width: 100%;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.8125rem;
    line-height: 1.6;
    padding: 0.875rem 1rem;
    color: #0a0a0a;
    background-color: #ffffff;
    border: 1px solid #d4d2ce;
    border-radius: 0.625rem;
    box-sizing: border-box;
    resize: vertical;
    min-height: 110px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.profile-textarea:focus {
    outline: none;
    border-color: #bd6b36;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* Danger Zone */
.profile-danger-card {
    background-color: #fdf1f0;
    border: 1px solid #f9dcd9;
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.profile-danger-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
@media (min-width: 640px) {
    .profile-danger-content {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
}
</style>

<div class="profile-wrapper space-y-6">

    <!-- Top Navigation Breadcrumb & Back Button -->
    <div class="flex items-center justify-between gap-4">
        <a href="/admin/boarders" class="profile-btn profile-btn-secondary !text-xs font-semibold">
            <span>←</span> Back to Directory
        </a>
        <div class="flex items-center gap-2">
            <span class="text-caption text-neutral-400 font-medium">Resident ID:</span>
            <span class="id-tag font-mono text-xs">#<?= $id ?></span>
        </div>
    </div>

    <!-- Page Banner / Header Card -->
    <div class="profile-banner">
        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-heading-lg font-bold text-white"><?= htmlspecialchars($boarder['name']) ?></h1>
                <span class="badge <?= $statusBadgeClass ?> capitalize text-xs px-3 py-1 font-semibold">
                    <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-neutral-200">
                <span class="inline-flex items-center gap-1.5">
                    <span class="text-neutral-400">✉</span>
                    <a href="mailto:<?= htmlspecialchars($boarder['email']) ?>" class="hover:underline text-white font-medium"><?= htmlspecialchars($boarder['email']) ?></a>
                </span>
                <?php if (!empty($boarder['contact_number'])): ?>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="text-neutral-400">📞</span>
                        <a href="tel:<?= htmlspecialchars($boarder['contact_number']) ?>" class="hover:underline text-white font-medium"><?= htmlspecialchars($boarder['contact_number']) ?></a>
                    </span>
                <?php endif; ?>
                <span class="inline-flex items-center gap-1.5 text-neutral-300">
                    <span class="text-neutral-400">📅</span>
                    Registered <?= date('M j, Y', strtotime($boarder['user_created_at'] ?? 'now')) ?>
                </span>
            </div>
        </div>

        <div class="profile-banner-balance self-start md:self-auto">
            <div class="text-[11px] uppercase tracking-wider text-neutral-300 font-bold mb-0.5">Total Balance Due</div>
            <div class="text-2xl font-extrabold <?= $totalOutstanding > 0 ? 'text-amber-300' : 'text-emerald-300' ?>">
                ₱<?= number_format($totalOutstanding, 2) ?>
            </div>
        </div>
    </div>

    <!-- Flash Notifications -->
    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-xl p-3.5 border border-error-500 flex items-center gap-2.5" role="alert">
            <span style="font-size:1.15rem;">⚠</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-xl p-3.5 border border-emerald-300 flex items-center gap-2.5" role="alert">
            <span style="font-size:1.15rem;">✓</span>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($info): ?>
        <div class="bg-blue-50 text-blue-800 text-body-sm rounded-xl p-3.5 border border-blue-300 flex items-center gap-2.5" role="alert">
            <span style="font-size:1.15rem;">ℹ</span>
            <span><?= htmlspecialchars($info) ?></span>
        </div>
    <?php endif; ?>

    <!-- Two-Column Layout: Personal Info & Status + Bed/Room Assignment & Occupancy Dates -->
    <div class="profile-main-grid">

        <!-- Left Column: Personal Info & Status -->
        <div class="reveal-card profile-card">
            <div class="profile-card-header">
                <div class="profile-card-title-group">
                    <span class="profile-card-icon">👤</span>
                    <div>
                        <h2 class="text-heading-sm font-semibold text-neutral-900">Personal Details &amp; Residency Status</h2>
                        <p class="text-caption text-neutral-500">Contact information and administrative status lifecycle</p>
                    </div>
                </div>
            </div>

            <form method="post" action="/admin/boarders/<?= $id ?>/info" class="flex flex-col gap-5 flex-1">
                <?= \App\Support\Csrf::field() ?>
                <input type="hidden" name="return_to" value="/admin/boarders/<?= $id ?>">

                <!-- Group 1: Identity & Contact -->
                <div class="space-y-3">
                    <div class="profile-section-tag">Identity &amp; Contact Details</div>
                    <div class="profile-form-grid-2">
                        <div class="profile-field">
                            <label class="profile-label">Full Name <span class="text-error-500">*</span></label>
                            <input type="text" name="name" value="<?= htmlspecialchars($boarder['name']) ?>" required maxlength="150" class="profile-input">
                        </div>
                        <div class="profile-field">
                            <label class="profile-label">Email Address <span class="text-error-500">*</span></label>
                            <input type="email" name="email" value="<?= htmlspecialchars($boarder['email']) ?>" required maxlength="150" class="profile-input">
                        </div>
                        <div class="profile-field">
                            <label class="profile-label">Primary Contact Number</label>
                            <input type="text" name="contact_number" value="<?= htmlspecialchars($boarder['contact_number'] ?? '') ?>" placeholder="e.g. 09123456789" maxlength="50" class="profile-input">
                        </div>
                        <div class="profile-field">
                            <label class="profile-label">Emergency Contact Number</label>
                            <input type="text" name="emergency_contact_number" value="<?= htmlspecialchars($boarder['emergency_contact_number'] ?? '') ?>" placeholder="e.g. 09987654321" maxlength="50" class="profile-input">
                        </div>
                    </div>
                </div>

                <!-- Group 2: Status Lifecycle -->
                <div class="profile-sub-section">
                    <div class="profile-section-tag">Residency Lifecycle Status</div>
                    <div class="profile-form-grid-2">
                        <div class="profile-field">
                            <label class="profile-label">Lifecycle Status</label>
                            <select name="status" class="profile-input">
                                <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>Pending (Awaiting Move-in)</option>
                                <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active (Current Resident)</option>
                                <option value="on_notice" <?= $status === 'on_notice' ? 'selected' : '' ?>>On Notice (Pending Move-out)</option>
                                <option value="moved_out" <?= $status === 'moved_out' ? 'selected' : '' ?>>Moved Out (Former Resident)</option>
                            </select>
                        </div>
                        <div class="profile-field">
                            <label class="profile-label">Status Change Reason</label>
                            <input type="text" name="reason" placeholder="Required when updating status" maxlength="255" class="profile-input">
                        </div>
                    </div>

                    <div class="profile-notice-box profile-notice-blue">
                        <span class="text-base leading-none">ℹ</span>
                        <span>Status transitions are permanently recorded in the audit trail below. Moving to <strong>"Moved Out"</strong> automatically frees their assigned bed.</span>
                    </div>
                </div>

                <div class="profile-card-footer">
                    <button type="submit" class="profile-btn profile-btn-primary">
                        Save Personal Details &amp; Status
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Column: Room Assignment & Occupancy Dates -->
        <div class="reveal-card profile-card">
            <div class="profile-card-header">
                <div class="profile-card-title-group">
                    <span class="profile-card-icon">🛏️</span>
                    <div>
                        <h2 class="text-heading-sm font-semibold text-neutral-900">Bed &amp; Room Assignment</h2>
                        <p class="text-caption text-neutral-500">Current bed space and accommodation charges</p>
                    </div>
                </div>
                <a href="/admin/rooms" class="profile-btn profile-btn-secondary !py-1 !px-2.5 !text-xs font-semibold text-primary-700 hover:text-primary-900">
                    Manage in Rooms ↗
                </a>
            </div>

            <!-- Styled Stat Tiles -->
            <div class="profile-stat-tiles">
                <div class="profile-stat-tile">
                    <span class="profile-stat-tile-label">Assigned Room</span>
                    <span class="profile-stat-tile-value">
                        <?= !empty($boarder['room_number']) ? 'Room ' . htmlspecialchars($boarder['room_number']) : '<span class="text-neutral-400 font-normal">Unassigned</span>' ?>
                    </span>
                    <span class="text-[11px] text-neutral-500"><?= !empty($boarder['floor']) ? 'Floor ' . htmlspecialchars($boarder['floor']) : 'No Floor' ?></span>
                </div>
                <div class="profile-stat-tile">
                    <span class="profile-stat-tile-label">Bed Space</span>
                    <div class="pt-0.5">
                        <?php if (!empty($boarder['bed_label'])): ?>
                            <span class="badge badge-info text-xs px-2.5 py-0.5 font-bold"><?= htmlspecialchars($boarder['bed_label']) ?></span>
                        <?php else: ?>
                            <span class="badge badge-neutral text-xs px-2 py-0.5">No Bed</span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[11px] text-neutral-500 mt-1">Bed Allocation</span>
                </div>
                <div class="profile-stat-tile">
                    <span class="profile-stat-tile-label">Monthly Rate</span>
                    <span class="profile-stat-tile-value text-primary-700">
                        ₱<?= number_format($basePrice, 2) ?>
                    </span>
                    <span class="text-[11px] text-neutral-500">per month</span>
                </div>
            </div>

            <!-- Move Dates Form -->
            <form method="post" action="/admin/boarders/<?= $id ?>/dates" class="profile-sub-section space-y-4 flex-1 flex flex-col">
                <?= \App\Support\Csrf::field() ?>
                <div class="profile-sub-section-header">
                    <div>
                        <span class="profile-section-tag">Occupancy Schedule</span>
                        <h3 class="text-xs font-semibold text-neutral-800">Move-In &amp; Move-Out Dates</h3>
                    </div>
                </div>

                <div class="profile-form-grid-2">
                    <div class="profile-field">
                        <label class="profile-label">Move-In Date</label>
                        <input type="date" name="move_in_date" value="<?= htmlspecialchars($boarder['move_in_date'] ?? '') ?>" class="profile-input">
                    </div>
                    <div class="profile-field">
                        <label class="profile-label">Move-Out Date</label>
                        <input type="date" name="move_out_date" value="<?= htmlspecialchars($boarder['move_out_date'] ?? '') ?>" class="profile-input">
                    </div>
                </div>

                <div class="profile-notice-box profile-notice-amber">
                    <span class="text-base leading-none">ℹ</span>
                    <span>Specifying a <strong>Move-Out Date</strong> automatically marks the resident as <em>Moved Out</em>, frees the assigned bed immediately, and logs the change.</span>
                </div>

                <div class="profile-card-footer">
                    <button type="submit" class="profile-btn profile-btn-secondary">
                        Update Move Dates
                    </button>
                </div>
            </form>
        </div>

    </div>

    <!-- Section 3: Financial Overview -->
    <div class="space-y-4" style="margin-top: 2rem;">
        <div class="section-header flex items-center justify-between mb-3">
            <div>
                <h2 class="text-heading-sm font-semibold text-neutral-900">Financial Overview &amp; Balances</h2>
                <p class="text-caption text-neutral-500">Live canonical breakdown calculated from billing, verified allocations, and penalties</p>
            </div>
            <a href="/admin/payments" class="profile-btn profile-btn-secondary !py-1 !px-2.5 !text-xs font-semibold">
                Payments Queue ↗
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Left Card: Live Canonical Balance Breakdown -->
            <div class="reveal-card profile-card metric-accent-warning space-y-4">
                <div class="flex items-center justify-between">
                    <p class="profile-section-tag">Live Canonical Balance Breakdown</p>
                    <span class="badge badge-info text-[10px]">Period: <?= htmlspecialchars($balanceDetails['billing_period'] ?? date('Y-m')) ?></span>
                </div>

                <div class="flex items-baseline gap-2.5">
                    <span class="text-3xl font-extrabold <?= $totalOutstanding > 0 ? 'text-amber-700' : 'text-emerald-700' ?>">
                        ₱<?= number_format($totalOutstanding, 2) ?>
                    </span>
                    <span class="text-caption text-neutral-500 font-medium">Total Outstanding</span>
                </div>

                <div class="divide-y divide-neutral-100 text-xs pt-1">
                    <div class="py-2 flex justify-between items-center">
                        <span class="text-neutral-600">Rent Due this Period:</span>
                        <span class="font-bold text-neutral-900">₱<?= number_format($rentDue, 2) ?></span>
                    </div>
                    <div class="py-2 flex justify-between items-center">
                        <span class="text-neutral-600">Unpaid Penalties:</span>
                        <span class="font-bold <?= $penaltiesDue > 0 ? 'text-amber-600' : 'text-neutral-900' ?>">₱<?= number_format($penaltiesDue, 2) ?></span>
                    </div>
                    <div class="py-2 flex justify-between items-center">
                        <span class="text-neutral-600">Room Base Rate:</span>
                        <span class="font-semibold text-neutral-700">₱<?= number_format($basePrice, 2) ?></span>
                    </div>
                    <div class="py-2 flex justify-between items-center text-neutral-400">
                        <span class="text-[11px]">Cached Database Balance:</span>
                        <span class="font-mono text-[11px]">₱<?= number_format($cachedBalance, 2) ?> (Synchronized)</span>
                    </div>
                </div>
            </div>

            <!-- Right Card: Payment Summary -->
            <div class="reveal-card profile-card metric-accent-success space-y-4">
                <div class="flex items-center justify-between">
                    <p class="profile-section-tag">Verified Payments &amp; Submissions</p>
                    <span class="badge badge-success text-[10px]">Verified</span>
                </div>

                <div class="flex items-baseline gap-2.5">
                    <span class="text-3xl font-extrabold text-emerald-700">
                        ₱<?= number_format($totalPaid, 2) ?>
                    </span>
                    <span class="text-caption text-neutral-500 font-medium">Total Verified Payments</span>
                </div>

                <div class="divide-y divide-neutral-100 text-xs pt-1">
                    <div class="py-2 flex justify-between items-center">
                        <span class="text-neutral-600">Total Payment Submissions:</span>
                        <span class="font-bold text-neutral-900"><?= $paymentCount ?> submission<?= $paymentCount !== 1 ? 's' : '' ?></span>
                    </div>
                    <div class="py-2 flex justify-between items-center">
                        <span class="text-neutral-600">Pending Verification:</span>
                        <span class="font-bold <?= $pendingPayments > 0 ? 'text-amber-600' : 'text-neutral-500' ?>"><?= $pendingPayments ?> pending</span>
                    </div>
                    <div class="py-2 flex justify-between items-center">
                        <span class="text-neutral-600">Reviewed:</span>
                        <span class="font-semibold text-neutral-700"><?= max(0, $paymentCount - $pendingPayments) ?> recorded</span>
                    </div>
                    <div class="py-2 flex justify-end items-center">
                        <a href="/admin/payments" class="text-xs font-semibold text-primary-700 hover:underline">View in payments ledger →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 4: Resident Penalties & Inline Issue Form -->
    <div class="reveal-card profile-card space-y-4" style="margin-top: 2rem;">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-neutral-100">
            <div class="flex items-center gap-2.5">
                <span class="profile-card-icon">⚖️</span>
                <div>
                    <h2 class="text-heading-sm font-semibold text-neutral-900">Penalties &amp; Violations</h2>
                    <p class="text-caption text-neutral-500">Manual penalties, system late fees, and settlement status</p>
                </div>
            </div>
            <button type="button" id="toggle-issue-penalty-btn" class="profile-btn profile-btn-secondary !text-xs font-semibold text-primary-700 hover:text-primary-900 cursor-pointer self-start sm:self-auto">
                + Issue Manual Penalty
            </button>
        </div>

        <!-- Collapsible Issue Penalty Form -->
        <div id="issue-penalty-form-wrapper" class="hidden bg-neutral-50/90 rounded-2xl p-5 border border-neutral-200">
            <h3 class="text-xs font-bold text-neutral-900 uppercase tracking-wider mb-4">Issue New Penalty to <?= htmlspecialchars($boarder['name']) ?></h3>
            <form method="post" action="/admin/penalties/issue" class="space-y-4">
                <?= \App\Support\Csrf::field() ?>
                <input type="hidden" name="return_to" value="/admin/boarders/<?= $id ?>">
                <input type="hidden" name="boarder_id" value="<?= $id ?>">

                <div class="penalty-form-grid">
                    <div class="profile-field">
                        <label class="profile-label">Penalty Rule <span class="text-error-500">*</span></label>
                        <select name="rule_id" id="penalty-rule-select" required class="profile-input">
                            <option value="">-- Select Violation Rule --</option>
                            <?php foreach ($penaltyRules as $rule): ?>
                                <option value="<?= (int) $rule['id'] ?>" data-amount="<?= htmlspecialchars($rule['amount']) ?>">
                                    <?= htmlspecialchars($rule['name']) ?> (₱<?= number_format((float) $rule['amount'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="profile-field">
                        <label class="profile-label">Amount (₱) <span class="text-error-500">*</span></label>
                        <input type="number" step="0.01" min="1" name="amount" id="penalty-amount-input" required class="profile-input" placeholder="0.00">
                    </div>

                    <div class="profile-field">
                        <label class="profile-label">Due Date</label>
                        <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="profile-input">
                    </div>

                    <div class="profile-field penalty-form-full-col">
                        <label class="profile-label">Reason / Specific Violation Details</label>
                        <input type="text" name="reason" placeholder="Specific incident details, incident location, or context..." maxlength="255" class="profile-input">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-neutral-200/80">
                    <button type="button" id="cancel-issue-penalty-btn" class="profile-btn profile-btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="profile-btn profile-btn-primary">
                        Issue Penalty
                    </button>
                </div>
            </form>
        </div>

        <!-- Penalties List / Table -->
        <?php if (empty($penalties)): ?>
            <div class="text-center py-8 text-neutral-400 text-xs">
                <span style="font-size:1.75rem;display:block;margin-bottom:0.35rem;">✨</span>
                No penalties or violations recorded for this resident.
            </div>
        <?php else: ?>
            <div class="profile-table-container">
                <table class="profile-table">
                    <thead>
                        <tr>
                            <th>Rule / Violation</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Applied</th>
                            <th>Reason</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($penalties as $p): ?>
                            <?php
                            $isPaid = ($p['status'] ?? '') === 'paid';
                            $pBadge = $isPaid ? 'badge-success' : 'badge-error';
                            ?>
                            <tr>
                                <td class="font-semibold text-neutral-900">
                                    <?= htmlspecialchars($p['rule_name'] ?? 'Violation') ?>
                                    <div class="text-[10px] text-neutral-400 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $p['rule_type'] ?? 'rule')) ?></div>
                                </td>
                                <td class="font-bold text-neutral-900">
                                    ₱<?= number_format((float) $p['amount'], 2) ?>
                                </td>
                                <td class="text-neutral-600">
                                    <?= !empty($p['due_date']) ? date('M j, Y', strtotime($p['due_date'])) : '—' ?>
                                </td>
                                <td>
                                    <span class="badge <?= $pBadge ?> capitalize font-semibold"><?= htmlspecialchars($p['status']) ?></span>
                                </td>
                                <td class="text-neutral-500">
                                    <?= !empty($p['applied_at']) ? date('M j, Y', strtotime($p['applied_at'])) : '—' ?>
                                </td>
                                <td class="text-neutral-600 max-w-xs" title="<?= htmlspecialchars($p['reason'] ?? '') ?>">
                                    <?= htmlspecialchars($p['reason'] ?? '—') ?>
                                </td>
                                <td style="text-align:right;">
                                    <?php if (!$isPaid): ?>
                                        <form method="post" action="/admin/penalties/<?= (int) $p['id'] ?>/mark-paid" class="inline">
                                            <?= \App\Support\Csrf::field() ?>
                                            <input type="hidden" name="return_to" value="/admin/boarders/<?= $id ?>">
                                            <button type="submit" class="profile-btn profile-btn-secondary !py-1 !px-2.5 !text-xs font-semibold text-emerald-700 hover:text-emerald-900 hover:bg-emerald-50 cursor-pointer">
                                                Mark Paid
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-caption text-neutral-400 font-medium">Settled ✓</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section 5: Payment History -->
    <div class="reveal-card profile-card space-y-4" style="margin-top: 2rem;">
        <div class="flex items-center justify-between pb-3 border-b border-neutral-100">
            <div class="flex items-center gap-2.5">
                <span class="profile-card-icon">💳</span>
                <div>
                    <h2 class="text-heading-sm font-semibold text-neutral-900">Payment History</h2>
                    <p class="text-caption text-neutral-500">All submitted rent and utility payments for this account</p>
                </div>
            </div>

            <div>
                <?php if ($isShowingAllPayments): ?>
                    <a href="/admin/boarders/<?= $id ?>" class="text-xs font-semibold text-primary-700 hover:underline">
                        Show recent only (12)
                    </a>
                <?php else: ?>
                    <?php if ($paymentCount > 12): ?>
                        <a href="/admin/boarders/<?= $id ?>?payments=all" class="text-xs font-semibold text-primary-700 hover:underline">
                            Show all payments (<?= $paymentCount ?>) ↗
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($payments)): ?>
            <div class="text-center py-8 text-neutral-400 text-xs">
                <span style="font-size:1.75rem;display:block;margin-bottom:0.35rem;">📄</span>
                No payments have been submitted yet by this resident.
            </div>
        <?php else: ?>
            <div class="profile-table-container">
                <table class="profile-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Claimed</th>
                            <th>Expected</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Verified By</th>
                            <th style="text-align:right;">Proof</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $pm): ?>
                            <?php
                            $vStatus = $pm['verification_status'] ?? 'pending';
                            $vBadge = match($vStatus) {
                                'admin-approved', 'auto-matched' => 'badge-success',
                                'pending'                       => 'badge-warning',
                                'rejected'                      => 'badge-error',
                                default                         => 'badge-neutral',
                            };
                            ?>
                            <tr>
                                <td class="font-bold text-neutral-900">
                                    <?= htmlspecialchars($pm['billing_period'] ?? '—') ?>
                                </td>
                                <td class="font-semibold text-neutral-900">
                                    ₱<?= number_format((float) ($pm['claimed_amount'] ?? 0), 2) ?>
                                </td>
                                <td class="text-neutral-600">
                                    ₱<?= number_format((float) ($pm['expected_amount'] ?? 0), 2) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $vBadge ?> font-semibold"><?= htmlspecialchars(\App\Models\Payment::statusLabel($vStatus)) ?></span>
                                </td>
                                <td class="text-neutral-500">
                                    <?= !empty($pm['created_at']) ? date('M j, Y g:i A', strtotime($pm['created_at'])) : '—' ?>
                                </td>
                                <td class="text-neutral-700">
                                    <?= htmlspecialchars($pm['verifier_name'] ?? '—') ?>
                                </td>
                                <td style="text-align:right;">
                                    <?php if (!empty($pm['proof_path'])): ?>
                                        <?php $proofUrl = str_starts_with($pm['proof_path'], '/') ? $pm['proof_path'] : '/' . $pm['proof_path']; ?>
                                        <a href="<?= htmlspecialchars($proofUrl) ?>" target="_blank" rel="noopener noreferrer" class="text-primary-700 hover:text-primary-900 hover:underline font-semibold text-[11px] inline-flex items-center gap-1">
                                            Receipt ↗
                                        </a>
                                    <?php else: ?>
                                        <span class="text-neutral-400 text-[11px]">None</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section 6: Resident Activity (Maintenance & Incidents) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" style="margin-top: 2rem;">

        <!-- Maintenance Requests Column -->
        <div class="reveal-card profile-card space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-neutral-100">
                <div class="flex items-center gap-2.5">
                    <span class="profile-card-icon">🔧</span>
                    <h2 class="text-heading-sm font-semibold text-neutral-900">Maintenance Requests</h2>
                </div>
                <a href="/staff/maintenance/history" class="text-xs font-semibold text-primary-700 hover:underline">
                    View All ↗
                </a>
            </div>

            <?php if (empty($maintenance)): ?>
                <div class="text-center py-8 text-neutral-400 text-xs">
                    No maintenance requests submitted.
                </div>
            <?php else: ?>
                <div class="divide-y divide-neutral-100 text-xs">
                    <?php foreach ($maintenance as $m): ?>
                        <div class="py-3 space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-neutral-900 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $m['category'] ?? 'General')) ?></span>
                                <span class="badge <?= ($m['status'] ?? '') === 'resolved' ? 'badge-success' : 'badge-warning' ?> capitalize text-[10px]">
                                    <?= htmlspecialchars($m['status'] ?? 'pending') ?>
                                </span>
                            </div>
                            <p class="text-neutral-600 line-clamp-2 leading-relaxed"><?= htmlspecialchars($m['description'] ?? '') ?></p>
                            <div class="flex items-center justify-between text-[11px] text-neutral-400 pt-0.5">
                                <span>Room <?= htmlspecialchars($m['room_number'] ?? ($boarder['room_number'] ?? '—')) ?></span>
                                <span><?= !empty($m['created_at']) ? date('M j, Y', strtotime($m['created_at'])) : '' ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Reported Incidents Column -->
        <div class="reveal-card profile-card space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-neutral-100">
                <div class="flex items-center gap-2.5">
                    <span class="profile-card-icon">🚨</span>
                    <h2 class="text-heading-sm font-semibold text-neutral-900">Reported Incidents</h2>
                </div>
                <a href="/staff/incidents/history" class="text-xs font-semibold text-primary-700 hover:underline">
                    View All ↗
                </a>
            </div>

            <?php if (empty($incidents)): ?>
                <div class="text-center py-8 text-neutral-400 text-xs">
                    No incidents reported by this resident.
                </div>
            <?php else: ?>
                <div class="divide-y divide-neutral-100 text-xs">
                    <?php foreach ($incidents as $inc): ?>
                        <div class="py-3 space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-neutral-900"><?= htmlspecialchars($inc['title'] ?? 'Incident') ?></span>
                                <span class="badge <?= ($inc['status'] ?? '') === 'resolved' ? 'badge-success' : 'badge-error' ?> capitalize text-[10px]">
                                    <?= htmlspecialchars($inc['status'] ?? 'open') ?>
                                </span>
                            </div>
                            <p class="text-neutral-600 line-clamp-2 leading-relaxed"><?= htmlspecialchars($inc['description'] ?? '') ?></p>
                            <div class="flex items-center justify-between text-[11px] text-neutral-400 pt-0.5">
                                <span>Severity: <strong class="capitalize font-semibold"><?= htmlspecialchars($inc['severity'] ?? 'medium') ?></strong></span>
                                <span><?= !empty($inc['created_at']) ? date('M j, Y', strtotime($inc['created_at'])) : '' ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Section 7: Status Change Timeline -->
    <div class="reveal-card profile-card space-y-5" style="margin-top: 2rem;">
        <div class="flex items-center justify-between pb-3 border-b border-neutral-100">
            <div class="flex items-center gap-2.5">
                <span class="profile-card-icon">⏱️</span>
                <div>
                    <h2 class="text-heading-sm font-semibold text-neutral-900">Status Change Timeline &amp; Audit Trail</h2>
                    <p class="text-caption text-neutral-500">Chronological history of residency status transitions and administrative reasons</p>
                </div>
            </div>
            <span class="text-caption text-neutral-400 font-mono"><?= count($statusLog) ?> transition<?= count($statusLog) !== 1 ? 's' : '' ?></span>
        </div>

        <div class="profile-timeline">
            <?php if (!empty($statusLog)): ?>
                <?php foreach ($statusLog as $log): ?>
                    <div class="profile-timeline-node">
                        <span class="profile-timeline-dot"></span>
                        <div class="profile-timeline-card text-xs">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="badge badge-neutral text-[10px] capitalize"><?= htmlspecialchars(str_replace('_', ' ', $log['old_status'] ?? 'start')) ?></span>
                                    <span class="text-neutral-400 font-bold">→</span>
                                    <span class="badge badge-primary text-[10px] capitalize font-bold"><?= htmlspecialchars(str_replace('_', ' ', $log['new_status'])) ?></span>
                                </div>
                                <span class="text-neutral-400 text-[11px]">
                                    <?= date('M j, Y g:i A', strtotime($log['created_at'])) ?>
                                </span>
                            </div>

                            <p class="text-neutral-700 font-medium pt-1">
                                <?= htmlspecialchars($log['reason'] ?? 'No reason provided') ?>
                            </p>

                            <div class="text-[11px] text-neutral-400">
                                Changed by: <span class="font-semibold text-neutral-600"><?= htmlspecialchars($log['changer_name'] ?? 'System') ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Initial Account Creation Node -->
            <div class="profile-timeline-node">
                <span class="profile-timeline-dot created"></span>
                <div class="profile-timeline-card bg-white border-dashed text-xs">
                    <div class="flex items-center justify-between text-neutral-500 font-semibold">
                        <span>Resident Account Created</span>
                        <span class="text-[11px] text-neutral-400 font-normal">
                            <?= date('M j, Y g:i A', strtotime($boarder['user_created_at'] ?? 'now')) ?>
                        </span>
                    </div>
                    <p class="text-[11px] text-neutral-400">User registered with email <?= htmlspecialchars($boarder['email']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 8: Internal Admin Notes (Private Scratchpad) -->
    <div class="reveal-card profile-card space-y-4" style="margin-top: 2rem;">
        <div class="flex items-center justify-between pb-3 border-b border-neutral-100">
            <div class="flex items-center gap-2.5">
                <span class="profile-card-icon">📝</span>
                <div>
                    <h2 class="text-heading-sm font-semibold text-neutral-900">Internal Admin Notes (Private Scratchpad)</h2>
                    <p class="text-caption text-neutral-500">Persistent private scratchpad visible only to staff and admins. Not shown to the resident.</p>
                </div>
            </div>
            <span class="badge badge-neutral text-xs">Private</span>
        </div>

        <form method="post" action="/admin/boarders/<?= $id ?>/notes" class="space-y-3">
            <?= \App\Support\Csrf::field() ?>
            <div>
                <textarea name="notes" id="admin-notes-textarea" rows="4" maxlength="10000"
                          placeholder="Type internal notes regarding payment agreements, conduct, special accommodations, or staff reminders..."
                          class="profile-textarea"><?= htmlspecialchars($boarder['notes'] ?? '') ?></textarea>
                <div class="flex items-center justify-between text-[11px] text-neutral-400 mt-1.5">
                    <span>Markdown/plain text accepted. Maximum 10,000 characters.</span>
                    <span id="notes-char-counter">0 / 10,000</span>
                </div>
            </div>

            <div class="flex justify-end pt-1">
                <button type="submit" class="profile-btn profile-btn-primary">
                    Save Internal Notes
                </button>
            </div>
        </form>
    </div>

    <!-- Section 9: Danger Zone -->
    <div class="reveal-card profile-danger-card" style="margin-top: 2rem;">
        <div class="flex items-center gap-2.5 pb-3 border-b border-error-200">
            <span class="text-xl text-error-600">⚠️</span>
            <div>
                <h2 class="text-heading-sm font-bold text-error-900">Danger Zone</h2>
                <p class="text-caption text-error-700">Archive or delete this resident</p>
            </div>
        </div>

        <div class="profile-danger-content text-xs">
            <div class="text-neutral-600 max-w-xl leading-relaxed">
                Removes <strong><?= htmlspecialchars($boarder['name']) ?></strong>: frees their bed and disables login. Residents with payment or penalty records are <strong>archived</strong> (records kept, restorable from the resident list); others are deleted.
            </div>
            <button type="button" id="delete-boarder-trigger-btn" class="btn-danger !py-2.5 !px-4 !text-xs font-semibold self-start sm:self-auto cursor-pointer">
                Remove Resident
            </button>
        </div>
    </div>

</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs hidden" role="dialog" aria-modal="true">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-neutral-200 space-y-4">
        <div class="flex items-center gap-3 text-error-600">
            <span style="font-size:1.75rem;">⚠️</span>
            <div>
                <h3 class="text-heading-sm font-bold text-neutral-900">Confirm Removal</h3>
                <p class="text-caption text-error-700">Archived residents can be restored; deleted ones cannot</p>
            </div>
        </div>

        <p class="text-xs text-neutral-600 leading-relaxed">
            Remove the resident <strong class="text-neutral-900"><?= htmlspecialchars($boarder['name']) ?></strong> (<code class="text-xs"><?= htmlspecialchars($boarder['email']) ?></code>)?
            Their bed will be set to vacant immediately.
        </p>

        <form method="post" action="/admin/boarders/<?= $id ?>/delete" class="space-y-4">
            <?= \App\Support\Csrf::field() ?>
            <div class="flex items-center justify-end gap-3 pt-3 border-t border-neutral-100">
                <button type="button" id="cancel-delete-btn" class="profile-btn profile-btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn-danger !py-2 !px-4 !text-xs font-bold cursor-pointer">
                    Yes, Remove
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    // 1. Issue Penalty Form Toggle & Rule Auto-populate
    const togglePenaltyBtn = document.getElementById('toggle-issue-penalty-btn');
    const penaltyFormWrapper = document.getElementById('issue-penalty-form-wrapper');
    const cancelPenaltyBtn = document.getElementById('cancel-issue-penalty-btn');
    const ruleSelect = document.getElementById('penalty-rule-select');
    const amountInput = document.getElementById('penalty-amount-input');

    if (togglePenaltyBtn && penaltyFormWrapper) {
        togglePenaltyBtn.addEventListener('click', function() {
            penaltyFormWrapper.classList.toggle('hidden');
            if (!penaltyFormWrapper.classList.contains('hidden') && ruleSelect) {
                ruleSelect.focus();
            }
        });
    }

    if (cancelPenaltyBtn && penaltyFormWrapper) {
        cancelPenaltyBtn.addEventListener('click', function() {
            penaltyFormWrapper.classList.add('hidden');
        });
    }

    if (ruleSelect && amountInput) {
        ruleSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const amt = opt ? opt.getAttribute('data-amount') : null;
            if (amt) {
                amountInput.value = parseFloat(amt).toFixed(2);
            }
        });
    }

    // 2. Character counter for notes
    const notesArea = document.getElementById('admin-notes-textarea');
    const charCounter = document.getElementById('notes-char-counter');
    if (notesArea && charCounter) {
        const updateCount = () => {
            const len = notesArea.value.length;
            charCounter.textContent = len.toLocaleString() + ' / 10,000';
            charCounter.className = len > 9500 ? 'text-error-600 font-bold' : 'text-neutral-400';
        };
        notesArea.addEventListener('input', updateCount);
        updateCount();
    }

    // 3. Delete Confirmation Modal
    const deleteTrigger = document.getElementById('delete-boarder-trigger-btn');
    const deleteModal = document.getElementById('delete-modal');
    const cancelDelete = document.getElementById('cancel-delete-btn');

    if (deleteTrigger && deleteModal) {
        deleteTrigger.addEventListener('click', function() {
            deleteModal.classList.remove('hidden');
        });
    }

    if (cancelDelete && deleteModal) {
        cancelDelete.addEventListener('click', function() {
            deleteModal.classList.add('hidden');
        });
    }

    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === deleteModal) {
                deleteModal.classList.add('hidden');
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !deleteModal.classList.contains('hidden')) {
                deleteModal.classList.add('hidden');
            }
        });
    }
})();

if (window.gsap && window.ScrollTrigger) {
    let mm = gsap.matchMedia();
    mm.add({
        animate: "(prefers-reduced-motion: no-preference)",
        reduce:  "(prefers-reduced-motion: reduce)",
    }, (context) => {
        if (context.conditions.animate) {
            gsap.from(".reveal-card", {
                y: 16, opacity: 0, duration: 0.45, stagger: 0.06, ease: "power2.out",
                scrollTrigger: { trigger: ".reveal-card", start: "top 95%", once: true },
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
