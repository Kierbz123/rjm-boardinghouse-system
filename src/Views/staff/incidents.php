<?php
$pageTitle = 'Incidents';
ob_start();
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$totalIncidents = count($incidents ?? []);
$openCount = count(array_filter($incidents ?? [], fn($i) => empty($i['resolved'])));
$resolvedCount = count(array_filter($incidents ?? [], fn($i) => !empty($i['resolved'])));
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Incident Log</h1>
            <p class="text-body-sm">Facility security, rule compliance &amp; disturbance tracking (logged independently of SOS alerts)</p>
        </div>
        <span class="badge badge-info" style="background:rgba(255,255,255,0.12); color:#93c5fd; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#3b82f6;display:inline-block;margin-right:0.4rem;"></span>
            Incident Registry
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
            <p class="text-caption text-neutral-400 mt-1">Independent staff log</p>
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
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr class="bg-neutral-50/80 border-b border-neutral-200 text-left text-neutral-500 text-caption uppercase tracking-wider">
                                <th class="p-3 font-semibold">Incident Type</th>
                                <th class="p-3 font-semibold">Details</th>
                                <th class="p-3 font-semibold">Reporter</th>
                                <th class="p-3 font-semibold">Status</th>
                                <th class="p-3 font-semibold text-right">Resolution</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <?php foreach ($incidents as $i): ?>
                                <tr class="hover:bg-neutral-50 transition-colors">
                                    <td class="p-3">
                                        <span class="badge badge-neutral font-semibold text-xs capitalize">
                                            <?= htmlspecialchars($i['type']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 max-w-xs">
                                        <div class="text-neutral-900 leading-relaxed font-normal">
                                            <?= htmlspecialchars($i['description']) ?>
                                        </div>
                                    </td>
                                    <td class="p-3 text-neutral-700 font-medium">
                                        <?= htmlspecialchars($i['reporter_name']) ?>
                                    </td>
                                    <td class="p-3">
                                        <?php if (!empty($i['resolved'])): ?>
                                            <span class="badge badge-success">Resolved</span>
                                            <?php if (!empty($i['resolution_notes'])): ?>
                                                <div class="text-[11px] text-neutral-500 mt-1 max-w-xs bg-neutral-100/70 p-1.5 rounded border border-neutral-200/60 leading-tight">
                                                    <?= htmlspecialchars($i['resolution_notes']) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Open</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-right">
                                        <?php if (empty($i['resolved'])): ?>
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
                                            <span class="text-caption text-neutral-400 italic">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
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
                        <h2 class="text-heading-sm font-bold text-neutral-900">Log Incident</h2>
                        <p class="text-caption text-neutral-500">Independent of any SOS alert</p>
                    </div>
                </div>

                <?= \App\Support\Csrf::field() ?>

                <div>
                    <label for="incident-type" class="block text-caption font-semibold text-neutral-700 mb-1">
                        Incident Type <span class="text-error-600">*</span>
                    </label>
                    <input id="incident-type"
                           name="type"
                           type="text"
                           placeholder="Type (e.g. noise complaint)"
                           required
                           class="input w-full">
                </div>

                <div>
                    <label for="incident-desc" class="block text-caption font-semibold text-neutral-700 mb-1">
                        Incident Details <span class="text-error-600">*</span>
                    </label>
                    <textarea id="incident-desc"
                              name="description"
                              placeholder="Description"
                              required
                              class="input w-full resize-y"
                              rows="4"></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-full py-2.5 text-sm font-semibold justify-center">
                    Log Incident
                </button>
            </form>
        </div>

    </div>

</div>

<script>
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
