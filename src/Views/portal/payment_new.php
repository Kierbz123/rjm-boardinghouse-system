<?php
$pageTitle = 'Pay Rent';
ob_start();

$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$boarder = $boarder ?? null;
$recentPayments = $recentPayments ?? [];

// Fallback if rendered without controller variables
if ($boarder === null && !empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $pdo = \App\Database::getConnection();
    $stmt = $pdo->prepare('
        SELECT bp.*, r.room_number, r.base_price, b.label AS bed_label
        FROM boarder_profiles bp
        LEFT JOIN rooms r ON r.id = bp.room_id
        LEFT JOIN beds b ON b.id = bp.bed_id
        WHERE bp.user_id = ?
    ');
    $stmt->execute([$userId]);
    $boarder = $stmt->fetch() ?: null;

    if (empty($recentPayments)) {
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE boarder_id = ? ORDER BY created_at DESC LIMIT 5');
        $stmt->execute([$userId]);
        $recentPayments = $stmt->fetchAll();
    }
}

$expectedPrice = !empty($boarder['base_price']) ? (string) (float) $boarder['base_price'] : '3500.00';
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-6">

    <!-- Page Banner -->
    <div class="page-banner" style="padding: 1.5rem 1.75rem; border-radius: 1rem;">
        <div>
            <h1 class="text-heading-lg font-bold">Pay Rent &amp; Upload Receipt</h1>
            <p class="text-body-sm" style="margin-top: 0.35rem;">Submit your monthly accommodation settlement for automated ledger matching and verification</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15); padding: 0.35rem 0.75rem;">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            Verification Active
        </span>
    </div>

    <!-- Flash Alerts -->
    <?php if ($error): ?>
        <div class="text-body-sm flex items-center gap-2.5" role="alert" style="background: #fef2f2; color: #b91c1c; border-radius: 0.875rem; padding: 1rem 1.25rem; border: 1px solid #fca5a5;">
            <span style="font-size:1.125rem;">⚠</span>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="text-body-sm flex items-center gap-2.5" role="alert" style="background: #f0fdf4; color: #166534; border-radius: 0.875rem; padding: 1rem 1.25rem; border: 1px solid #86efac;">
            <span style="font-size:1.125rem;">✓</span>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Accommodation Billing Rate Info Bar -->
    <?php if (!empty($boarder['room_number'])): ?>
        <div class="card flex flex-wrap items-center justify-between gap-3 text-xs" style="padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.875rem;">
            <div class="flex items-center gap-3">
                <span class="font-bold text-neutral-900 font-mono">
                    Room <?= htmlspecialchars($boarder['room_number']) ?>
                    <?= !empty($boarder['bed_label']) ? ' &middot; ' . htmlspecialchars($boarder['bed_label']) : '' ?>
                </span>
                <span class="text-neutral-400">|</span>
                <span class="text-neutral-600">
                    Monthly Contract Rate: <strong class="text-neutral-900 font-mono">₱<?= number_format((float)$expectedPrice, 2) ?></strong>
                </span>
            </div>
            <span class="badge badge-neutral text-caption" style="padding: 0.2rem 0.6rem;">Due every monthly cycle</span>
        </div>
    <?php endif; ?>

    <!-- Main Grid: Form + Sidebar -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        
        <!-- Left: Form Card (2/3 width on lg) -->
        <div class="lg:col-span-2">
            <form method="post" action="/portal/payments" enctype="multipart/form-data" class="form-card accent-success" style="padding: 1.5rem 1.625rem 1.75rem; border-radius: 1rem; gap: 1.125rem;">
                <div class="flex items-center gap-2.5" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 0.875rem;">
                    <span style="font-size: 1.25rem;">💳</span>
                    <div>
                        <h2 class="text-heading-sm font-bold text-neutral-900">Proof of Payment Submission</h2>
                        <p class="text-caption text-neutral-500" style="margin-top: 0.2rem;">Transfers matching your exact rent amount are automatically approved</p>
                    </div>
                </div>

                <?= \App\Support\Csrf::field() ?>

                <!-- Billing Period -->
                <div>
                    <label for="billing_period" class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">
                        Billing Period (YYYY-MM) <span class="text-error-600">*</span>
                    </label>
                    <input id="billing_period"
                           name="billing_period"
                           type="text"
                           placeholder="Billing period (e.g. 2026-08)"
                           required
                           class="input w-full font-mono"
                           style="padding: 0.5rem 0.75rem; border-radius: 0.5rem;"
                           value="<?= date('Y-m') ?>">
                    <p class="text-caption text-neutral-400" style="margin-top: 0.375rem;">
                        Specify the calendar cycle month (e.g. <?= date('Y-m') ?>).
                    </p>
                </div>

                <!-- Expected & Claimed Amounts -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="expected_amount" class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">
                            Expected Rent Amount (₱) <span class="text-error-600">*</span>
                        </label>
                        <input id="expected_amount"
                               name="expected_amount"
                               type="number"
                               step="0.01"
                               placeholder="Expected rent amount"
                               required
                               class="input w-full font-mono"
                               style="padding: 0.5rem 0.75rem; border-radius: 0.5rem;"
                               value="<?= htmlspecialchars($expectedPrice) ?>">
                        <p class="text-caption text-neutral-400" style="margin-top: 0.375rem;">Standard rate for your assigned bed.</p>
                    </div>

                    <div>
                        <label for="claimed_amount" class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">
                            Amount Paid (₱) <span class="text-error-600">*</span>
                        </label>
                        <input id="claimed_amount"
                               name="claimed_amount"
                               type="number"
                               step="0.01"
                               placeholder="Amount you paid"
                               required
                               class="input w-full font-mono"
                               style="padding: 0.5rem 0.75rem; border-radius: 0.5rem;"
                               value="<?= htmlspecialchars($expectedPrice) ?>">
                        <p class="text-caption text-neutral-400" style="margin-top: 0.375rem;">Exact transferred amount on receipt.</p>
                    </div>
                </div>

                <!-- Proof File Upload -->
                <div>
                    <label for="proof" class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">
                        Proof of Payment Slip / Screenshot
                    </label>
                    <div style="padding: 0.875rem 1rem; background: #f8fafc; border-radius: 0.625rem; border: 1px solid #e2e8f0;">
                        <input id="proof"
                               type="file"
                               name="proof"
                               accept="image/*"
                               class="w-full text-body-sm text-neutral-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-neutral-200 file:text-neutral-800 hover:file:bg-neutral-300 cursor-pointer">
                        <p class="text-caption text-neutral-400" style="margin-top: 0.5rem;">
                            Upload a clear screenshot or photo of your GCash, Maya, or bank transfer reference receipt.
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="padding-top: 0.5rem; display: flex; flex-direction: column; gap: 0.625rem;">
                    <button type="submit" class="btn btn-primary w-full text-sm font-semibold justify-center" style="padding: 0.7rem 1rem; border-radius: 0.625rem;">
                        Submit
                    </button>
                    <a href="/portal/dashboard" class="btn btn-secondary w-full text-center text-xs justify-center" style="padding: 0.575rem 1rem; border-radius: 0.625rem;">
                        &larr; Cancel and return to dashboard
                    </a>
                </div>
            </form>
        </div>

        <!-- Right: Official Payment Channels Sidebar (1/3 width on lg) -->
        <div style="display: flex; flex-direction: column; gap: 1.125rem;">
            <div class="card" style="padding: 1.25rem 1.375rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.875rem;">
                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-600 flex items-center gap-2" style="margin-bottom: 0.875rem;">
                    <span>🏦</span> Official Payment Channels
                </h3>
                <div style="display: flex; flex-direction: column; gap: 0.625rem; font-size: 0.8125rem; color: #475569; line-height: 1.5;">
                    <div style="padding: 0.75rem 0.875rem; border-radius: 0.5rem; background: #ffffff; border: 1px solid #e2e8f0;">
                        <span class="font-bold text-neutral-800 block">GCash / Maya:</span>
                        <span class="font-mono text-neutral-900 block font-bold" style="margin-top: 0.25rem;">0917-823-9912</span>
                        <span style="font-size: 0.6875rem; color: #94a3b8;">RJM Boardinghouse Admin</span>
                    </div>
                    <div style="padding: 0.75rem 0.875rem; border-radius: 0.5rem; background: #ffffff; border: 1px solid #e2e8f0;">
                        <span class="font-bold text-neutral-800 block">BDO Bank Deposit:</span>
                        <span class="font-mono text-neutral-900 block font-bold" style="margin-top: 0.25rem;">0012-3456-7890</span>
                        <span style="font-size: 0.6875rem; color: #94a3b8;">RJM Boardinghouse Management</span>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 1.25rem 1.375rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.875rem;">
                <h3 class="text-xs font-bold flex items-center gap-2" style="color: #166534; margin-bottom: 0.5rem;">
                    <span>⚡</span> Instant Verification
                </h3>
                <p style="font-size: 0.8125rem; color: #15803d; line-height: 1.6;">
                    When your transferred amount matches your expected contract rent, our verification engine reconciles and auto-approves your payment instantly.
                </p>
            </div>
        </div>

    </div>

    <!-- Bottom Section: My Recent Payments History -->
    <?php if (!empty($recentPayments)): ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; padding-top: 0.375rem;">
            <div class="section-header" style="padding-bottom: 0.625rem;">
                <h2 class="text-heading-sm font-semibold text-neutral-900">My Payment History</h2>
                <span class="badge badge-neutral"><?= count($recentPayments) ?> recorded</span>
            </div>

            <div class="card overflow-hidden" style="border-radius: 0.875rem;">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Billing Period</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Expected</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Amount Paid</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Verification Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Proof Slip</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600; text-align: right;">Payment Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <?php foreach ($recentPayments as $p): ?>
                                <?php
                                $status = $p['verification_status'] ?? 'pending';
                                $badgeClass = match($status) {
                                    'auto-matched', 'admin-approved' => 'badge-success',
                                    'flagged'                        => 'badge-warning',
                                    'rejected'                       => 'badge-error',
                                    default                          => 'badge-neutral',
                                };
                                ?>
                                <tr class="hover:bg-neutral-50 transition-colors">
                                    <td style="padding: 0.75rem 1rem; font-family: ui-monospace, monospace; font-weight: 600; color: #0f172a;">
                                        <span class="id-tag"><?= htmlspecialchars($p['billing_period']) ?></span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; color: #475569; font-family: ui-monospace, monospace;">
                                        ₱<?= number_format((float) ($p['expected_amount'] ?? 0), 2) ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0f172a; font-family: ui-monospace, monospace;">
                                        ₱<?= number_format((float) ($p['claimed_amount'] ?? 0), 2) ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <?php if (!empty($p['proof_path'])): ?>
                                            <a href="/<?= htmlspecialchars(ltrim($p['proof_path'], '/')) ?>"
                                               target="_blank"
                                               style="font-size: 0.75rem; font-weight: 600; color: #2563eb; text-decoration: underline; display: inline-flex; align-items: center; gap: 0.25rem; transition: color 0.15s;">
                                                <span>📎</span> View Receipt
                                            </a>
                                        <?php else: ?>
                                            <span class="text-caption text-neutral-400">None attached</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-family: ui-monospace, monospace; color: #94a3b8;">
                                        <?= date('M j, Y', strtotime($p['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
if (window.gsap) {
    gsap.fromTo(".form-card",
        { opacity: 0, y: 12 },
        { opacity: 1, y: 0, duration: 0.35, ease: "power2.out" }
    );
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
