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
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1>Rooms &amp; Beds</h1>
            <p>Physical property configuration, capacity management &amp; bed inventory</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#8cc2a2; border:1px solid rgba(255,255,255,0.18); padding:0.4rem 0.85rem; font-size:0.75rem;">
            <span style="width:0.45rem;height:0.45rem;border-radius:50%;background:#5fae84;display:inline-block;margin-right:0.4rem;"></span>
            Inventory Active
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

    <!-- KPI Cards (4 Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="reveal-card card p-4 metric-accent-primary">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Rooms</span>
                <span style="font-size:1.1rem; line-height:1;">🏢</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $totalRooms ?></p>
            <p class="text-caption text-neutral-400 mt-1">Configured units</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Capacity</span>
                <span style="font-size:1.1rem; line-height:1;">🛏️</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $totalBeds ?></p>
            <p class="text-caption text-neutral-400 mt-1">Total bed slots</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Occupied Beds</span>
                <span style="font-size:1.1rem; line-height:1;">👥</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $occupiedBeds ?></p>
            <p class="text-caption text-amber-600 mt-1 font-semibold"><?= $occupancyRate ?>% filled</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Vacant Beds</span>
                <span style="font-size:1.1rem; line-height:1;">🟢</span>
            </div>
            <p class="text-3xl font-bold <?= $vacantBeds === 0 ? 'text-error-600' : 'text-neutral-900' ?> mt-1" style="font-variant-numeric: tabular-nums;"><?= $vacantBeds ?></p>
            <p class="text-caption text-neutral-400 mt-1">Ready for occupancy</p>
        </div>
    </div>

    <!-- Room Configurations Section -->
    <div>
        <div class="section-header">
            <h2>Room Configurations</h2>
            <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-3 py-1 rounded-full">
                <?= $totalRooms ?> room<?= $totalRooms !== 1 ? 's' : '' ?> configured
            </span>
        </div>

        <div class="card overflow-hidden">
            <?php if (empty($rooms)): ?>
            <div class="empty-state" style="padding:3.5rem 0;">
                <span style="font-size:2.5rem; margin-bottom: 0.5rem;">🏠</span>
                <span class="text-sm font-semibold text-neutral-800">No rooms configured yet</span>
                <span class="text-xs text-neutral-500 mt-1">Use the "Add Room" form below to configure your first room.</span>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm" style="min-width: 680px;">
                    <thead>
                        <tr>
                            <th style="width: 18%;">Room #</th>
                            <th style="width: 16%;">Floor</th>
                            <th style="width: 18%;">Capacity</th>
                            <th style="width: 26%;">Base Price / mo</th>
                            <th style="width: 22%; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $r): ?>
                        <tr>
                            <td>
                                <?php $csrf_field = \App\Support\Csrf::field(); ?>
                                <form id="room-update-<?= (int)$r['id'] ?>" method="post" action="/admin/rooms/<?= (int) $r['id'] ?>/update">
                                    <?= $csrf_field ?>
                                </form>
                                <input form="room-update-<?= (int)$r['id'] ?>" name="room_number" value="<?= htmlspecialchars($r['room_number']) ?>"
                                       class="input input-sm font-bold text-neutral-900" style="width:5.5rem;" title="Room number">
                            </td>
                            <td>
                                <input form="room-update-<?= (int)$r['id'] ?>" name="floor" value="<?= htmlspecialchars($r['floor'] ?? '') ?>"
                                       class="input input-sm" style="width:4.5rem;" placeholder="—" title="Floor">
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <input form="room-update-<?= (int)$r['id'] ?>" name="capacity" type="number" min="1" value="<?= (int) $r['capacity'] ?>"
                                           class="input input-sm font-semibold" style="width:4.5rem;" title="Capacity">
                                    <span class="text-caption text-neutral-400">beds</span>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-neutral-500 font-semibold text-xs">₱</span>
                                    <input form="room-update-<?= (int)$r['id'] ?>" name="base_price" type="number" step="0.01" value="<?= htmlspecialchars($r['base_price']) ?>"
                                           class="input input-sm font-semibold font-mono" style="width:7.5rem;" title="Base price">
                                </div>
                            </td>
                            <td>
                                <div class="action-cluster" style="justify-content: flex-end;">
                                    <button type="submit" form="room-update-<?= (int)$r['id'] ?>" class="btn btn-primary !py-1 !px-3 !text-xs">
                                        Save
                                    </button>
                                    <form method="post" action="/admin/rooms/<?= (int) $r['id'] ?>/delete"
                                          onsubmit="return confirm('Delete Room <?= htmlspecialchars($r['room_number']) ?>? Only allowed if it has no beds.');">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn-danger !py-1 !px-3 !text-xs" title="Delete room">Delete</button>
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
    </div>

    <!-- Beds Directory Section -->
    <div>
        <div class="section-header">
            <h2>Beds Directory</h2>
            <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-3 py-1 rounded-full">
                <?= $totalBeds ?> bed<?= $totalBeds !== 1 ? 's' : '' ?> &bull; <?= $vacantBeds ?> vacant
            </span>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm" style="min-width: 720px;">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Room</th>
                            <th style="width: 18%;">Capacity</th>
                            <th style="width: 20%;">Occupancy</th>
                            <th style="width: 17%;">Status</th>
                            <th style="width: 20%; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Group beds by room
                        $bedsByRoom = [];
                        foreach ($beds as $b) {
                            $roomId = $b['room_id'];
                            if (!isset($bedsByRoom[$roomId])) {
                                $bedsByRoom[$roomId] = [
                                    'room_number' => $b['room_number'],
                                    'room_id' => $roomId,
                                    'beds' => []
                                ];
                            }
                            $bedsByRoom[$roomId]['beds'][] = $b;
                        }
                        
                        foreach ($rooms as $r): 
                            $roomBeds = $bedsByRoom[$r['id']]['beds'] ?? [];
                            $roomOccupied = count(array_filter($roomBeds, fn($b) => $b['status'] === 'occupied'));
                            $roomVacant = count($roomBeds) - $roomOccupied;
                            $roomCapacity = count($roomBeds);
                        ?>
                        <!-- Main Room Row -->
                        <tr class="room-main-row transition-colors cursor-pointer" id="room-row-<?= (int) $r['id'] ?>" data-id="<?= (int) $r['id'] ?>" title="Click row to view bed slots">
                            <td class="font-bold text-neutral-900">
                                <span class="inline-flex items-center gap-1.5">
                                    <span style="font-size:0.95rem;">🚪</span>
                                    Room <?= htmlspecialchars($r['room_number']) ?>
                                </span>
                            </td>
                            <td class="text-neutral-700 font-medium"><?= $roomCapacity ?> bed<?= $roomCapacity !== 1 ? 's' : '' ?></td>
                            <td>
                                <span class="badge badge-info font-medium" style="font-variant-numeric: tabular-nums;">
                                    <?= $roomOccupied ?> / <?= $roomCapacity ?> occupied
                                </span>
                            </td>
                            <td>
                                <?php if ($roomVacant > 0): ?>
                                    <span class="badge badge-success"><?= $roomVacant ?> Vacant</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Fully Occupied</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-cluster" style="justify-content: flex-end;">
                                    <button type="button" class="btn btn-secondary !py-1 !px-3 !text-xs beds-toggle-btn cursor-pointer" data-id="<?= (int) $r['id'] ?>">
                                        Manage Beds
                                    </button>
                                    <form method="post" action="/admin/rooms/<?= (int) $r['id'] ?>/delete" class="inline"
                                          onsubmit="return confirm('Delete Room <?= htmlspecialchars($r['room_number']) ?>? Only allowed if it has no beds.');">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn-danger !py-1 !px-3 !text-xs" title="Delete room">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Slide-Down Beds Drawer Row -->
                        <tr id="beds-row-<?= (int) $r['id'] ?>" class="hidden beds-drawer-row" style="background-color: #f8f7f5;">
                            <td colspan="5" style="padding: 0.75rem 1.25rem 1.25rem;">
                                <div class="bg-white rounded-xl border border-neutral-200 p-4 shadow-xs">
                                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-neutral-100">
                                        <div class="flex items-center gap-2">
                                            <span style="font-size:1.1rem; line-height: 1;">🛏️</span>
                                            <h4 class="text-body-sm font-semibold text-neutral-900">
                                                Beds in Room <?= htmlspecialchars($r['room_number']) ?> &mdash; <span class="text-primary-700 font-bold"><?= $roomCapacity ?> bed<?= $roomCapacity !== 1 ? 's' : '' ?></span>
                                            </h4>
                                        </div>
                                        <span class="text-caption text-neutral-400">Click row above to close</span>
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="w-full text-body-sm">
                                            <thead>
                                                <tr style="background:#f1f0ee; border-bottom:1px solid #e6e5e2;">
                                                    <th style="padding:0.5rem 0.75rem; font-size:0.65rem;">Bed Label</th>
                                                    <th style="padding:0.5rem 0.75rem; font-size:0.65rem;">Status</th>
                                                    <th style="padding:0.5rem 0.75rem; font-size:0.65rem;">Assigned Boarder</th>
                                                    <th style="padding:0.5rem 0.75rem; font-size:0.65rem; text-align: right;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($roomBeds as $b): ?>
                                                <tr>
                                                    <td style="padding:0.5rem 0.75rem;" class="font-semibold text-neutral-800">
                                                        <?= htmlspecialchars($b['label']) ?>
                                                    </td>
                                                    <td style="padding:0.5rem 0.75rem;">
                                                        <span class="badge <?= $b['status'] === 'occupied' ? 'badge-warning' : 'badge-success' ?>">
                                                            <?= htmlspecialchars($b['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding:0.5rem 0.75rem;">
                                                        <?php if (!empty($b['boarder_name'])): ?>
                                                            <span class="text-neutral-900 font-semibold text-xs"><?= htmlspecialchars($b['boarder_name']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-neutral-400 text-xs italic">Vacant slot</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding:0.5rem 0.75rem; text-align: right;">
                                                        <form method="post" action="/admin/beds/<?= (int) $b['id'] ?>/delete" class="inline"
                                                              onsubmit="return confirm('Delete this bed? Only allowed if vacant.');">
                                                            <?= \App\Support\Csrf::field() ?>
                                                            <button class="btn-danger !py-0.5 !px-2.5 !text-xs" title="Delete bed">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($roomBeds)): ?>
                                                <tr>
                                                    <td colspan="4" class="p-4 text-center text-neutral-400 text-xs italic">
                                                        No beds configured in this room yet. Use the "Add Bed" form below.
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
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Management Forms Section -->
    <div>
        <div class="section-header">
            <h2>Management Actions</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

            <!-- Card 1: Add Room Form -->
            <form method="post" action="/admin/rooms" class="form-card">
                <div class="flex items-center gap-2 pb-2 border-b border-neutral-100">
                    <span style="font-size:1.1rem; line-height:1;">🏠</span>
                    <h3 class="text-body font-semibold text-neutral-900">Add Room</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Room Number</label>
                    <input name="room_number" placeholder="e.g. 101" required class="input">
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Floor <span class="font-normal text-neutral-400">(optional)</span></label>
                    <input name="floor" placeholder="e.g. 1" class="input">
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Capacity</label>
                    <input name="capacity" type="number" min="1" value="2" required class="input">
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Monthly Base Price (₱)</label>
                    <input name="base_price" type="number" step="0.01" value="3500" required class="input font-mono font-semibold">
                </div>
                <button class="btn btn-primary w-full !py-2 !text-xs font-semibold">
                    Create Room Unit
                </button>
            </form>

            <!-- Card 2: Add Bed Form -->
            <form method="post" action="/admin/beds" class="form-card accent-success">
                <div class="flex items-center gap-2 pb-2 border-b border-neutral-100">
                    <span style="font-size:1.1rem; line-height:1;">🛏️</span>
                    <h3 class="text-body font-semibold text-neutral-900">Add Bed</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Select Room</label>
                    <select name="room_id" id="add-bed-room" required class="input">
                        <option value="">— Choose Room —</option>
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?= (int) $r['id'] ?>">Room <?= htmlspecialchars($r['room_number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Number of Beds</label>
                    <input type="number" name="count" id="add-bed-count" min="1" max="50" value="1" required class="input font-mono" placeholder="1">
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Bed Label / Prefix</label>
                    <input name="label" id="add-bed-label" placeholder="e.g. Bed A, Bed 1, or Bed" required class="input">
                    <p class="text-[11px] text-neutral-400 mt-0.5">Numbers or letters sequence automatically for batches.</p>
                </div>
                <div id="add-bed-preview" class="hidden text-caption text-neutral-600 bg-neutral-50 rounded-md p-2.5 border border-neutral-200">
                    <span class="font-semibold text-neutral-700">Preview:</span> <span id="add-bed-preview-text" class="font-mono text-emerald-700 font-semibold"></span>
                </div>
                <button class="btn btn-primary w-full !py-2 !text-xs font-semibold" id="add-bed-submit">
                    Add Bed
                </button>
            </form>

            <!-- Card 3: Assign Boarder to Bed Form -->
            <form method="post" action="/admin/beds/assign" class="form-card accent-neutral">
                <div class="flex items-center gap-2 pb-2 border-b border-neutral-100">
                    <span style="font-size:1.1rem; line-height:1;">🔗</span>
                    <h3 class="text-body font-semibold text-neutral-900">Assign Boarder</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Boarder User ID</label>
                    <input name="boarder_id" type="number" placeholder="Enter user ID" required class="input">
                    <p class="text-caption text-neutral-400 mt-1">
                        Find IDs in the <a href="/admin/boarders" class="text-primary-600 font-medium hover:underline">Boarders Directory</a>.
                    </p>
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Select Vacant Bed</label>
                    <select name="bed_id" required class="input">
                        <option value="">— Choose Vacant Bed —</option>
                        <?php foreach ($beds as $b): if ($b['status'] === 'vacant'): ?>
                            <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['room_number'] . ' / ' . $b['label']) ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="text-caption text-neutral-500 bg-neutral-50 rounded-md p-2.5 border border-neutral-100 mt-auto">
                    Reassigning an active resident will safely relocate them and free their previous bed.
                </div>
                <button class="btn btn-secondary w-full !py-2 !text-xs font-semibold">
                    Assign Bed Slot
                </button>
            </form>

        </div>
    </div>
</div>

<script>
/* ── Batch Bed Label Live Preview ── */
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

/* ── Beds Toggle Drawer ── */
document.addEventListener('DOMContentLoaded', () => {
    const bedsToggleBtns = document.querySelectorAll('.beds-toggle-btn');
    const roomMainRows = document.querySelectorAll('.room-main-row');
    const bedsDrawerRows = document.querySelectorAll('.beds-drawer-row');
    
    let activeRoomId = null;
    
    function closeAllDrawers() {
        bedsDrawerRows.forEach(row => row.classList.add('hidden'));
        roomMainRows.forEach(row => row.classList.remove('bg-neutral-50'));
        activeRoomId = null;
    }
    
    function openDrawer(roomId) {
        closeAllDrawers();
        const drawerRow = document.getElementById(`beds-row-${roomId}`);
        const mainRow = document.getElementById(`room-row-${roomId}`);
        
        if (drawerRow && mainRow) {
            drawerRow.classList.remove('hidden');
            mainRow.classList.add('bg-neutral-50');
            activeRoomId = roomId;
        }
    }
    
    bedsToggleBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const roomId = btn.getAttribute('data-id');
            
            if (activeRoomId === parseInt(roomId)) {
                closeAllDrawers();
            } else {
                openDrawer(roomId);
            }
        });
    });
    
    roomMainRows.forEach(row => {
        row.addEventListener('click', () => {
            if (activeRoomId !== null) {
                closeAllDrawers();
            }
        });
    });
});

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
