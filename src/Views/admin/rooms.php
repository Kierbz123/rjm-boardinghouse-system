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
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Rooms &amp; Beds</h1>
            <p class="text-body-sm">Physical property layout, room pricing &amp; bed management</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Inventory Active
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
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Rooms</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalRooms ?></p>
            <p class="text-caption text-neutral-400 mt-1">Configured units</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Capacity</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalBeds ?></p>
            <p class="text-caption text-neutral-400 mt-1">Total sleeping slots</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Occupied Beds</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $occupiedBeds ?></p>
            <p class="text-caption text-amber-600 mt-1 font-semibold"><?= $occupancyRate ?>% rate</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Vacant Beds</p>
            <p class="text-3xl font-bold <?= $vacantBeds === 0 ? 'text-error-600' : 'text-neutral-900' ?> mt-1.5"><?= $vacantBeds ?></p>
            <p class="text-caption text-neutral-400 mt-1">Available for boarders</p>
        </div>
    </div>

    <!-- Room Configurations -->
    <div>
        <div class="section-header">
            <h2>Room Configurations</h2>
            <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">
                <?= $totalRooms ?> room<?= $totalRooms !== 1 ? 's' : '' ?>
            </span>
        </div>

        <div class="card overflow-hidden">
            <?php if (empty($rooms)): ?>
            <div class="empty-state" style="padding:3rem 0;">
                <span style="font-size:2rem;">🏠</span>
                <span class="text-xs font-medium text-neutral-500">No rooms configured yet — use the form below to create your first room.</span>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm" style="border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                            <th style="padding:0.625rem 0.875rem;text-align:left;font-size:0.65rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Room #</th>
                            <th style="padding:0.625rem 0.875rem;text-align:left;font-size:0.65rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Floor</th>
                            <th style="padding:0.625rem 0.875rem;text-align:left;font-size:0.65rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Capacity</th>
                            <th style="padding:0.625rem 0.875rem;text-align:left;font-size:0.65rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Base Price / mo</th>
                            <th style="padding:0.625rem 0.875rem;text-align:left;font-size:0.65rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $r): ?>
                        <tr class="border-b border-neutral-100">
                            <td style="padding:0.5rem 0.625rem;">
                                <?php $csrf_field = \App\Support\Csrf::field(); ?>
                                <form id="room-update-<?= (int)$r['id'] ?>" method="post" action="/admin/rooms/<?= (int) $r['id'] ?>/update">
                                    <?= $csrf_field ?>
                                </form>
                                <input form="room-update-<?= (int)$r['id'] ?>" name="room_number" value="<?= htmlspecialchars($r['room_number']) ?>"
                                       class="input input-sm" style="width:4.5rem;font-weight:700;" title="Room number">
                            </td>
                            <td style="padding:0.5rem 0.625rem;">
                                <input form="room-update-<?= (int)$r['id'] ?>" name="floor" value="<?= htmlspecialchars($r['floor'] ?? '') ?>"
                                       class="input input-sm" style="width:4rem;" placeholder="—" title="Floor">
                            </td>
                            <td style="padding:0.5rem 0.625rem;">
                                <input form="room-update-<?= (int)$r['id'] ?>" name="capacity" type="number" min="1" value="<?= (int) $r['capacity'] ?>"
                                       class="input input-sm" style="width:4.5rem;" title="Capacity">
                            </td>
                            <td style="padding:0.5rem 0.625rem;">
                                <div style="display:flex;align-items:center;gap:0.25rem;">
                                    <span style="color:#64748b;font-size:0.8rem;">&#8369;</span>
                                    <input form="room-update-<?= (int)$r['id'] ?>" name="base_price" type="number" step="0.01" value="<?= htmlspecialchars($r['base_price']) ?>"
                                           class="input input-sm font-mono" style="width:7rem;" title="Base price">
                                </div>
                            </td>
                            <td style="padding:0.5rem 0.625rem;">
                                <div style="display:flex;align-items:center;gap:0.375rem;">
                                    <button type="submit" form="room-update-<?= (int)$r['id'] ?>" class="btn btn-primary !py-1 !px-3 !text-xs">Save</button>
                                    <form method="post" action="/admin/rooms/<?= (int) $r['id'] ?>/delete"
                                          onsubmit="return confirm('Delete Room <?= htmlspecialchars($r['room_number']) ?>? Only allowed if it has no beds.');">
                                        <?= \App\Support\Csrf::field() ?>
                                        <button class="btn-danger" title="Delete room">Delete</button>
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

    <!-- Beds Directory -->
    <div>
        <div class="section-header">
            <h2 class="text-heading-sm font-semibold text-neutral-900">Beds Directory</h2>
            <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">
                <?= $totalBeds ?> bed<?= $totalBeds !== 1 ? 's' : '' ?> &bull; <?= $vacantBeds ?> vacant
            </span>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Room</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Bed Label</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Status</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Assigned Boarder</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($beds as $b): ?>
                        <tr class="border-b border-neutral-100" style="transition:background 120ms;">
                            <td class="p-3 font-bold text-neutral-800">Room <?= htmlspecialchars($b['room_number']) ?></td>
                            <td class="p-3 text-neutral-700 font-medium"><?= htmlspecialchars($b['label']) ?></td>
                            <td class="p-3">
                                <span class="badge <?= $b['status'] === 'occupied' ? 'badge-warning' : 'badge-success' ?>">
                                    <?= htmlspecialchars($b['status']) ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <?php if (!empty($b['boarder_name'])): ?>
                                    <span class="text-neutral-900 font-medium"><?= htmlspecialchars($b['boarder_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-neutral-400 text-xs italic">Vacant slot</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <form method="post" action="/admin/beds/<?= (int) $b['id'] ?>/delete" class="inline"
                                      onsubmit="return confirm('Delete this bed? Only allowed if vacant.');">
                                    <?= \App\Support\Csrf::field() ?>
                                    <button class="btn-danger" title="Delete bed">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($beds)): ?>
                        <tr>
                            <td colspan="5" class="empty-state" style="padding:3rem 0;">
                                <span style="font-size:2rem;">🛏️</span>
                                <span class="text-xs font-medium text-neutral-500">No beds configured yet.</span>
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

            <!-- Add Room -->
            <form method="post" action="/admin/rooms" class="form-card">
                <div class="flex items-center gap-2">
                    <span style="font-size:1rem;">🏠</span>
                    <h3 class="text-body font-semibold text-neutral-900">Add Room</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Room Number</label>
                    <input name="room_number" placeholder="e.g. 101" required class="input">
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Floor <span class="font-normal text-neutral-400">(optional)</span></label>
                    <input name="floor" placeholder="e.g. 1" class="input">
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Capacity</label>
                    <input name="capacity" type="number" min="1" value="2" required class="input">
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Monthly Base Price (₱)</label>
                    <input name="base_price" type="number" step="0.01" value="3500" required class="input font-mono">
                </div>
                <button class="btn btn-primary w-full">Add Room</button>
            </form>

            <!-- Add Bed -->
            <form method="post" action="/admin/beds" class="form-card accent-success">
                <div class="flex items-center gap-2">
                    <span style="font-size:1rem;">🛏️</span>
                    <h3 class="text-body font-semibold text-neutral-900">Add Bed</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Select Room</label>
                    <select name="room_id" id="add-bed-room" required class="input">
                        <option value="">— Choose Room —</option>
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?= (int) $r['id'] ?>">Room <?= htmlspecialchars($r['room_number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Number of Beds</label>
                    <input type="number" name="count" id="add-bed-count" min="1" max="50" value="1" required class="input font-mono" placeholder="1">
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Bed Label / Prefix</label>
                    <input name="label" id="add-bed-label" placeholder="e.g. Bed A, Bed 1, or Bed" required class="input">
                    <p class="text-[11px] text-neutral-400 mt-0.5">For multiple beds, numbers or letters sequence automatically.</p>
                </div>
                <div id="add-bed-preview" class="hidden text-caption text-neutral-600 bg-neutral-50 rounded-md p-2 border border-neutral-200">
                    <span class="font-semibold text-neutral-700">Preview:</span> <span id="add-bed-preview-text" class="font-mono text-emerald-700 font-semibold"></span>
                </div>
                <p class="text-caption text-neutral-500 bg-neutral-50 rounded-md p-2.5 border border-neutral-100">
                    Beds are initialized in <em>vacant</em> status and immediately available for assignment.
                </p>
                <button class="btn btn-primary w-full" id="add-bed-submit">Add Bed</button>
            </form>

            <!-- Assign Boarder to Bed -->
            <form method="post" action="/admin/beds/assign" class="form-card accent-neutral">
                <div class="flex items-center gap-2">
                    <span style="font-size:1rem;">🔗</span>
                    <h3 class="text-body font-semibold text-neutral-900">Assign Boarder</h3>
                </div>
                <?= \App\Support\Csrf::field() ?>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Boarder User ID</label>
                    <input name="boarder_id" type="number" placeholder="Enter user ID" required class="input">
                    <p class="text-caption text-neutral-400 mt-1">
                        Find IDs in the <a href="/admin/boarders" class="text-primary-600 hover:underline">Boarders Directory</a>.
                    </p>
                </div>
                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">Select Vacant Bed</label>
                    <select name="bed_id" required class="input">
                        <option value="">— Choose Vacant Bed —</option>
                        <?php foreach ($beds as $b): if ($b['status'] === 'vacant'): ?>
                            <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['room_number'] . ' / ' . $b['label']) ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-secondary w-full" style="margin-top:auto;">Assign Bed</button>
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
