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
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- Page Banner -->
    <div class="page-banner">
        <div>
            <h1>Payments &amp; Collections</h1>
            <p>Rent tracking, proof-of-payment verifications &amp; ledger records</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.18); padding:0.4rem 0.85rem; font-size:0.75rem;">
            <span style="width:0.45rem;height:0.45rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Audit Active
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
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Recorded</span>
                <span style="font-size:1.1rem; line-height:1;">📑</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $totalPayments ?></p>
            <p class="text-caption text-neutral-400 mt-1">Transactions logged</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-warning">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Pending Review</span>
                <span style="font-size:1.1rem; line-height:1;">⏳</span>
            </div>
            <p class="text-3xl font-bold <?= $pendingPaymentsCount > 0 ? 'text-amber-600' : 'text-neutral-900' ?> mt-1" style="font-variant-numeric: tabular-nums;"><?= $pendingPaymentsCount ?></p>
            <?php if ($pendingPaymentsCount > 0): ?>
                <p class="text-caption text-amber-600 mt-1 font-semibold">Requires verification</p>
            <?php else: ?>
                <p class="text-caption text-neutral-400 mt-1">All up to date</p>
            <?php endif; ?>
        </div>
        <div class="reveal-card card p-4 metric-accent-success">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Verified / Approved</span>
                <span style="font-size:1.1rem; line-height:1;">✓</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;"><?= $verifiedPaymentsCount ?></p>
            <p class="text-caption text-neutral-400 mt-1">Confirmed payments</p>
        </div>
        <div class="reveal-card card p-4 metric-accent-info">
            <div class="flex items-center justify-between mb-1">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Collected</span>
                <span style="font-size:1.1rem; line-height:1;">₱</span>
            </div>
            <p class="text-3xl font-bold text-neutral-900 mt-1" style="font-variant-numeric: tabular-nums;">₱<?= number_format($totalCollected, 2) ?></p>
            <p class="text-caption text-neutral-400 mt-1">Verified revenue</p>
        </div>
    </div>

    <!-- Payments Ledger Section -->
    <div>
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2>Payment Transactions</h2>
                <span class="text-caption text-neutral-500 font-medium bg-neutral-100 px-3 py-1 rounded-full">
                    <?= $totalPayments ?> record<?= $totalPayments !== 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="flex items-center gap-2">
                <a href="/admin/ledger/export"
                   class="btn btn-secondary !py-1.5 !px-3.5 !text-xs inline-flex items-center gap-1.5 shadow-xs font-semibold"
                   title="Export detailed financial ledger with user names, descriptions, and proper formatting">
                    <span>📊</span>
                    <span>Export Ledger</span>
                </a>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="card p-3 mb-3 flex flex-wrap items-center justify-between gap-3 bg-white">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-caption font-semibold text-neutral-500 uppercase tracking-wider mr-1">Filter:</span>
                <button type="button" class="btn btn-secondary !py-1.5 !px-3 !text-xs filter-btn active-filter cursor-pointer" data-status="all">
                    All (<?= $totalPayments ?>)
                </button>
                <button type="button" class="btn btn-secondary !py-1.5 !px-3 !text-xs filter-btn cursor-pointer" data-status="pending">
                    Pending Review (<?= $pendingPaymentsCount ?>)
                </button>
                <button type="button" class="btn btn-secondary !py-1.5 !px-3 !text-xs filter-btn cursor-pointer" data-status="verified">
                    Verified (<?= $verifiedPaymentsCount ?>)
                </button>
                <button type="button" class="btn btn-secondary !py-1.5 !px-3 !text-xs filter-btn cursor-pointer" data-status="rejected">
                    Rejected (<?= $rejectedPaymentsCount ?>)
                </button>
            </div>
            <div class="relative flex-1 sm:max-w-xs min-w-[220px]">
                <input type="text"
                       id="payment-search"
                       placeholder="Search boarder or period..."
                       class="input input-sm w-full"
                       style="padding-left: 2.25rem !important; padding-right: 1rem !important; height: 2.25rem;">
                <span style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.875rem;pointer-events:none;">🔍</span>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm" id="payments-table" style="min-width: 900px;">
                    <thead>
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th style="width: 25%;">Boarder</th>
                            <th style="width: 15%;">Period</th>
                            <th style="width: 14%;">Expected</th>
                            <th style="width: 18%;">Claimed</th>
                            <th style="width: 11%;">Proof</th>
                            <th style="width: 12%;">Status</th>
                            <th style="width: 150px; text-align: right;">Actions</th>
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
                                    <?php if (!empty($p['allocations'])): ?>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <?php foreach ($p['allocations'] as $al): ?>
                                                <?php if ($al['allocation_type'] === 'rent'): ?>
                                                    <span class="badge badge-neutral !text-[10px] !py-0 !px-1.5" title="Rent applied for <?= htmlspecialchars($al['reference_id']) ?>">
                                                        Rent: ₱<?= number_format((float)$al['amount'], 2) ?>
                                                    </span>
                                                <?php elseif ($al['allocation_type'] === 'penalty'): ?>
                                                    <span class="badge badge-warning !text-[10px] !py-0 !px-1.5" title="Penalty #<?= $al['reference_id'] ?>: <?= htmlspecialchars($al['penalty_rule_name'] ?? '') ?>">
                                                        Pen: ₱<?= number_format((float)$al['amount'], 2) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
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
                                            <form method="post" action="/admin/payments/<?= (int) $p['id'] ?>/approve" class="inline payment-approve-form" data-payment-id="<?= (int) $p['id'] ?>" data-boarder-name="<?= htmlspecialchars($p['boarder_name']) ?>">
                                                <?= \App\Support\Csrf::field() ?>
                                                <button type="button"
                                                        class="btn btn-primary !py-1 !px-2.5 !text-xs cursor-pointer shadow-xs payment-approve-btn"
                                                        data-payment-id="<?= (int) $p['id'] ?>"
                                                        data-boarder-name="<?= htmlspecialchars($p['boarder_name']) ?>">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="post" action="/admin/payments/<?= (int) $p['id'] ?>/reject" class="inline payment-reject-form" data-payment-id="<?= (int) $p['id'] ?>" data-boarder-name="<?= htmlspecialchars($p['boarder_name']) ?>">
                                                <?= \App\Support\Csrf::field() ?>
                                                <button type="button"
                                                        class="btn-danger !py-1 !px-2.5 !text-xs cursor-pointer payment-reject-btn"
                                                        data-payment-id="<?= (int) $p['id'] ?>"
                                                        data-boarder-name="<?= htmlspecialchars($p['boarder_name']) ?>">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif (in_array($status, ['auto-matched', 'admin-approved'], true)): ?>
                                        <form method="post" action="/admin/payments/<?= (int) $p['id'] ?>/reject" class="inline payment-reject-form" data-payment-id="<?= (int) $p['id'] ?>" data-boarder-name="<?= htmlspecialchars($p['boarder_name']) ?>">
                                            <?= \App\Support\Csrf::field() ?>
                                            <button type="button"
                                                    class="btn-danger !py-1 !px-2.5 !text-xs cursor-pointer payment-reject-btn"
                                                    title="Undo this approval and recalculate the boarder's balance"
                                                    data-payment-id="<?= (int) $p['id'] ?>"
                                                    data-boarder-name="<?= htmlspecialchars($p['boarder_name']) ?>">
                                                Reverse
                                            </button>
                                        </form>
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

