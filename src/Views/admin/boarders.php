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
$totalBoarders     = count($boarders);
$activeBoarders    = count(array_filter($boarders, fn($b) => ($b['status'] ?? '') === 'active'));
$pendingBoarders   = count(array_filter($boarders, fn($b) => in_array($b['status'] ?? '', ['pending', 'on_notice'], true)));
$unassignedBoarders = count(array_filter($boarders, fn($b) => empty($b['bed_id'])));
$vacantBedsCount   = count($vacantBeds);
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Boarders</h1>
            <p class="text-body-sm">Resident directory, bed allocations &amp; account lifecycle</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Active Directory
        </span>
    </div>

    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3 border border-error-500 flex items-center gap-2" role="alert">
            <span style="font-size:1rem;">⚠</span>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-lg p-3 border border-emerald-300 flex items-center gap-2" role="alert">
            <span style="font-size:1rem;">✓</span>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="reveal-card card p-4 metric-accent-primary">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Registered</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalBoarders ?></p>
            <?php if ($unassignedBoarders > 0): ?>
                <p class="text-caption text-amber-600 mt-1 font-semibold"><?= $unassignedBoarders ?> unassigned</p>
            <?php else: ?>
                <p class="text-caption text-neutral-400 mt-1">All assigned</p>
            <?php endif; ?>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Active Residents</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $activeBoarders ?></p>
            <p class="text-caption text-neutral-400 mt-1">Current boarders</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Pending / Notice</p>
            <p class="text-3xl font-bold <?= $pendingBoarders > 0 ? 'text-amber-600' : 'text-neutral-900' ?> mt-1.5"><?= $pendingBoarders ?></p>
            <p class="text-caption text-neutral-400 mt-1">Requires review</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Vacant Beds</p>
            <p class="text-3xl font-bold <?= $vacantBedsCount === 0 ? 'text-error-600' : 'text-neutral-900' ?> mt-1.5"><?= $vacantBedsCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Ready for occupancy</p>
        </div>
    </div>

    <!-- Boarders Directory -->
    <div>
        <div class="section-header">
            <h2 class="text-heading-sm font-semibold text-neutral-900">Boarders Directory</h2>
            <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">
                <?= $totalBoarders ?> resident<?= $totalBoarders !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide" style="width:4rem;">ID</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Resident</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Assigned Bed</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Status</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody>                        <?php foreach ($boarders as $b): ?>
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
                        <tr class="border-b border-neutral-100 boarder-main-row transition-colors cursor-pointer hover:bg-neutral-50/80"
                            id="boarder-row-<?= (int) $b['user_id'] ?>"
                            data-id="<?= (int) $b['user_id'] ?>"
                            title="Click row while editing to cancel">
                            <td class="p-3">
                                <span class="id-tag">#<?= (int) $b['user_id'] ?></span>
                            </td>
                            <td class="p-3">
                                <div class="font-bold text-neutral-800 text-sm"><?= htmlspecialchars($b['name']) ?></div>
                                <div class="text-caption text-neutral-500 font-normal"><?= htmlspecialchars($b['email']) ?></div>
                                <?php if (!empty($b['contact_number'])): ?>
                                    <div class="text-[11px] text-neutral-400 mt-0.5">📞 <?= htmlspecialchars($b['contact_number']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <?php if (!empty($b['bed_id'])): ?>
                                    <span class="badge badge-info font-medium">
                                        <?= htmlspecialchars(($b['room_number'] ?? 'Room') . ' / ' . ($b['bed_label'] ?? 'Bed')) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <span class="badge <?= $badgeClass ?> capitalize"><?= htmlspecialchars(str_replace('_', ' ', $status)) ?></span>
                            </td>
                            <td class="p-3">
                                <div class="action-cluster" style="display:flex;align-items:center;gap:0.375rem;">
                                    <button type="button"
                                            class="btn btn-secondary !py-1 !px-3 !text-xs edit-toggle-btn cursor-pointer"
                                            data-id="<?= (int) $b['user_id'] ?>">
                                        Edit
                                    </button>
                                    <button type="button"
                                            class="btn-danger !py-1 !px-3 !text-xs delete-trigger-btn cursor-pointer"
                                            data-id="<?= (int) $b['user_id'] ?>"
                                            data-name="<?= htmlspecialchars($b['name']) ?>">
                                        Delete
                                    </button>
                                    <!-- Hidden delete form for confirmation submit -->
                                    <form id="delete-form-<?= (int) $b['user_id'] ?>" method="post" action="/admin/boarders/<?= (int) $b['user_id'] ?>/delete" class="hidden">
                                        <?= \App\Support\Csrf::field() ?>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Slide-Down Edit Drawer Row -->
                        <tr id="edit-row-<?= (int) $b['user_id'] ?>" class="hidden edit-drawer-row border-b border-neutral-200" style="background-color: #f8fafc;">
                            <td colspan="5" class="p-4" style="padding: 1rem 1.25rem;">
                                <div class="edit-drawer-box bg-white rounded-xl border border-neutral-200 p-4 sm:p-5 shadow-xs">
                                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-neutral-100">
                                        <div class="flex items-center gap-2">
                                            <span style="font-size:1rem;">✏️</span>
                                            <h4 class="text-body-sm font-semibold text-neutral-900">Edit Resident Details &mdash; <span class="text-primary-700 font-bold"><?= htmlspecialchars($b['name']) ?></span></h4>
                                        </div>
                                        <span class="text-caption text-neutral-400">Click row above to cancel and close</span>
                                    </div>

                                    <form id="edit-form-<?= (int) $b['user_id'] ?>" method="post" action="/admin/boarders/<?= (int) $b['user_id'] ?>/info">
                                        <?= \App\Support\Csrf::field() ?>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                                            <div>
                                                <label class="block text-caption font-semibold text-neutral-600 mb-1">Full Name</label>
                                                <input name="name" value="<?= htmlspecialchars($b['name']) ?>" required class="input input-sm">
                                            </div>
                                            <div>
                                                <label class="block text-caption font-semibold text-neutral-600 mb-1">Email Address</label>
                                                <input name="email" type="email" value="<?= htmlspecialchars($b['email']) ?>" required class="input input-sm">
                                            </div>
                                            <div>
                                                <label class="block text-caption font-semibold text-neutral-600 mb-1">Contact Number</label>
                                                <input name="contact_number" value="<?= htmlspecialchars($b['contact_number'] ?? '') ?>" placeholder="e.g. 09123456789" class="input input-sm">
                                            </div>
                                            <div>
                                                <label class="block text-caption font-semibold text-neutral-600 mb-1">Emergency Contact Number</label>
                                                <input name="emergency_contact_number" value="<?= htmlspecialchars($b['emergency_contact_number'] ?? '') ?>" placeholder="e.g. 09987654321" class="input input-sm">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                            <div>
                                                <label class="block text-caption font-semibold text-neutral-600 mb-1">Resident Status</label>
                                                <select name="status" class="input input-sm">
                                                    <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>Pending</option>
                                                    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                                                    <option value="on_notice" <?= $status === 'on_notice' ? 'selected' : '' ?>>On Notice</option>
                                                    <option value="moved_out" <?= $status === 'moved_out' ? 'selected' : '' ?>>Moved Out</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-caption font-semibold text-neutral-600 mb-1">Note (Status change reason)</label>
                                                <input name="note" placeholder="Optional notes regarding this resident" class="input input-sm">
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-neutral-100">
                                            <button type="button"
                                                    class="btn btn-secondary !py-1.5 !px-3 !text-xs cancel-edit-btn cursor-pointer"
                                                    data-id="<?= (int) $b['user_id'] ?>">
                                                Cancel
                                            </button>
                                            <button type="button"
                                                    class="btn btn-primary !py-1.5 !px-4 !text-xs update-trigger-btn cursor-pointer"
                                                    data-id="<?= (int) $b['user_id'] ?>"
                                                    data-name="<?= htmlspecialchars($b['name']) ?>">
                                                Update
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($boarders)): ?>
                        <tr>
                            <td colspan="5" class="empty-state" style="padding:3rem 0;">
                                <span style="font-size:2rem;">🏠</span>
                                <span class="text-xs font-medium text-neutral-500">No boarders registered yet.</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Management Forms -->
    <div>
        <div class="section-header">
            <h2 class="text-heading-sm font-semibold text-neutral-900">Management Actions</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <!-- Add Boarder -->
            <form method="post" action="/admin/boarders" class="form-card">
                <div class="flex items-center gap-2">
                    <span style="font-size:1rem;">👤</span>
                    <h3 class="text-body font-semibold text-neutral-900">Add Boarder</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Full Name</label>
                    <input name="name" placeholder="Juan dela Cruz" required class="input">
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Email Address</label>
                    <input name="email" type="email" placeholder="boarder@example.com" required class="input">
                </div>
                <div>
                    <label for="add-boarder-password" class="block text-caption font-semibold text-neutral-600 mb-0.5">Temporary Password</label>
                    <div class="relative flex items-center">
                        <input id="add-boarder-password"
                               name="password"
                               type="password"
                               placeholder="Min. 8 characters"
                               required
                               class="input"
                               style="padding-right: 2.25rem;">
                        <button type="button"
                                id="toggle-boarder-password"
                                class="text-neutral-400 hover:text-neutral-600 focus:outline-none p-1.5 rounded transition-colors flex items-center justify-center cursor-pointer"
                                style="position: absolute; right: 0.375rem; top: 50%; transform: translateY(-50%); background: transparent; border: none;"
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
                    <label for="add_boarder_room" class="block text-caption font-semibold text-neutral-600 mb-0.5">Room <span class="font-normal text-neutral-400">(optional)</span></label>
                    <select id="add_boarder_room" name="room_id" class="input">
                        <option value="">— Skip room assignment —</option>
                        <?php foreach ($availableRooms as $r): ?>
                            <option value="<?= (int) $r['id'] ?>">Room <?= htmlspecialchars($r['room_number']) ?> (<?= count($vacantBedsByRoom[(int)$r['id']] ?? []) ?> vacant)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="add_boarder_bed" class="block text-caption font-semibold text-neutral-600 mb-0.5">Bed <span class="font-normal text-neutral-400">(optional)</span></label>
                    <select id="add_boarder_bed" name="bed_id" class="input">
                        <option value="">— Select a room first —</option>
                        <?php foreach ($vacantBeds as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" data-room-id="<?= (int) $b['room_id'] ?>" style="display:none;">
                                Room <?= htmlspecialchars($b['room_number']) ?> — <?= htmlspecialchars($b['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary w-full">Create Boarder</button>
            </form>

            <!-- Assign Boarder to Bed -->
            <form method="post" action="/admin/beds/assign" class="form-card accent-success">
                <div class="flex items-center gap-2">
                    <span style="font-size:1rem;">🛏️</span>
                    <h3 class="text-body font-semibold text-neutral-900">Assign to Bed</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Select Boarder</label>
                    <select name="boarder_id" required class="input">
                        <option value="">— Choose Boarder —</option>
                        <?php foreach ($boarders as $b): ?>
                            <option value="<?= (int) $b['user_id'] ?>">
                                <?= htmlspecialchars($b['name']) ?> <?= !empty($b['bed_id']) ? '(Assigned)' : '(Unassigned)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Select Vacant Bed</label>
                    <select name="bed_id" required class="input">
                        <option value="">— Choose Vacant Bed —</option>
                        <?php foreach ($vacantBeds as $b): ?>
                            <option value="<?= (int) $b['id'] ?>">Room <?= htmlspecialchars($b['room_number']) ?> / Bed <?= htmlspecialchars($b['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="text-caption text-neutral-500 bg-neutral-50 rounded-md p-2.5 border border-neutral-100">
                    Assigning a new bed to an already-assigned resident will safely relocate them.
                </p>
                <button class="btn btn-secondary w-full">Assign Bed</button>
            </form>

            <!-- Operations Guide -->
            <div class="form-card accent-neutral">
                <div class="flex items-center gap-2 mb-1">
                    <span style="font-size:1.1rem;">📋</span>
                    <h3 class="text-body font-semibold text-neutral-900">Operations Guide</h3>
                </div>
                <div class="space-y-2 text-caption text-neutral-600">
                    <div class="p-2.5 rounded-md bg-neutral-50 border border-neutral-200">
                        <span class="font-semibold text-neutral-800 block mb-0.5">Automated Bed Sync:</span>
                        When a boarder is deleted, their assigned bed is automatically vacated and made available.
                    </div>
                    <div class="p-2.5 rounded-md bg-neutral-50 border border-neutral-200">
                        <span class="font-semibold text-neutral-800 block mb-0.5">Status Lifecycle:</span>
                        Residents can move through <em>pending → active → on notice → moved out</em>.
                    </div>
                    <div class="p-2.5 rounded-md bg-neutral-50 border border-neutral-200">
                        <span class="font-semibold text-neutral-800 block mb-0.5">Bed Reassignment:</span>
                        Assigning a new bed to an active resident relocates them automatically.
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Double Confirmation Popout Modal — uses ONLY inline styles (no Tailwind utility classes needed) -->
<div id="confirm-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:99999; background:rgba(15,23,42,0.55); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;">
    <div id="confirm-card" style="background:#fff; border-radius:1rem; padding:1.5rem 1.75rem; max-width:22rem; width:100%; margin:0 auto; text-align:center; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        
        <!-- Big Round Icon (!) -->
        <div id="modal-icon-wrap" style="width:3.5rem; height:3.5rem; border-radius:50%; border:2px solid #10b981; color:#059669; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:700; margin:0 auto 0.75rem;">
            !
        </div>

        <!-- Title -->
        <h3 id="modal-heading" style="font-size:1.1rem; font-weight:700; color:#0f172a; letter-spacing:-0.01em; margin:0 0 0.375rem;">Save Changes?</h3>

        <!-- Description -->
        <p id="modal-subtext" style="font-size:0.8rem; color:#64748b; line-height:1.6; margin:0 0 1.5rem; padding:0 0.5rem;">
            Are you sure you want to proceed?
        </p>

        <!-- Actions -->
        <div style="display:flex; align-items:center; justify-content:flex-end; gap:0.75rem; padding-top:0.25rem;">
            <button type="button" id="modal-cancel" style="padding:0.5rem 1rem; border-radius:0.75rem; font-size:0.75rem; font-weight:600; color:#64748b; background:transparent; border:none; cursor:pointer; transition:all 150ms;">
                Cancel
            </button>
            <button type="button" id="modal-confirm" style="color:#fff; font-weight:600; border-radius:0.75rem; padding:0.5rem 1.25rem; font-size:0.75rem; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:0.375rem; transition:all 150ms; background:linear-gradient(135deg,#10b981,#059669);">
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
        const toggleBtn    = document.getElementById('toggle-boarder-password');
        const passwordInput = document.getElementById('add-boarder-password');
        const eyeOpen      = document.getElementById('eye-open-boarder');
        const eyeClosed    = document.getElementById('eye-closed-boarder');
        if (toggleBtn && passwordInput && eyeOpen && eyeClosed) {
            toggleBtn.addEventListener('click', function() {
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

        // 5. Single delegated listener on document — bulletproof regardless of hidden/DOM order
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
                    confirmLabel: 'Yes',
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
                    title: 'Are you sure?',
                    desc:  "Are you sure you want to delete " + name + "? Their assigned bed will be vacated and records cleaned up.",
                    confirmLabel: 'Yes',
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

            // Click main boarder row to close open drawer
            const mainRow = e.target.closest('.boarder-main-row');
            if (mainRow && !e.target.closest('button, a, input, select, textarea')) {
                const id     = mainRow.getAttribute('data-id');
                const drawer = document.getElementById('edit-row-' + id);
                if (drawer && !drawer.classList.contains('hidden')) closeDrawer(id);
            }
        });

        // Escape closes modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('confirm-modal');
                if (modal && modal.style.display !== 'none') hideConfirmModal();
            }
        });
    }

    // Run immediately — by the time this inline script executes, all preceding HTML is already in the DOM
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
                y: 20, opacity: 0, duration: 0.5, stagger: 0.07, ease: "power2.out",
                scrollTrigger: { trigger: ".reveal-card", start: "top 92%", once: true },
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
