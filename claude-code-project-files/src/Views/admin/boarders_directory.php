<?php
// The Resident Directory: User Management's Boarders tab (/admin/staff?view=boarders). StaffController passes $boarders and $showArchived.
$pageTitle = 'Boarders';
ob_start();
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$totalBoarders  = count($boarders);
$activeBoarders = count(array_filter($boarders, fn($b) => ($b['status'] ?? '') === 'active'));
$pendingCount   = count(array_filter($boarders, fn($b) => ($b['status'] ?? '') === 'pending'));
$onNoticeCount  = count(array_filter($boarders, fn($b) => ($b['status'] ?? '') === 'on_notice'));
$movedOutCount  = count(array_filter($boarders, fn($b) => ($b['status'] ?? '') === 'moved_out'));
$returnTo = '/admin/staff?view=boarders' . (!empty($showArchived) ? '&archived=1' : '');
?>

<style>
/* Filter & Controls Container */
.filter-card {
    background: #ffffff;
    border: 1px solid #e6e5e2;
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
    background-color: #5c3113;
    color: #ffffff;
    border-color: #5c3113;
    box-shadow: 0 1px 2px rgba(30, 58, 138, 0.15);
}
.filter-tab-btn:not(.is-active) {
    background-color: #f8f7f5;
    color: #555452;
    border-color: #e6e5e2;
}
.filter-tab-btn:not(.is-active):hover {
    background-color: #f1f0ee;
    color: #0a0a0a;
    border-color: #d4d2ce;
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
    border: 1px solid #d4d2ce !important;
    background-color: #ffffff !important;
    color: #0a0a0a !important;
    outline: none !important;
    box-sizing: border-box !important;
    transition: border-color 150ms ease, box-shadow 150ms ease;
}
.search-input:focus {
    border-color: #bd6b36 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
}
.search-icon-fixed {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    font-size: 0.85rem;
    color: #9d9b97;
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
    color: #9d9b97;
    cursor: pointer;
    line-height: 1;
    border-radius: 0.25rem;
    transition: color 150ms;
}
.search-clear-fixed:hover {
    color: #3b3a38;
}

/* Table Card & Scroll */
.boarders-table-card {
    background: #ffffff;
    border: 1px solid #e6e5e2;
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
    background: #f8f7f5;
    border-bottom: 2px solid #e6e5e2;
}
.boarders-table th {
    padding: 0.75rem 1rem !important;
    font-size: 0.6875rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    color: #6b6b6b !important;
    background: #f8f7f5;
    vertical-align: middle;
}
.boarders-table td {
    padding: 0.75rem 1rem !important;
    vertical-align: middle;
    border-bottom: 1px solid #f1f0ee;
}
.boarders-table tbody tr.boarder-main-row {
    transition: background-color 130ms ease;
    cursor: pointer;
}
.boarders-table tbody tr.boarder-main-row:hover {
    background-color: #f8f7f5;
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
    background: #fbf4ef;
    color: #97501f;
    border-color: #ebc6ac;
}
.tbl-btn-profile:hover {
    background: #f5e3d6;
    color: #7a4119;
    border-color: #dea27c;
}
.tbl-btn-edit {
    background: #ffffff;
    color: #3b3a38;
    border-color: #d4d2ce;
}
.tbl-btn-edit:hover {
    background: #f1f0ee;
    color: #0a0a0a;
    border-color: #9d9b97;
}
.tbl-btn-delete {
    background: #fdf1f0;
    color: #912018;
    border-color: #f9dcd9;
}
.tbl-btn-delete:hover {
    background: #fee2e2;
    color: #7a1b14;
    border-color: #f87171;
}

/* Edit Drawer Styling */
.edit-drawer-cell {
    padding: 0 !important;
    background-color: #f8f7f5;
    white-space: normal !important;
    overflow: visible !important;
}
.edit-drawer-box {
    background: #ffffff;
    border: 1px solid #d4d2ce;
    border-left: 4px solid #b15f2c;
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    margin: 0.75rem 1rem 1rem;
    box-shadow: 0 4px 12px -2px rgba(10, 10, 10, 0.06);
}
.edit-drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.75rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #f1f0ee;
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