/* Payment Confirmation Modal Styling (matching logout modal) */
@keyframes paymentModalFadeIn {
    0% { opacity: 0; }
    100% { opacity: 1; }
}
#payment-approve-modal {
    animation: paymentModalFadeIn 180ms ease-out forwards;
}
#payment-reject-modal {
    animation: paymentModalFadeIn 180ms ease-out forwards;
}
#payment-modal-cancel:hover,
#payment-modal-cancel-reject:hover {
    background-color: #f1f5f9 !important;
    color: #0f172a !important;
}
#payment-modal-confirm-approve:hover {
    filter: brightness(1.08);
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
}
#payment-modal-confirm-reject:hover {
    filter: brightness(1.08);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
}
#payment-modal-confirm-approve:active,
#payment-modal-confirm-reject:active {
    transform: scale(0.97);
}
</style>

<!-- Payment Approve Confirmation Modal (matching logout modal design) -->
<div id="payment-approve-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:99999; background:rgba(15,23,42,0.55); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;" role="dialog" aria-modal="true" aria-labelledby="payment-approve-heading">
    <div id="payment-approve-card" style="background: rgb(255, 255, 255); border-radius: 1rem; padding: 1.5rem 1.75rem; max-width: 22rem; width: 100%; margin: 0px auto; text-align: center; box-shadow: rgba(0, 0, 0, 0.25) 0px 25px 50px -12px;">
        <div id="payment-approve-icon-wrap" style="width: 3.5rem; height: 3.5rem; border-radius: 50%; border: 2px solid #16a34a; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0px auto 0.75rem; user-select: none;">
            ✓
        </div>
        <h3 id="payment-approve-heading" style="font-size: 1.1rem; font-weight: 700; color: #0f172a; letter-spacing: -0.01em; margin: 0 0 0.375rem;">Approve Payment?</h3>
        <p id="payment-approve-subtext" style="font-size: 0.8rem; color: #64748b; line-height: 1.6; margin: 0 0 1.5rem; padding: 0 0.5rem;">Are you sure you want to approve this payment? This will mark it as verified.</p>
        <p id="payment-approve-details" style="font-size: 0.75rem; color: #94a3b8; line-height: 1.5; margin: 0 0 1.5rem; padding: 0 0.5rem; font-style: italic;"></p>
        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; padding-top: 0.25rem;">
            <button type="button" id="payment-modal-cancel" style="padding: 0.5rem 1rem; border-radius: 0.75rem; font-size: 0.75rem; font-weight: 600; color: #64748b; background: transparent; border: none; cursor: pointer; transition: all 150ms;">
                Cancel
            </button>
            <button type="button" id="payment-modal-confirm-approve" style="color: rgb(255, 255, 255); font-weight: 600; border-radius: 0.75rem; padding: 0.5rem 1.25rem; font-size: 0.75rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem; transition: 150ms; background: linear-gradient(135deg, #16a34a, #15803d);">
                <span>Yes</span>
                <span style="font-size: 0.95rem;">&rarr;</span>
            </button>
        </div>
    </div>
