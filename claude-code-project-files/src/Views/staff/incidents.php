<?php
$pageTitle = 'Incidents';
ob_start();
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$userRole = $_SESSION['role'] ?? 'staff';
$isStaff = in_array($userRole, ['staff', 'admin'], true);

$totalIncidents = count($incidents ?? []);
$openCount = count(array_filter($incidents ?? [], fn($i) => empty($i['resolved'])));
$resolvedCount = count(array_filter($incidents ?? [], fn($i) => !empty($i['resolved'])));
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold"><?= $isStaff ? 'Incident Log' : 'Incident &amp; Lost &amp; Found Report' ?></h1>
            <p class="text-body-sm"><?= $isStaff ? 'Facility security, rule compliance & disturbance tracking (logged independently of SOS alerts)' : 'Report missing belongings (pets, uniform, keys), facility disturbances, or security concerns directly to staff.' ?></p>
        </div>
        <span class="badge badge-info" style="background:rgba(255,255,255,0.12); color:#dea27c; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#bd6b36;display:inline-block;margin-right:0.4rem;"></span>
            <?= $isStaff ? 'Incident Registry' : 'Resident Portal' ?>
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

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="reveal-card card p-4 metric-accent-primary">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Recorded</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalIncidents ?></p>
            <p class="text-caption text-neutral-400 mt-1">Lifetime incident logs</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Open Cases</p>
            <p class="text-3xl font-bold <?= $openCount > 0 ? 'text-amber-600' : 'text-neutral-900' ?> mt-1.5"><?= $openCount ?></p>
            <p class="text-caption text-neutral-400 mt-1"><?= $openCount > 0 ? 'Awaiting resolution' : 'All cases closed' ?></p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Resolved Cases</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $resolvedCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Closed with staff notes</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Registry Engine</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5">Active</p>
            <p class="text-caption text-neutral-400 mt-1"><?= $isStaff ? 'Independent staff log' : 'Resident reporting log' ?></p>
        </div>
    </div>

    <!-- 2-Column Responsive Layout: Incidents Table (2/3) + Log Incident Form (1/3) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        
        <!-- Left: Incident Records Table (2/3 on lg) -->
        <div class="lg:col-span-2 space-y-2.5">
            <div class="section-header">
                <div class="flex items-center gap-2">
                    <h2 class="text-heading-sm font-semibold text-neutral-900">Incident Records</h2>
                    <span class="badge badge-neutral"><?= $totalIncidents ?> logged</span>
                </div>
                <a href="/staff/incidents/history" class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    View Full History &rarr;
                </a>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto" style="-webkit-overflow-scrolling: touch;">
                    <table class="w-full table-compact" style="min-width: 720px;">
                        <thead>
                            <tr class="bg-neutral-50/80 border-b border-neutral-200 text-left text-neutral-500 text-[11px] uppercase tracking-wider">
                                <th class="p-2.5 font-semibold">Incident Type</th>
                                <th class="p-2.5 font-semibold">Details</th>
                                <th class="p-2.5 font-semibold">Reporter</th>
                                <th class="p-2.5 font-semibold">Status</th>
                                <th class="p-2.5 font-semibold text-right"><?= $isStaff ? 'Resolution' : 'Resolution Status' ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <?php foreach ($incidents as $i): ?>
                                <tr class="hover:bg-neutral-50 transition-colors incident-main-row" id="incident-row-<?= (int) $i['id'] ?>" data-id="<?= (int) $i['id'] ?>">
                                    <td class="p-2.5">
                                        <span class="badge badge-neutral font-semibold text-[11px] capitalize">
                                            <?= htmlspecialchars($i['type']) ?>
                                        </span>
                                    </td>
                                    <td class="p-2.5 max-w-xs">
                                        <div class="text-neutral-900 leading-relaxed font-normal text-xs">
                                            <?= htmlspecialchars($i['description']) ?>
                                        </div>
                                    </td>
                                    <td class="p-2.5 text-neutral-700 font-medium text-xs">
                                        <?= htmlspecialchars($i['reporter_name']) ?>
                                    </td>
                                    <td class="p-2.5">
                                        <?php if (!empty($i['resolved'])): ?>
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="badge badge-success text-[11px]">Resolved</span>
                                                <?php if (!empty($i['resolution_notes'])): ?>
                                                    <button type="button"
                                                            class="btn btn-secondary !py-0.5 !px-2 !text-[11px] font-semibold note-toggle-btn cursor-pointer inline-flex items-center gap-1 shadow-2xs"
                                                            data-id="<?= (int) $i['id'] ?>"
                                                            title="View resolution notes">
                                                        <span>📝</span> Note
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-warning text-[11px]">Open</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-2.5 text-right">
                                        <?php if (empty($i['resolved'])): ?>
                                            <?php if ($isStaff): ?>
                                                <form method="post" action="/staff/incidents/<?= (int) $i['id'] ?>/resolve" class="inline-flex items-center justify-end gap-1.5">
                                                    <?= \App\Support\Csrf::field() ?>
                                                    <input name="resolution_notes"
                                                           placeholder="Resolution notes"
                                                           required
                                                           class="input input-sm text-xs !py-1 w-36 bg-white">
                                                    <button type="submit" class="btn btn-primary !py-1 !px-2.5 !text-xs font-semibold shadow-xs">
                                                        Resolve
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded font-medium">Pending Review</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-[11px] text-neutral-400 italic">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($i['resolution_notes'])): ?>
                                    <!-- Slide-Down Resolution Note Drawer Row -->
                                    <tr id="note-row-<?= (int) $i['id'] ?>" class="hidden note-drawer-row border-b border-neutral-200" style="background-color: #f8f7f5;">
                                        <td colspan="5" class="p-4" style="padding: 0.875rem 1.25rem;">
                                            <div class="note-drawer-box bg-white rounded-xl border border-neutral-200 p-4 shadow-xs">
                                                <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-neutral-100">
                                                    <div class="flex items-center gap-2">
                                                        <span style="font-size:1rem;">📝</span>
                                                        <h4 class="text-body-sm font-semibold text-neutral-900">
                                                            Resolution Notes &mdash;
                                                            <span class="text-primary-700 font-bold"><?= htmlspecialchars($i['type']) ?></span>
                                                            <span class="text-caption text-neutral-400 font-normal ml-1">#<?= (int) $i['id'] ?></span>
                                                        </h4>
                                                    </div>
                                                    <button type="button" class="text-caption text-neutral-400 hover:text-neutral-700 cancel-note-btn cursor-pointer bg-transparent border-none py-1 px-2 font-medium" data-id="<?= (int) $i['id'] ?>">
                                                        &times; Close
                                                    </button>
                                                </div>
                                                <div class="text-xs text-neutral-700 leading-relaxed bg-neutral-50 p-3 rounded-lg border border-neutral-200/80">
                                                    <?= nl2br(htmlspecialchars($i['resolution_notes'])) ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (empty($incidents)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="py-12 text-center text-neutral-400">
                                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                                            <p class="font-semibold text-neutral-700 text-body-sm">No incidents logged.</p>
                                            <p class="text-caption text-neutral-400 mt-0.5">Facility records are clear of security or rule incidents.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Log Incident Form Card (1/3 on lg) -->
        <div>
            <form method="post" action="/staff/incidents" class="form-card accent-neutral space-y-4">
                <div class="flex items-center gap-2 border-b border-neutral-100 pb-3">
                    <span class="text-lg">📝</span>
                    <div>
                        <h2 class="text-heading-sm font-bold text-neutral-900"><?= $isStaff ? 'Log Incident' : 'Report Incident' ?></h2>
                        <p class="text-caption text-neutral-500"><?= $isStaff ? 'Facility & rule compliance tracking' : 'Report missing pet, uniform, or disturbance' ?></p>
                    </div>
                </div>

                <?= \App\Support\Csrf::field() ?>

                <div>
                    <label for="incident-type" class="block text-caption font-semibold text-neutral-700 mb-1">
                        Incident Type <span class="text-error-600">*</span>
                    </label>

                    <!-- Quick suggestion chips -->
                    <div class="flex items-center gap-1.5 flex-wrap mb-2">
                        <button type="button" class="incident-chip" style="font-size: 0.65rem !important; padding: 0.18rem 0.45rem !important; line-height: 1 !important;" data-type="Missing Pet">🐾 Missing Pet</button>
                        <button type="button" class="incident-chip" style="font-size: 0.65rem !important; padding: 0.18rem 0.45rem !important; line-height: 1 !important;" data-type="Missing Uniform">👔 Missing Uniform</button>
                        <button type="button" class="incident-chip" style="font-size: 0.65rem !important; padding: 0.18rem 0.45rem !important; line-height: 1 !important;" data-type="Lost Belonging">🔑 Lost Belonging</button>
                        <button type="button" class="incident-chip" style="font-size: 0.65rem !important; padding: 0.18rem 0.45rem !important; line-height: 1 !important;" data-type="Noise Disturbance">📢 Noise Disturbance</button>
                        <button type="button" class="incident-chip" style="font-size: 0.65rem !important; padding: 0.18rem 0.45rem !important; line-height: 1 !important;" data-type="Safety Concern">⚠️ Safety Concern</button>
                    </div>

                    <input id="incident-type"
                           name="type"
                           type="text"
                           placeholder="Type (e.g. Missing pet, missing uniform)"
                           required
                           class="input w-full">
                </div>

                <div>
                    <label for="incident-desc" class="block text-caption font-semibold text-neutral-700 mb-1">
                        Incident Details <span class="text-error-600">*</span>
                    </label>
                    <textarea id="incident-desc"
                              name="description"
                              placeholder="Describe what happened, item/pet description, last seen location, time, or disturbance details..."
                              required
                              class="input w-full resize-y"
                              rows="4"></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-full py-2.5 text-sm font-semibold justify-center">
                    <?= $isStaff ? 'Log Incident' : 'Submit Incident Report' ?>
                </button>
            </form>
        </div>

    </div>

</div>

<script>
document.querySelectorAll('.incident-chip').forEach(function(chip) {
    chip.addEventListener('click', function() {
        var input = document.getElementById('incident-type');
        var desc = document.getElementById('incident-desc');
        if (input) {
            input.value = this.dataset.type;
            if (desc) desc.focus();
        }
    });
});

// Resolution Note Drawer toggle
let activeNoteId = null;

function openNoteDrawer(id) {
    if (activeNoteId && activeNoteId !== id) {
        closeNoteDrawer(activeNoteId);
    }
    const row = document.getElementById('note-row-' + id);
    const mainRow = document.getElementById('incident-row-' + id);
    if (!row) return;
    row.classList.remove('hidden');
    const box = row.querySelector('.note-drawer-box');
    if (window.gsap && box) {
        gsap.fromTo(box, { opacity: 0, y: -8 }, { opacity: 1, y: 0, duration: 0.22, ease: 'power2.out' });
    }
    if (mainRow) mainRow.style.backgroundColor = '#f1f0ee';
    activeNoteId = id;
}

function closeNoteDrawer(id) {
    const row = document.getElementById('note-row-' + id);
    const mainRow = document.getElementById('incident-row-' + id);
    if (!row || row.classList.contains('hidden')) return;
    const box = row.querySelector('.note-drawer-box');
    if (window.gsap && box) {
        gsap.to(box, {
            opacity: 0,
            y: -8,
            duration: 0.18,
            ease: 'power2.in',
            onComplete: () => row.classList.add('hidden')
        });
    } else {
        row.classList.add('hidden');
    }
    if (mainRow) mainRow.style.backgroundColor = '';
    if (activeNoteId === id) activeNoteId = null;
}

document.addEventListener('click', function(e) {
    const noteToggle = e.target.closest('.note-toggle-btn');
    if (noteToggle) {
        e.stopPropagation();
        const id = noteToggle.getAttribute('data-id');
        const row = document.getElementById('note-row-' + id);
        (row && !row.classList.contains('hidden')) ? closeNoteDrawer(id) : openNoteDrawer(id);
        return;
    }

    const cancelNote = e.target.closest('.cancel-note-btn');
    if (cancelNote) {
        e.stopPropagation();
        closeNoteDrawer(cancelNote.getAttribute('data-id'));
        return;
    }
});

if (window.gsap) {
    let mm = gsap.matchMedia();
    mm.add({
        animate: "(prefers-reduced-motion: no-preference)",
        reduce: "(prefers-reduced-motion: reduce)",
    }, (context) => {
        if (context.conditions.animate) {
            gsap.from(".reveal-card", {
                opacity: 0,
                y: 12,
                duration: 0.35,
                stagger: 0.05,
                ease: "power2.out"
            });
            gsap.from(".form-card", {
                opacity: 0,
                y: 12,
                duration: 0.35,
                delay: 0.1,
                ease: "power2.out"
            });
        }
    });
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';

