<?php
$pageTitle = 'Penalty Rules';
ob_start();
$info = $_SESSION['flash_info'] ?? null; unset($_SESSION['flash_info']);
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);

// Compute summary metrics
$totalRules = count($rules ?? []);
$activeRules = count(array_filter($rules ?? [], fn($r) => !empty($r['active'])));
$inactiveRules = $totalRules - $activeRules;

// Find late fee policy
$lateRule = null;
foreach ($rules ?? [] as $r) {
    if (($r['condition_type'] ?? '') === 'late_per_day' && !empty($r['active'])) {
        $lateRule = $r;
        break;
    }
}
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Penalty Rules &amp; Automation</h1>
            <p class="text-body-sm">Late fee policies, flat fee enforcement &amp; manual notification triggers</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Rules Engine Active
        </span>
    </div>

    <?php if ($info): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-lg p-3 border border-emerald-300 flex items-center gap-2" role="status">
            <span style="font-size:1rem;">✓</span>
            <?= htmlspecialchars($info) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3 border border-error-500 flex items-center gap-2" role="alert">
            <span style="font-size:1rem;">⚠</span>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="reveal-card card p-4 metric-accent-primary">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Rules</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalRules ?></p>
            <p class="text-caption text-neutral-400 mt-1">Configured policies</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Active Rules</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $activeRules ?></p>
            <p class="text-caption text-neutral-400 mt-1">Enforced currently</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Inactive Rules</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $inactiveRules ?></p>
            <p class="text-caption text-neutral-400 mt-1">Disabled policies</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Late Rent Rate</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5">
                <?= $lateRule ? '₱' . number_format((float)$lateRule['amount'], 2) : 'None' ?>
            </p>
            <p class="text-caption text-neutral-400 mt-1">
                <?= $lateRule ? 'Per day overdue' : 'No active late rate' ?>
            </p>
        </div>
    </div>

    <!-- Configured Rules Section -->
    <div>
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Configured Penalty Rules</h2>
                <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">
                    <?= $totalRules ?> rule<?= $totalRules !== 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide" style="width:4.5rem;">ID</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Rule Name</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Condition Type</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Fee Amount</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Status</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $r): ?>
                            <?php $isActive = (bool) $r['active']; ?>
                            <tr class="border-b border-neutral-100 hover:bg-neutral-50/80 transition-colors">
                                <td class="p-3">
                                    <span class="id-tag">#<?= (int) $r['id'] ?></span>
                                </td>
                                <td class="p-3">
                                    <div class="font-bold text-neutral-800 text-sm"><?= htmlspecialchars($r['name']) ?></div>
                                </td>
                                <td class="p-3">
                                    <span class="badge badge-neutral font-mono font-medium">
                                        <?= htmlspecialchars($r['condition_type']) ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <!-- Inline Amount Update Form -->
                                    <form method="post" action="/admin/penalty-rules/<?= (int) $r['id'] ?>/amount" class="flex items-center gap-1.5">
                                        <?= \App\Support\Csrf::field() ?>
                                        <div class="relative flex items-center">
                                            <span style="position:absolute;left:0.5rem;color:#64748b;font-size:0.75rem;font-weight:700;">₱</span>
                                            <input name="amount"
                                                   type="number"
                                                   step="0.01"
                                                   min="0.01"
                                                   value="<?= htmlspecialchars($r['amount']) ?>"
                                                   class="input input-sm font-mono font-medium"
                                                   style="width: 6.5rem; padding-left: 1.5rem;"
                                                   title="Update amount">
                                        </div>
                                        <button type="submit" class="btn btn-primary !py-1 !px-2.5 !text-xs cursor-pointer shadow-xs">
                                            Save
                                        </button>
                                    </form>
                                </td>
                                <td class="p-3">
                                    <span class="badge <?= $isActive ? 'badge-success' : 'badge-neutral' ?>">
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <!-- Toggle Active/Inactive Form -->
                                    <form method="post" action="/admin/penalty-rules/<?= (int) $r['id'] ?>/toggle" class="inline">
                                        <?= \App\Support\Csrf::field() ?>
                                        <input type="hidden" name="active" value="<?= $isActive ? '0' : '1' ?>">
                                        <?php if ($isActive): ?>
                                            <button type="submit"
                                                    class="btn-danger !py-1 !px-2.5 !text-xs cursor-pointer"
                                                    onclick="return confirm('Deactivate rule &quot;<?= htmlspecialchars(addslashes($r['name'])) ?>&quot;?');">
                                                Deactivate
                                            </button>
                                        <?php else: ?>
                                            <button type="submit"
                                                    class="btn btn-secondary !py-1 !px-2.5 !text-xs cursor-pointer"
                                                    onclick="return confirm('Reactivate rule &quot;<?= htmlspecialchars(addslashes($r['name'])) ?>&quot;?');">
                                                Reactivate
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($rules)): ?>
                            <tr>
                                <td colspan="6" class="empty-state" style="padding:3.5rem 0;">
                                    <span style="font-size:2rem;">⚖️</span>
                                    <span class="text-xs font-medium text-neutral-500 mt-1 block">No penalty rules configured yet. Create one using the form below.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Management & Automation Actions Grid -->
    <div>
        <div class="section-header">
            <h2 class="text-heading-sm font-semibold text-neutral-900">Automation &amp; Policy Management</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <!-- Card 1: Add New Rule -->
            <form method="post" action="/admin/penalty-rules" class="form-card">
                <div class="flex items-center gap-2 pb-1 border-b border-neutral-100">
                    <span style="font-size:1.1rem;">➕</span>
                    <h3 class="text-body font-semibold text-neutral-900">Add Penalty Rule</h3>
                </div>

                <?= \App\Support\Csrf::field() ?>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                        Rule Name <span class="text-error-600">*</span>
                    </label>
                    <input name="name" placeholder="e.g. Late Rent Charge" required class="input" maxlength="150">
                </div>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                        Condition Type <span class="text-error-600">*</span>
                    </label>
                    <select name="condition_type" class="input">
                        <option value="late_per_day">late_per_day (Daily Overdue Fee)</option>
                        <option value="flat_damage">flat_damage (Property Damage / Incident)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-0.5">
                        Default Amount (₱) <span class="text-error-600">*</span>
                    </label>
                    <div class="relative flex items-center">
                        <span style="position:absolute;left:0.75rem;color:#64748b;font-size:0.875rem;font-weight:700;">₱</span>
                        <input name="amount"
                               type="number"
                               step="0.01"
                               min="0.01"
                               placeholder="0.00"
                               required
                               class="input font-mono font-medium"
                               style="padding-left: 2rem;">
                    </div>
                </div>

                <div class="pt-1">
                    <button type="submit" class="btn btn-primary w-full shadow-xs cursor-pointer">
                        <span>Create Rule</span>
                    </button>
                </div>
            </form>

            <!-- Card 2: Run Penalty Check Trigger -->
            <div class="form-card accent-neutral flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 pb-1 border-b border-neutral-100">
                        <span style="font-size:1.1rem;">⚡</span>
                        <h3 class="text-body font-semibold text-neutral-900">Run Penalty Check</h3>
                    </div>
                    <p class="text-body-sm text-neutral-600 mt-2">
                        Scans active tenancies, calculates unpaid rent for the current billing period, and generates penalty charges based on active rules.
                    </p>
                    <div class="bg-neutral-50 rounded-lg p-2.5 mt-3 border border-neutral-200 text-caption text-neutral-500">
                        ℹ On localhost, this is triggered manually on demand or automatically during admin sign-in.
                    </div>
                </div>

                <form method="post" action="/admin/penalties/run-check" class="pt-3">
                    <?= \App\Support\Csrf::field() ?>
                    <button type="submit"
                            class="btn btn-primary w-full shadow-xs cursor-pointer"
                            onclick="return confirm('Run automated penalty calculation across all active tenancies now?');">
                        <span>⚡ Run Check Now</span>
                    </button>
                </form>
            </div>

            <!-- Card 3: Send Rent-Due Reminders Trigger -->
            <div class="form-card accent-success flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 pb-1 border-b border-neutral-100">
                        <span style="font-size:1.1rem;">🔔</span>
                        <h3 class="text-body font-semibold text-neutral-900">Rent-Due Reminders</h3>
                    </div>
                    <p class="text-body-sm text-neutral-600 mt-2">
                        Dispatches in-app notifications and dashboard alerts to all active boarders without a verified payment for the current month (<span class="font-semibold text-neutral-800"><?= date('F Y') ?></span>).
                    </p>
                    <div class="bg-emerald-50 rounded-lg p-2.5 mt-3 border border-emerald-200 text-caption text-emerald-800">
                        ✓ Alerts boarders safely without creating duplicate notices.
                    </div>
                </div>

                <form method="post" action="/admin/notifications/rent-due" class="pt-3">
                    <?= \App\Support\Csrf::field() ?>
                    <button type="submit"
                            class="btn btn-primary w-full shadow-xs cursor-pointer"
                            style="background-color: #16a34a; border-color: #16a34a;"
                            onclick="return confirm('Send rent-due reminders to all unverified boarders for <?= date('F Y') ?>?');">
                        <span>🔔 Send Reminders Now</span>
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // GSAP reveal animation matching dashboard and boarders
    if (window.gsap && window.ScrollTrigger) {
        let mm = gsap.matchMedia();
        mm.add({
            animate: "(prefers-reduced-motion: no-preference)",
            reduce: "(prefers-reduced-motion: reduce)",
        }, (context) => {
            if (context.conditions.animate) {
                gsap.from(".reveal-card", {
                    y: 16,
                    opacity: 0,
                    duration: 0.45,
                    stagger: 0.08,
                    ease: "power2.out",
                    scrollTrigger: { trigger: ".reveal-card", start: "top 90%", once: true },
                });
            } else {
                gsap.set(".reveal-card", { opacity: 1, y: 0 });
            }
        });
    }
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