</div>

<!-- Payment Reject Confirmation Modal (matching logout modal design) -->
<div id="payment-reject-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:99999; background:rgba(15,23,42,0.55); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;" role="dialog" aria-modal="true" aria-labelledby="payment-reject-heading">
    <div id="payment-reject-card" style="background: rgb(255, 255, 255); border-radius: 1rem; padding: 1.5rem 1.75rem; max-width: 22rem; width: 100%; margin: 0px auto; text-align: center; box-shadow: rgba(0, 0, 0, 0.25) 0px 25px 50px -12px;">
        <div id="payment-reject-icon-wrap" style="width: 3.5rem; height: 3.5rem; border-radius: 50%; border: 2px solid rgb(239, 68, 68); color: rgb(220, 38, 38); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0px auto 0.75rem; user-select: none;">
            ✕
        </div>
        <h3 id="payment-reject-heading" style="font-size: 1.1rem; font-weight: 700; color: #0f172a; letter-spacing: -0.01em; margin: 0 0 0.375rem;">Reject Payment?</h3>
        <p id="payment-reject-subtext" style="font-size: 0.8rem; color: #64748b; line-height: 1.6; margin: 0 0 1.5rem; padding: 0 0.5rem;">Are you sure you want to reject this payment? This action cannot be undone.</p>
        <p id="payment-reject-details" style="font-size: 0.75rem; color: #94a3b8; line-height: 1.5; margin: 0 0 1.5rem; padding: 0 0.5rem; font-style: italic;"></p>
        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem; padding-top: 0.25rem;">
            <button type="button" id="payment-modal-cancel-reject" style="padding: 0.5rem 1rem; border-radius: 0.75rem; font-size: 0.75rem; font-weight: 600; color: #64748b; background: transparent; border: none; cursor: pointer; transition: all 150ms;">
                Cancel
            </button>
            <button type="button" id="payment-modal-confirm-reject" style="color: rgb(255, 255, 255); font-weight: 600; border-radius: 0.75rem; padding: 0.5rem 1.25rem; font-size: 0.75rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem; transition: 150ms; background: linear-gradient(135deg, rgb(239, 68, 68), rgb(220, 38, 38));">
                <span>Yes</span>
                <span style="font-size: 0.95rem;">&rarr;</span>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Payment Filter Functionality
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

    // Payment Confirmation Modals
    const approveButtons = document.querySelectorAll('.payment-approve-btn');
    const rejectButtons = document.querySelectorAll('.payment-reject-btn');
    const approveModal = document.getElementById('payment-approve-modal');
    const rejectModal = document.getElementById('payment-reject-modal');
    const approveCard = document.getElementById('payment-approve-card');
    const rejectCard = document.getElementById('payment-reject-card');
    const approveCancelBtn = document.getElementById('payment-modal-cancel');
    const rejectCancelBtn = document.getElementById('payment-modal-cancel-reject');
    const approveConfirmBtn = document.getElementById('payment-modal-confirm-approve');
    const rejectConfirmBtn = document.getElementById('payment-modal-confirm-reject');
    let currentApproveForm = null;
    let currentRejectForm = null;

    function showApproveModal(form) {
        currentApproveForm = form;
        const boarderName = form.getAttribute('data-boarder-name') || 'Boarder';
        const paymentId = form.getAttribute('data-payment-id') || '';
        
        if (!approveModal) return;
        approveModal.style.display = 'flex';
        
        const detailsEl = document.getElementById('payment-approve-details');
        if (detailsEl) {
            detailsEl.textContent = `Payment #${paymentId} for ${boarderName}`;
        }
        if (approveConfirmBtn) {
            approveConfirmBtn.disabled = false;
            approveConfirmBtn.style.opacity = '';
            approveConfirmBtn.style.cursor = '';
        }
        if (window.gsap && approveCard) {
            gsap.fromTo(approveCard,
                { opacity: 0, scale: 0.9, y: 12 },
                { opacity: 1, scale: 1, y: 0, duration: 0.25, ease: 'back.out(1.7)' }
            );
        }
        if (approveCancelBtn) approveCancelBtn.focus();
    }

    function hideApproveModal() {
        if (!approveModal) return;
        approveModal.style.display = 'none';
        currentApproveForm = null;
    }

    approveButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const form = btn.closest('.payment-approve-form');
            if (form) {
                showApproveModal(form);
            }
        });
    });

    if (approveCancelBtn) {
        approveCancelBtn.addEventListener('click', hideApproveModal);
    }

    if (approveConfirmBtn) {
        approveConfirmBtn.addEventListener('click', () => {
            if (currentApproveForm) {
                const formToSubmit = currentApproveForm;
                approveConfirmBtn.disabled = true;
                approveConfirmBtn.style.opacity = '0.7';
                approveConfirmBtn.style.cursor = 'not-allowed';
                hideApproveModal();
                formToSubmit.submit();
            }
        });
    }

    // Payment Reject Modal Functions
    function showRejectModal(form) {
        currentRejectForm = form;
        const boarderName = form.getAttribute('data-boarder-name') || 'Boarder';
        const paymentId = form.getAttribute('data-payment-id') || '';
        
        if (!rejectModal) return;
        rejectModal.style.display = 'flex';
        
        const detailsEl = document.getElementById('payment-reject-details');
        if (detailsEl) {
            detailsEl.textContent = `Payment #${paymentId} for ${boarderName}`;
        }
        if (rejectConfirmBtn) {
            rejectConfirmBtn.disabled = false;
            rejectConfirmBtn.style.opacity = '';
            rejectConfirmBtn.style.cursor = '';
        }
        if (window.gsap && rejectCard) {
            gsap.fromTo(rejectCard,
                { opacity: 0, scale: 0.9, y: 12 },
                { opacity: 1, scale: 1, y: 0, duration: 0.25, ease: 'back.out(1.7)' }
            );
        }
        if (rejectCancelBtn) rejectCancelBtn.focus();
    }

    function hideRejectModal() {
        if (!rejectModal) return;
        rejectModal.style.display = 'none';
        currentRejectForm = null;
    }

    rejectButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const form = btn.closest('.payment-reject-form');
            if (form) {
                showRejectModal(form);
            }
        });
    });

    if (rejectCancelBtn) {
        rejectCancelBtn.addEventListener('click', hideRejectModal);
    }

    if (rejectConfirmBtn) {
        rejectConfirmBtn.addEventListener('click', () => {
            if (currentRejectForm) {
                const formToSubmit = currentRejectForm;
                rejectConfirmBtn.disabled = true;
                rejectConfirmBtn.style.opacity = '0.7';
                rejectConfirmBtn.style.cursor = 'not-allowed';
                hideRejectModal();
                formToSubmit.submit();
            }
        });
    }

    // Close modals on backdrop click
    [approveModal, rejectModal].forEach(modal => {
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    if (modal === approveModal) hideApproveModal();
                    if (modal === rejectModal) hideRejectModal();
                }
            });
        }
    });

    // Close modals on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (approveModal && approveModal.style.display === 'flex') hideApproveModal();
            if (rejectModal && rejectModal.style.display === 'flex') hideRejectModal();
        }
    });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
