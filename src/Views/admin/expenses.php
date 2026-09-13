<?php
$pageTitle = 'Expenses';
ob_start();
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

// Compute summary metrics
$totalCount = count($expenses ?? []);
$totalAmount = array_sum(array_column($expenses ?? [], 'amount'));

// Compute top category by spending
$categorySpending = [];
foreach ($expenses ?? [] as $e) {
    $cat = trim($e['category'] ?? 'General');
    $categorySpending[$cat] = ($categorySpending[$cat] ?? 0) + (float) ($e['amount'] ?? 0);
}
arsort($categorySpending);
$topCategory = !empty($categorySpending) ? array_key_first($categorySpending) : 'None';
$latestExpense = !empty($expenses) ? $expenses[0] : null;
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Operating Expenses</h1>
            <p class="text-body-sm">Operational expenditures, maintenance costs &amp; facility disbursements</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Financials Active
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
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Entries</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Disbursements logged</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-error">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Disbursed</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5">₱<?= number_format($totalAmount, 2) ?></p>
            <p class="text-caption text-neutral-400 mt-1">Cumulative expenditure</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Top Category</p>
            <p class="text-2xl font-bold text-neutral-900 mt-2 truncate" title="<?= htmlspecialchars($topCategory) ?>">
                <?= htmlspecialchars($topCategory) ?>
            </p>
            <p class="text-caption text-amber-600 mt-1 font-semibold">
                <?= !empty($categorySpending) ? '₱' . number_format(reset($categorySpending), 2) : '—' ?>
            </p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Latest Entry</p>
            <p class="text-2xl font-bold text-neutral-900 mt-2">
                <?= $latestExpense ? '₱' . number_format((float)$latestExpense['amount'], 2) : '—' ?>
            </p>
            <p class="text-caption text-neutral-400 mt-1">
                <?= $latestExpense && !empty($latestExpense['created_at']) ? date('M j, Y', strtotime($latestExpense['created_at'])) : 'No records' ?>
            </p>
        </div>
    </div>

    <!-- Main Content Layout (Table + Log Form) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

        <!-- Left 2 Cols: Expenses Table -->
        <div class="lg:col-span-2 space-y-4">
            <div class="section-header">
                <div class="flex items-center gap-2">
                    <h2 class="text-heading-sm font-semibold text-neutral-900">Expense Records</h2>
                    <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">
                        <?= $totalCount ?> entry<?= $totalCount !== 1 ? 's' : '' ?>
                    </span>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                                <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide" style="width:4.5rem;">ID</th>
                                <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Category &amp; Details</th>
                                <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Amount</th>
                                <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Logged By</th>
                                <th class="p-3 text-right font-semibold text-neutral-500 text-caption uppercase tracking-wide">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $e): ?>
                                <tr class="border-b border-neutral-100 hover:bg-neutral-50/80 transition-colors">
                                    <td class="p-3">
                                        <span class="id-tag">#<?= (int) ($e['id'] ?? 0) ?></span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center gap-2">
                                            <span class="badge badge-neutral font-medium">
                                                <?= htmlspecialchars($e['category'] ?? 'General') ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($e['description'])): ?>
                                            <div class="text-caption text-neutral-600 mt-1 max-w-xs sm:max-w-sm truncate" title="<?= htmlspecialchars($e['description']) ?>">
                                                <?= htmlspecialchars($e['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3">
                                        <span class="font-bold text-neutral-900 text-sm">
                                            ₱<?= number_format((float)$e['amount'], 2) ?>
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <div class="font-medium text-neutral-800 text-xs">
                                            <?= htmlspecialchars($e['staff_name'] ?? 'Staff') ?>
                                        </div>
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="text-caption text-neutral-500">
                                            <?= !empty($e['created_at']) ? date('M j, Y', strtotime($e['created_at'])) : '—' ?>
                                        </div>
                                        <div class="text-[11px] text-neutral-400">
                                            <?= !empty($e['created_at']) ? date('g:i a', strtotime($e['created_at'])) : '' ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($expenses)): ?>
                                <tr>
                                    <td colspan="5" class="empty-state" style="padding:3.5rem 0;">
                                        <span style="font-size:2rem;">📋</span>
                                        <span class="text-xs font-medium text-neutral-500 mt-1 block">No expenses logged yet. Use the form to log your first expenditure.</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right 1 Col: Log Expense Form Card -->
        <div class="space-y-4">
            <div class="section-header">
                <h2 class="text-heading-sm font-semibold text-neutral-900">New Expenditure</h2>
            </div>

            <form method="post" action="/admin/expenses" class="form-card">
                <div class="flex items-center gap-2 pb-1 border-b border-neutral-100">
                    <span style="font-size:1.1rem;">💸</span>
                    <h3 class="text-body font-semibold text-neutral-900">Log Operating Expense</h3>
                </div>

                <?= \App\Support\Csrf::field() ?>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">
                        Category <span class="text-error-600">*</span>
                    </label>
                    <input name="category"
                           placeholder="e.g. Electricity, Water, Repairs, Cleaning"
                           required
                           class="input"
                           maxlength="100">
                </div>

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">
                        Amount (₱) <span class="text-error-600">*</span>
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

                <div>
                    <label class="block text-caption font-semibold text-neutral-600 mb-1">
                        Description <span class="font-normal text-neutral-400">(optional)</span>
                    </label>
                    <textarea name="description"
                              rows="3"
                              placeholder="Add notes, invoice/receipt details, vendor name..."
                              class="input text-body-sm"
                              style="resize:vertical;"></textarea>
                </div>

                <div class="pt-1">
                    <button type="submit" class="btn btn-primary w-full shadow-xs cursor-pointer">
                        <span>Save Expenditure</span>
                    </button>
                </div>
            </form>
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