</style>
<?php require __DIR__ . '/_boarders_shared_css.php'; ?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div class="page-banner">
        <div>
            <h1>Boarders</h1>
            <p>Every resident, their bed, status and balance. Add or place a resident on the <a href="/admin/boarders" class="underline">Boarders page</a>.</p>
        </div>
        <a href="/admin/boarders#add-boarder" class="btn btn-secondary btn-sm"><?= \App\Support\Icon::svg('plus', 14) ?> Add boarder</a>
    </div>

    <?php $userTab = 'boarders'; require __DIR__ . '/_user_tabs.php'; ?>

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
                    <a href="<?= !empty($showArchived) ? '/admin/staff?view=boarders' : '/admin/staff?view=boarders&amp;archived=1' ?>" class="filter-tab-btn">
                        <?= !empty($showArchived) ? '&larr; Current residents' : 'Show archived' ?>
                    </a>
                </div>

                <!-- Live Search Box with Verified Padded Field -->
                <div class="search-wrapper">
                    <span class="search-icon-fixed"><?= \App\Support\Icon::svg('search') ?></span>
                    <input type="text" id="boarder-search" placeholder="Search by name, email, room, bed..."
                           class="search-input" autocomplete="off">
                    <button type="button" id="clear-search-btn" class="hidden search-clear-fixed" title="Clear search"><?= \App\Support\Icon::svg('x') ?></button>
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
                                        <?= \App\Support\Icon::svg('phone') ?> <?= htmlspecialchars($b['contact_number']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (($b['account_status'] ?? '') === 'archived'): ?>
                                    <div class="text-[11px] text-neutral-500 mt-0.5 archived-note">
                                        Archived <?= !empty($b['archived_at']) ? htmlspecialchars(date('M j, Y', strtotime($b['archived_at']))) : '(date not recorded)' ?><?= !empty($b['archived_by_name']) ? ' by ' . htmlspecialchars($b['archived_by_name']) : '' ?><?= !empty($b['archive_reason']) ? ' — ' . htmlspecialchars($b['archive_reason']) : '' ?>
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
                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
                                        <button type="submit" class="tbl-btn tbl-btn-edit cursor-pointer" title="Allow this resident to log in again">Restore</button>
                                    </form>
                                    <?php else: ?>
                                    <button type="button"
                                            class="tbl-btn tbl-btn-delete delete-trigger-btn cursor-pointer"
                                            data-id="<?= (int) $b['user_id'] ?>"
                                            data-name="<?= htmlspecialchars($b['name']) ?>"
                                            title="Archive resident (all records are kept; can be restored)">
                                        Remove
                                    </button>
                                    <!-- Hidden delete form for confirmation submit -->
                                    <form id="delete-form-<?= (int) $b['user_id'] ?>" method="post" action="/admin/boarders/<?= (int) $b['user_id'] ?>/delete" class="hidden">
                                        <?= \App\Support\Csrf::field() ?>
                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
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
                                            <?= \App\Support\Icon::svg('pencil', 18) ?>
                                            <h4 class="text-body-sm font-semibold text-neutral-900">
                                                Edit Resident Details &mdash; <span class="text-primary-700 font-bold"><?= htmlspecialchars($b['name']) ?></span>
                                            </h4>
                                        </div>
                                        <span class="text-caption text-neutral-400">Click row above to close</span>
                                    </div>

                                    <form id="edit-form-<?= (int) $b['user_id'] ?>" method="post" action="/admin/boarders/<?= (int) $b['user_id'] ?>/info">
                                        <?= \App\Support\Csrf::field() ?>
                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">

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
                                <span class="empty-icon"><?= \App\Support\Icon::svg('search') ?></span>
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
                                <span class="empty-icon"><?= \App\Support\Icon::svg('home') ?></span>
                                <span class="text-sm font-semibold text-neutral-800 block">No boarders registered yet</span>
                                <span class="text-xs text-neutral-500 block mt-1">Register your first resident with <a href="/admin/boarders#add-boarder" class="underline">Add Boarder</a>.</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- Double Confirmation Popout Modal -->
<div id="confirm-modal" class="modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="modal-heading">
    <div id="confirm-card" class="modal-card">
        <div id="modal-icon-wrap" class="modal-icon"><?= \App\Support\Icon::svg('alert-circle') ?></div>
        <h3 id="modal-heading" class="modal-title">Save Changes?</h3>
        <p id="modal-subtext" class="modal-text">Are you sure you want to proceed?</p>
        <div class="modal-actions">
            <button type="button" id="modal-cancel" class="btn btn-secondary">Cancel</button>
            <button type="button" id="modal-confirm" class="btn btn-primary">
                <span id="modal-confirm-label">Yes</span>
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    function initBoardersPage() {
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
            if (mainRow) mainRow.style.backgroundColor = '#f1f0ee';
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

            // Removing someone reads as a warning; saving is the ordinary primary action.
            const isDelete = cfg.type === 'delete';
            if (icon) icon.className = 'modal-icon' + (isDelete ? ' modal-icon-danger' : '');
            if (btn)  btn.className  = 'btn ' + (isDelete ? 'btn-danger-solid' : 'btn-primary');

            pendingAction = cfg.onConfirm;
            modal.style.display = 'flex'; // .modal-card's CSS entrance plays on open
            const cancel = document.getElementById('modal-cancel');
            if (cancel) cancel.focus();
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
                    desc:  "Remove " + name + "? Their bed is freed and their login disabled. They are archived, not erased: every payment, penalty, repair, incident and SOS record is kept, and you can restore them under Show archived.",
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

</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
