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
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1>Operating Expenses</h1>
            <p>Operational expenditures, maintenance costs &amp; facility disbursements</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.18); padding:0.4rem 0.85rem; font-size:0.75rem;">
            <span style="width:0.45rem;height:0.45rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Financials Active
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

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="reveal-card card p-4 metric-accent-primary">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Entries</span>
                <span style="font-size:1.1rem; line-height:1;">📑</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $totalCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Disbursements logged</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-error">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Disbursed</span>
                <span style="font-size:1.1rem; line-height:1;">💸</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;">₱<?= number_format($totalAmount, 2) ?></p>
            <p class="text-caption text-neutral-400 mt-1">Cumulative expenditure</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Top Category</span>
                <span style="font-size:1.1rem; line-height:1;">🏷️</span>
            </div>
            <p class="text-2xl font-bold text-neutral-900 mt-1 truncate" title="<?= htmlspecialchars($topCategory) ?>">
                <?= htmlspecialchars($topCategory) ?>
            </p>
            <p class="text-caption text-amber-600 mt-1 font-semibold" style="font-variant-numeric: tabular-nums;">
                <?= !empty($categorySpending) ? '₱' . number_format(reset($categorySpending), 2) : '—' ?>
            </p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Latest Entry</span>
                <span style="font-size:1.1rem; line-height:1;">⏱️</span>
            </div>
            <p class="text-2xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;">
                <?= $latestExpense ? '₱' . number_format((float)$latestExpense['amount'], 2) : '—' ?>
            </p>
            <p class="text-caption text-neutral-400 mt-1">
                <?= $latestExpense && !empty($latestExpense['created_at']) ? date('M j, Y', strtotime($latestExpense['created_at'])) : 'No records' ?>
            </p>
        </div>
    </div>

    <!-- Main Content Layout (Table + Log Form) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <!-- Left 2 Cols: Expenses Table -->
        <div class="lg:col-span-2 space-y-4">
            <div class="section-header">
                <div class="flex items-center gap-2">
                    <h2>Expense Records</h2>
                    <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-3 py-1 rounded-full">
                        <?= $totalCount ?> entry<?= $totalCount !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <a href="/admin/ledger/export"
                   class="btn btn-secondary !py-1.5 !px-3.5 !text-xs inline-flex items-center gap-1.5 shadow-xs font-semibold"
                   title="Export detailed financial ledger with user names, descriptions, and proper formatting">
                    <span>📊</span>
                    <span>Export Ledger</span>
                </a>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm" style="min-width: 650px;">
                        <thead>
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th style="width: 40%;">Category &amp; Details</th>
                                <th style="width: 20%;">Amount</th>
                                <th style="width: 20%;">Logged By</th>
                                <th style="width: 20%; text-align: right;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $e): ?>
                                <tr>
                                    <td>
                                        <span class="id-tag">#<?= (int) ($e['id'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="badge badge-neutral font-semibold">
                                                <?= htmlspecialchars($e['category'] ?? 'General') ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($e['description'])): ?>
                                            <div class="text-caption text-neutral-600 mt-1 max-w-sm truncate" title="<?= htmlspecialchars($e['description']) ?>">
                                                <?= htmlspecialchars($e['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="font-bold text-neutral-900 text-sm" style="font-variant-numeric: tabular-nums;">
                                            ₱<?= number_format((float)$e['amount'], 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="font-medium text-neutral-800 text-xs">
                                            <?= htmlspecialchars($e['staff_name'] ?? 'Staff') ?>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="text-caption text-neutral-600 font-medium">
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
                                        <span style="font-size:2.5rem; margin-bottom:0.5rem;">📋</span>
                                        <span class="text-sm font-semibold text-neutral-800">No expenses logged yet</span>
                                        <span class="text-xs text-neutral-500 mt-1">Use the form on the right to log your first expenditure.</span>
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
                <h2>New Expenditure</h2>
            </div>

            <form method="post" action="/admin/expenses" class="form-card">
                <div class="flex items-center gap-2 pb-2 border-b border-neutral-100">
                    <span style="font-size:1.15rem; line-height: 1;">💸</span>
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

                <div class="pt-2 mt-auto">
                    <button type="submit" class="btn btn-primary w-full !py-2 !text-xs font-semibold shadow-xs cursor-pointer">
                        Save Expenditure
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
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
