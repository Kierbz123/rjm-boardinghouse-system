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

    <!-- KPI Cards (4 Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="reveal-card card p-4 metric-accent-primary">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Rooms</span>
                <?= \App\Support\Icon::svg('building', 18) ?>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $totalRooms ?></p>
            <p class="text-caption text-neutral-400 mt-1">Configured units</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Capacity</span>
                <?= \App\Support\Icon::svg('bed', 18) ?>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $totalBeds ?></p>
            <p class="text-caption text-neutral-400 mt-1">Total bed slots</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Occupied Beds</span>
                <?= \App\Support\Icon::svg('users', 18) ?>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $occupiedBeds ?></p>
            <p class="text-caption text-amber-600 mt-1 font-semibold"><?= $occupancyRate ?>% filled</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Vacant Beds</span>
                <?= \App\Support\Icon::svg('circle-dot', 18) ?>
            </div>
            <p class="text-3xl font-bold <?= $vacantBeds === 0 ? 'text-error-600' : 'text-neutral-900' ?> mt-1" style="font-variant-numeric: tabular-nums;"><?= $vacantBeds ?></p>
            <p class="text-caption text-neutral-400 mt-1">Ready for occupancy</p>
        </div>
    </div>

    <!-- Room Types: the offerings advertised on the website ("Choose how you live") -->
    <div id="room-types">
        <div class="section-header">
            <h2>Room Types</h2>
            <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-3 py-1 rounded-full">What the website offers</span>
        </div>
        <p class="text-body-sm text-neutral-500 mb-3">These are the offerings shown on the public page and in the inquiry forms. Link each physical room to its type in Room Configurations below: the type then sets that room's capacity and what each resident is billed, and saving an offering updates all its rooms. Vacant beds are counted from linked rooms only.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($roomTypes as $t): ?>
            <div class="card p-4" style="display:flex; flex-direction:column; gap:0.6rem;">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider"><?= htmlspecialchars($t['capacity_label']) ?></span>
                    <?php if ($t['badge']): ?><span class="text-caption font-semibold" style="background:#fbf4ef; color:#b15f2c; padding:0.15rem 0.6rem; border-radius:999px;"><?= htmlspecialchars($t['badge']) ?></span><?php endif; ?>
                </div>
                <h3 class="text-body font-semibold text-neutral-900"><?= htmlspecialchars($t['name']) ?></h3>
                <p class="text-neutral-900 font-semibold font-mono"><?= htmlspecialchars(\App\Models\RoomType::priceLabel($t)) ?></p>
                <p class="text-caption text-neutral-500"><?= (int) $t['capacity'] === 1 ? 'Exclusive: 1 bed per room' : (int) $t['capacity'] . ' beds per room' ?> · each resident billed ₱<?= number_format(\App\Models\RoomType::ratePerBed($t), 2) ?> / month</p>
                <ul class="text-body-sm text-neutral-600" style="list-style:disc; padding-left:1.1rem;">
                    <?php foreach ($t['feature_list'] as $feature): ?><li><?= htmlspecialchars($feature) ?></li><?php endforeach; ?>
                </ul>
                <p class="text-caption <?= (int) $t['room_count'] === 0 ? 'text-amber-600' : 'text-neutral-500' ?> font-semibold">
                    <?php if ((int) $t['room_count'] === 0): ?>
                        No physical room linked yet, so nothing of this type can be assigned.
                    <?php else: ?>
                        <?= (int) $t['room_count'] ?> room<?= (int) $t['room_count'] !== 1 ? 's' : '' ?> · <?= (int) $t['bed_count'] ?> bed<?= (int) $t['bed_count'] !== 1 ? 's' : '' ?> · <?= (int) $t['vacant_bed_count'] ?> vacant
                    <?php endif; ?>
                </p>
                <details>
                    <summary class="text-caption font-semibold text-neutral-600" style="cursor:pointer;">Edit offering</summary>
                    <form method="post" action="/admin/room-types/<?= (int) $t['id'] ?>/update" style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.6rem;">
                        <?= \App\Support\Csrf::field() ?>
                        <label class="block text-caption font-semibold text-neutral-600">Name
                            <input name="name" value="<?= htmlspecialchars($t['name']) ?>" required maxlength="100" class="input"></label>
                        <label class="block text-caption font-semibold text-neutral-600">Who it is for
                            <input name="capacity_label" value="<?= htmlspecialchars($t['capacity_label']) ?>" required maxlength="60" class="input"></label>
                        <label class="block text-caption font-semibold text-neutral-600">Residents per room <span class="font-normal text-neutral-400">(beds each room of this type holds)</span>
                            <input name="capacity" type="number" min="1" max="50" value="<?= (int) $t['capacity'] ?>" required class="input font-mono"></label>
                        <div class="flex items-center gap-1.5">
                            <label class="block text-caption font-semibold text-neutral-600" style="flex:1;">Price (₱ / month)
                                <input name="price" type="number" step="0.01" min="0" value="<?= htmlspecialchars($t['price']) ?>" required class="input font-mono"></label>
                            <label class="block text-caption font-semibold text-neutral-600" style="flex:1;">Charged
                                <select name="price_unit" class="input">
                                    <option value="room" <?= $t['price_unit'] === 'room' ? 'selected' : '' ?>>per room</option>
                                    <option value="bed" <?= $t['price_unit'] === 'bed' ? 'selected' : '' ?>>per bed</option>
                                </select></label>
                        </div>
                        <label class="block text-caption font-semibold text-neutral-600">Badge <span class="font-normal text-neutral-400">(optional)</span>
                            <input name="badge" value="<?= htmlspecialchars($t['badge'] ?? '') ?>" maxlength="40" class="input"></label>
                        <label class="text-caption font-semibold text-neutral-600 flex items-center gap-1.5">
                            <input type="checkbox" name="is_popular" value="1" <?= $t['is_popular'] ? 'checked' : '' ?>> Highlight as most popular</label>
                        <label class="block text-caption font-semibold text-neutral-600">Features <span class="font-normal text-neutral-400">(one per line; the website shows the first 3)</span>
                            <textarea name="features" rows="5" required class="input"><?= htmlspecialchars(implode("\n", $t['feature_list'])) ?></textarea></label>
                        <button class="btn btn-primary w-full !py-2 !text-xs font-semibold">Save offering</button>
                    </form>
                </details>
            </div>
            <?php endforeach; ?>
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
                    <?= \App\Support\Icon::svg('home', 18) ?>
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
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Room Type <span class="font-normal text-neutral-400">(sets capacity and rent)</span></label>
                    <select name="room_type_id" class="input" data-room-type>
                        <option value="">— Not one of the offerings —</option>
                        <?php foreach ($roomTypes as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" data-capacity="<?= (int) $t['capacity'] ?>" data-rate="<?= \App\Models\RoomType::ratePerBed($t) ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Capacity</label>
                    <input name="capacity" type="number" min="1" max="50" value="2" required class="input">
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">Monthly Rent per Bed (₱)</label>
                    <input name="base_price" type="number" step="0.01" min="0" value="3500" required class="input font-mono font-semibold">
                </div>
                <button class="btn btn-primary w-full !py-2 !text-xs font-semibold">
                    Create Room Unit
                </button>
            </form>

            <!-- Card 2: Add Bed Form -->
            <form method="post" action="/admin/beds" class="form-card accent-success">
                <div class="flex items-center gap-2 pb-2 border-b border-neutral-100">
                    <?= \App\Support\Icon::svg('bed', 18) ?>
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
                    <?= \App\Support\Icon::svg('link', 18) ?>
                    <h3 class="text-body font-semibold text-neutral-900">Assign Boarder</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <?php $assignable = array_filter($boarders, fn($b) => $b['status'] !== 'moved_out'); // assignBed() refuses moved-out ?>
                <div>
                    <label for="rooms-assign-boarder" class="block text-caption font-semibold text-neutral-600 mb-1">Select Boarder</label>
                    <select id="rooms-assign-boarder" name="boarder_id" required class="input">
                        <option value="">— Choose Boarder —</option>
                        <?php foreach ($assignable as $b): ?>
                            <option value="<?= (int) $b['user_id'] ?>">
                                <?= htmlspecialchars($b['name']) ?> <?= !empty($b['bed_id']) ? '(Assigned: ' . htmlspecialchars(($b['room_number'] ?? '') . '/' . ($b['bed_label'] ?? '')) . ')' : '(Unassigned)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$assignable): ?>
                        <p class="text-caption text-neutral-400 mt-1">
                            No boarders to assign yet. Add one in the <a href="/admin/boarders" class="text-primary-600 font-medium hover:underline">Boarders Directory</a>.
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="rooms-assign-type" class="block text-caption font-semibold text-neutral-600 mb-1">Room Type</label>
                    <select id="rooms-assign-type" class="input" data-filters-beds="rooms-assign-bed">
                        <option value="">— All room types —</option>
                        <?php foreach ($roomTypes as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= (int) $t['vacant_bed_count'] ?> vacant)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="rooms-assign-bed" class="block text-caption font-semibold text-neutral-600 mb-1">Select Vacant Bed</label>
                    <select id="rooms-assign-bed" name="bed_id" required class="input">
                        <option value="">— Choose Vacant Bed —</option>
                        <?php foreach ($beds as $b): if ($b['status'] === 'vacant'): ?>
                            <option value="<?= (int) $b['id'] ?>" data-room-type="<?= (int) ($b['room_type_id'] ?? 0) ?>"><?= htmlspecialchars($b['room_number'] . ' / ' . $b['label']) ?></option>
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

    <!-- Electricity: a room's bill, split equally among the residents staying in it -->
    <div id="electricity">
        <div class="section-header">
            <h2>Electricity Bills</h2>
            <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-3 py-1 rounded-full">
                Split equally among the residents in the room
            </span>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
            <form method="post" action="/admin/electricity" class="form-card" data-testid="electricity-form">
                <div class="flex items-center gap-2 pb-2 border-b border-neutral-100">
                    <?= \App\Support\Icon::svg('zap', 18) ?>
                    <h3 class="text-body font-semibold text-neutral-900">Enter a Room's Bill</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div>
                    <label for="electricity-room" class="block text-caption font-semibold text-neutral-600 mb-1">Room</label>
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
                    <label for="electricity-label" class="block text-caption font-semibold text-neutral-600 mb-1">Bill for</label>
                    <input name="label" id="electricity-label" maxlength="60" required class="input" placeholder="e.g. September 2026">
                </div>
                <div>
                    <label for="electricity-amount" class="block text-caption font-semibold text-neutral-600 mb-1">Room's Bill (₱)</label>
                    <input name="amount" id="electricity-amount" type="number" step="0.01" min="1" max="1000000" required class="input font-mono font-semibold">
                </div>
                <p id="electricity-share" class="text-caption text-neutral-600 bg-neutral-50 rounded-md p-2.5 border border-neutral-200" aria-live="polite">
                    Each resident's share appears here.
                </p>
                <button class="btn btn-primary w-full !py-2 !text-xs font-semibold">
                    Split and Bill the Residents
                </button>
            </form>

            <div class="card overflow-hidden lg:col-span-2">
                <?php if (empty($electricityBills)): ?>
                    <div class="empty-state" style="padding:2.5rem 0;">
                        <span class="text-sm font-semibold text-neutral-800">No electricity bills entered yet</span>
                        <span class="text-xs text-neutral-500 mt-1">Each share is due together with that resident's next rent.</span>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-body-sm" data-testid="electricity-bills">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Bill for</th>
                                    <th>Room's Bill</th>
                                    <th>Each Resident</th>
                                    <th>Entered</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($electricityBills as $bill): ?>
                                    <tr>
                                        <td class="font-bold text-neutral-900">Room <?= htmlspecialchars($bill['room_number']) ?></td>
                                        <td><?= htmlspecialchars($bill['label']) ?></td>
                                        <td class="font-mono">₱<?= number_format((float) $bill['amount'], 2) ?></td>
                                        <td class="font-mono">₱<?= number_format((float) $bill['share'], 2) ?> × <?= (int) $bill['residents'] ?></td>
                                        <td class="text-neutral-500"><?= date('M j, Y', strtotime($bill['created_at'])) ?></td>
                                        <td>
                                            <form method="post" action="/admin/electricity/<?= (int) $bill['id'] ?>/delete" class="action-cluster" style="justify-content: flex-end;"
                                                  onsubmit="return confirm('Remove this electricity bill? Every resident\'s share of it is removed and their balance recalculated.');">
                                                <?= \App\Support\Csrf::field() ?>
                                                <button class="btn-danger !py-1 !px-3 !text-xs" title="Remove a bill entered by mistake">Remove</button>
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
                <span class="empty-icon"><?= \App\Support\Icon::svg('home') ?></span>
                <span class="text-sm font-semibold text-neutral-800">No rooms configured yet</span>
                <span class="text-xs text-neutral-500 mt-1">Use the "Add Room" form above to configure your first room.</span>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm" style="min-width: 860px;">
                    <thead>
                        <tr>
                            <th style="width: 13%;">Room #</th>
                            <th style="width: 11%;">Floor</th>
                            <th style="width: 22%;">Room Type</th>
                            <th style="width: 15%;">Capacity</th>
                            <th style="width: 20%;">Rent per Bed / mo</th>
                            <th style="width: 19%; text-align: right;">Actions</th>
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
                                <select form="room-update-<?= (int)$r['id'] ?>" name="room_type_id" data-room-type class="input input-sm" style="max-width:12rem;" title="Room type">
                                    <option value="">— None —</option>
                                    <?php foreach ($roomTypes as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>" data-capacity="<?= (int) $t['capacity'] ?>" data-rate="<?= \App\Models\RoomType::ratePerBed($t) ?>" <?= (int) ($r['room_type_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <input form="room-update-<?= (int)$r['id'] ?>" name="capacity" type="number" min="1" max="50" value="<?= (int) $r['capacity'] ?>" <?= $r['room_type_id'] ? 'readonly' : '' ?>
                                           class="input input-sm font-semibold" style="width:4.5rem;" title="Capacity">
                                    <span class="text-caption text-neutral-400">beds</span>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-neutral-500 font-semibold text-xs">₱</span>
                                    <input form="room-update-<?= (int)$r['id'] ?>" name="base_price" type="number" step="0.01" min="0" value="<?= htmlspecialchars($r['base_price']) ?>" <?= $r['room_type_id'] ? 'readonly' : '' ?>
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
                            $roomCapacity = (int) $r['capacity'];
                            $roomBedCount = count($roomBeds);
                        ?>
                        <!-- Main Room Row -->
                        <tr class="room-main-row transition-colors cursor-pointer" id="room-row-<?= (int) $r['id'] ?>" data-id="<?= (int) $r['id'] ?>" title="Click row to view bed slots">
                            <td class="font-bold text-neutral-900">
                                <span class="inline-flex items-center gap-1.5">
                                    <?= \App\Support\Icon::svg('door-open', 15) ?>
                                    Room <?= htmlspecialchars($r['room_number']) ?>
                                </span>
                                <span class="block text-caption font-normal text-neutral-500"><?= htmlspecialchars($r['room_type_name'] ?? 'No room type') ?></span>
                            </td>
                            <td class="text-neutral-700 font-medium">
                                <?= $roomBedCount ?> of <?= $roomCapacity ?> bed<?= $roomCapacity !== 1 ? 's' : '' ?>
                                <?php if ($roomBedCount < $roomCapacity): ?><span class="block text-caption text-amber-600"><?= $roomCapacity - $roomBedCount ?> not set up yet</span><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info font-medium" style="font-variant-numeric: tabular-nums;">
                                    <?= $roomOccupied ?> / <?= $roomBedCount ?> occupied
                                </span>
                            </td>
                            <td>
                                <?php if ($roomBedCount === 0): ?>
                                    <span class="badge badge-info">No beds yet</span>
                                <?php elseif ($roomVacant > 0): ?>
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
                                            <?= \App\Support\Icon::svg('bed', 18) ?>
                                            <h4 class="text-body-sm font-semibold text-neutral-900">
                                                Beds in Room <?= htmlspecialchars($r['room_number']) ?> &mdash; <span class="text-primary-700 font-bold"><?= $roomBedCount ?> of <?= $roomCapacity ?> bed<?= $roomCapacity !== 1 ? 's' : '' ?></span>
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
                                                        <form method="post" action="/admin/beds/<?= (int) $b['id'] ?>/update" class="inline-flex items-center gap-1.5">
                                                            <?= \App\Support\Csrf::field() ?>
                                                            <label class="sr-only" for="bed-label-<?= (int) $b['id'] ?>">Bed label</label>
                                                            <input id="bed-label-<?= (int) $b['id'] ?>" name="label" value="<?= htmlspecialchars($b['label']) ?>"
                                                                   required maxlength="20" class="input input-sm !py-1 text-xs" style="width: 8rem;">
                                                            <button class="btn btn-secondary !py-0.5 !px-2.5 !text-xs" title="Save the new bed label">Rename</button>
                                                        </form>
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
                                                        No beds configured in this room yet. Use the "Add Bed" form above.
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
</div>

<script>
/* ── Electricity: show each resident's share before the bill is entered ── */
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

/* ── A room type sets the room's capacity and rent: fill them in and lock them while one is chosen ── */
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

</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
