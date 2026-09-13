<?php
$pageTitle = 'Payments';
ob_start();
$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

// Compute summary metrics
$totalPayments = count($payments ?? []);
$pendingPaymentsCount = count(array_filter($payments ?? [], fn($p) => in_array($p['verification_status'] ?? '', ['pending', 'flagged'], true)));
$verifiedPaymentsCount = count(array_filter($payments ?? [], fn($p) => in_array($p['verification_status'] ?? '', ['auto-matched', 'admin-approved'], true)));
$rejectedPaymentsCount = count(array_filter($payments ?? [], fn($p) => ($p['verification_status'] ?? '') === 'rejected'));

$totalCollected = array_sum(array_map(
    fn($p) => in_array($p['verification_status'] ?? '', ['auto-matched', 'admin-approved'], true) ? (float) ($p['claimed_amount'] ?? 0) : 0,
    $payments ?? []
));
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-5">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1 class="text-heading-lg font-bold">Payments &amp; Collections</h1>
            <p class="text-body-sm">Rent tracking, proof-of-payment verifications &amp; ledger records</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15);">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Audit Active
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
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Recorded</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $totalPayments ?></p>
            <p class="text-caption text-neutral-400 mt-1">Transactions logged</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Pending Review</p>
            <p class="text-3xl font-bold <?= $pendingPaymentsCount > 0 ? 'text-amber-600' : 'text-neutral-900' ?> mt-1.5"><?= $pendingPaymentsCount ?></p>
            <?php if ($pendingPaymentsCount > 0): ?>
                <p class="text-caption text-amber-600 mt-1 font-semibold">Requires verification</p>
            <?php else: ?>
                <p class="text-caption text-neutral-400 mt-1">All up to date</p>
            <?php endif; ?>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Verified / Approved</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5"><?= $verifiedPaymentsCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Confirmed payments</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Collected</p>
            <p class="text-3xl font-bold text-neutral-900 mt-1.5">₱<?= number_format($totalCollected, 2) ?></p>
            <p class="text-caption text-neutral-400 mt-1">Verified revenue</p>
        </div>
    </div>

    <!-- Payments Ledger Section -->
    <div>
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Payment Transactions</h2>
                <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-2.5 py-1 rounded-full">
                    <?= $totalPayments ?> record<?= $totalPayments !== 1 ? 's' : '' ?>
                </span>
            </div>
            <a href="/admin/ledger/export"
               class="btn btn-secondary !py-1.5 !px-3 !text-xs inline-flex items-center gap-1.5 shadow-xs"
               title="Export full financial ledger to CSV">
                <span>📊</span>
                <span>Export Ledger (CSV)</span>
            </a>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card p-3 mb-3 flex flex-wrap items-center justify-between gap-3 bg-white">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider mr-1">Filter:</span>
                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs filter-btn active-filter" data-status="all">
                    All (<?= $totalPayments ?>)
                </button>
                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs filter-btn" data-status="pending">
                    Pending Review (<?= $pendingPaymentsCount ?>)
                </button>
                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs filter-btn" data-status="verified">
                    Verified (<?= $verifiedPaymentsCount ?>)
                </button>
                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs filter-btn" data-status="rejected">
                    Rejected (<?= $rejectedPaymentsCount ?>)
                </button>
            </div>
            <div class="relative flex-1 sm:max-w-xs min-w-[200px]">
                <input type="text"
                       id="payment-search"
                       placeholder="Search boarder or period..."
                       class="input input-sm w-full"
                       style="padding-left: 2rem;">
                <span style="position:absolute;left:0.625rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.875rem;">🔍</span>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm" id="payments-table">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide" style="width:4.5rem;">ID</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Boarder</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Period</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Expected</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Claimed</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Proof</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Status</th>
                            <th class="p-3 text-left font-semibold text-neutral-500 text-caption uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <?php
                            $status = $p['verification_status'] ?? 'pending';
                            $badgeClass = match($status) {
                                'auto-matched', 'admin-approved' => 'badge-success',
                                'pending', 'flagged'             => 'badge-warning',
                                'rejected'                       => 'badge-error',
                                default                          => 'badge-neutral',
                            };

                            // Category classification for filter tabs
                            $filterCategory = match($status) {
                                'auto-matched', 'admin-approved' => 'verified',
                                'pending', 'flagged'             => 'pending',
                                'rejected'                       => 'rejected',
                                default                          => 'other',
                            };

                            $expectedVal = (float) ($p['expected_amount'] ?? 0);
                            $claimedVal  = (float) ($p['claimed_amount'] ?? 0);
                            $discrepancy = abs($expectedVal - $claimedVal) > 0.01;
                            ?>
                            <tr class="border-b border-neutral-100 payment-row hover:bg-neutral-50/80 transition-colors"
                                data-category="<?= htmlspecialchars($filterCategory) ?>"
                                data-search="<?= htmlspecialchars(strtolower(($p['boarder_name'] ?? '') . ' ' . ($p['billing_period'] ?? ''))) ?>">
                                <td class="p-3">
                                    <span class="id-tag">#<?= (int) $p['id'] ?></span>
                                </td>
                                <td class="p-3">
                                    <div class="font-bold text-neutral-800 text-sm"><?= htmlspecialchars($p['boarder_name'] ?? 'Unknown') ?></div>
                                    <div class="text-caption text-neutral-400 font-normal">
                                        <?= !empty($p['created_at']) ? date('M j, Y • g:i a', strtotime($p['created_at'])) : '—' ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <span class="badge badge-neutral font-mono font-medium">
                                        <?= htmlspecialchars($p['billing_period'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="font-semibold text-neutral-700">₱<?= number_format($expectedVal, 2) ?></span>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-neutral-900">₱<?= number_format($claimedVal, 2) ?></span>
                                        <?php if ($discrepancy): ?>
                                            <span class="text-amber-600 text-caption font-bold" title="Amount mismatch with expected rent">⚠</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <?php if (!empty($p['proof_path'])): ?>
                                        <?php $proofUrl = str_starts_with($p['proof_path'], '/') ? $p['proof_path'] : '/' . $p['proof_path']; ?>
                                        <a href="<?= htmlspecialchars($proofUrl) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn btn-secondary !py-0.5 !px-2 !text-[11px] inline-flex items-center gap-1"
                                           title="Open uploaded proof receipt">
                                            <span>🧾</span>
                                            <span>Receipt</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-neutral-400 text-caption italic">No upload</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <span class="badge <?= $badgeClass ?> capitalize">
                                        <?= htmlspecialchars(str_replace('-', ' ', $status)) ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <?php if (in_array($status, ['pending', 'flagged'], true)): ?>
                                        <div class="action-cluster" style="display:flex;align-items:center;gap:0.375rem;">
                                            <form method="post" action="/admin/payments/<?= (int) $p['id'] ?>/approve" class="inline">
                                                <?= \App\Support\Csrf::field() ?>
                                                <button type="submit"
                                                        class="btn btn-primary !py-1 !px-2.5 !text-xs cursor-pointer shadow-xs"
                                                        onclick="return confirm('Approve payment #<?= (int) $p['id'] ?> for <?= htmlspecialchars(addslashes($p['boarder_name'])) ?>?');">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="post" action="/admin/payments/<?= (int) $p['id'] ?>/reject" class="inline">
                                                <?= \App\Support\Csrf::field() ?>
                                                <button type="submit"
                                                        class="btn-danger !py-1 !px-2.5 !text-xs cursor-pointer"
                                                        onclick="return confirm('Reject payment #<?= (int) $p['id'] ?> for <?= htmlspecialchars(addslashes($p['boarder_name'])) ?>?');">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-caption text-neutral-400 font-medium">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($payments)): ?>
                            <tr id="empty-state-row">
                                <td colspan="8" class="empty-state" style="padding:3.5rem 0;">
                                    <span style="font-size:2rem;">💳</span>
                                    <span class="text-xs font-medium text-neutral-500 mt-1 block">No payment records found.</span>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <!-- Client-side filter no results row -->
                        <tr id="no-filter-match-row" class="hidden">
                            <td colspan="8" class="empty-state" style="padding:3rem 0;">
                                <span style="font-size:1.75rem;">🔍</span>
                                <span class="text-xs font-medium text-neutral-500 mt-1 block">No transactions match your current search or filter.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.filter-btn.active-filter {
    background-color: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const searchInput = document.getElementById('payment-search');
    const rows = document.querySelectorAll('.payment-row');
    const noMatchRow = document.getElementById('no-filter-match-row');

    let currentStatus = 'all';
    let searchQuery = '';

    function applyFilters() {
        let visibleCount = 0;
        rows.forEach(row => {
            const rowCategory = row.getAttribute('data-category');
            const rowSearch = row.getAttribute('data-search') || '';

            const matchesStatus = currentStatus === 'all' || rowCategory === currentStatus;
            const matchesSearch = !searchQuery || rowSearch.includes(searchQuery);

            if (matchesStatus && matchesSearch) {
                row.classList.remove('hidden');
                visibleCount++;
            } else {
                row.classList.add('hidden');
            }
        });

        if (noMatchRow) {
            if (visibleCount === 0 && rows.length > 0) {
                noMatchRow.classList.remove('hidden');
            } else {
                noMatchRow.classList.add('hidden');
            }
        }
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active-filter'));
            btn.classList.add('active-filter');
            currentStatus = btn.getAttribute('data-status');
            applyFilters();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase().trim();
            applyFilters();
        });
    }

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
