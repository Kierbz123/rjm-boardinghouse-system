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

$balanceDetails = $balanceDetails ?? null;
if ($balanceDetails === null && !empty($_SESSION['user_id'])) {
    $balanceDetails = \App\Services\BillingService::calculateBalance((int) $_SESSION['user_id']);
}
$rentPrice = !empty($boarder['base_price']) ? (float) $boarder['base_price'] : 3500.00;
$totalDue = (float) ($balanceDetails['total_outstanding'] ?? $rentPrice);
$rentDue = (float) ($balanceDetails['rent_due'] ?? $rentPrice);
$penDue = (float) ($balanceDetails['penalties_due'] ?? 0.00);
$defaultExpected = $totalDue > 0 ? $totalDue : $rentPrice;
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-6">

    <!-- Page Banner -->
    <div class="page-banner" style="padding: 1.5rem 1.75rem; border-radius: 1rem;">
        <div>
            <h1 class="text-heading-lg font-bold">Pay Rent &amp; Upload Receipt</h1>
            <p class="text-body-sm" style="margin-top: 0.35rem;">Upload your receipt; an administrator checks it before it counts toward your balance</p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#8cc2a2; border:1px solid rgba(255,255,255,0.15); padding: 0.35rem 0.75rem;">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#5fae84;display:inline-block;margin-right:0.4rem;"></span>
            Reviewed by an admin
        </span>
    </div>

    <!-- Flash Alerts -->
    <?php if ($error): ?>
        <div class="text-body-sm flex items-center gap-2.5" role="alert" style="background: #fdf1f0; color: #912018; border-radius: 0.875rem; padding: 1rem 1.25rem; border: 1px solid #e88f86;">
            <span style="font-size:1.125rem;">⚠</span>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="text-body-sm flex items-center gap-2.5" role="alert" style="background: #eef6f1; color: #245a3f; border-radius: 0.875rem; padding: 1rem 1.25rem; border: 1px solid #8cc2a2;">
            <span style="font-size:1.125rem;">✓</span>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Accommodation Billing Rate & Balance Info Bar -->
    <div class="card flex flex-wrap items-center justify-between gap-4 text-xs" style="padding: 1.25rem 1.5rem; background: linear-gradient(135deg, #f8f7f5 0%, #ffffff 100%); border: 1px solid #e6e5e2; border-radius: 0.875rem;">
        <div class="flex flex-wrap items-center gap-4">
            <span class="font-bold text-neutral-900 font-mono text-sm">
                Room <?= htmlspecialchars($boarder['room_number'] ?? '101') ?>
                <?= !empty($boarder['bed_label']) ? ' &middot; ' . htmlspecialchars($boarder['bed_label']) : '' ?>
            </span>
            <span class="text-neutral-300">|</span>
            <span class="text-neutral-600">
                Monthly Rent Due: <strong class="text-neutral-900 font-mono">₱<?= number_format($rentDue, 2) ?></strong>
            </span>
            <?php if ($penDue > 0): ?>
                <span class="text-neutral-300">|</span>
                <span class="text-amber-700">
                    Unpaid Penalties: <strong class="font-mono">₱<?= number_format($penDue, 2) ?></strong> (<?= count($balanceDetails['unpaid_penalties'] ?? []) ?> pending)
                </span>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-caption text-neutral-500 uppercase font-semibold">Total Balance Due:</span>
            <span class="badge badge-warning text-sm font-bold font-mono px-3 py-1">
                ₱<?= number_format($totalDue, 2) ?>
            </span>
        </div>
    </div>

    <!-- Main Grid: Form + Sidebar -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        
        <!-- Left: Form Card (2/3 width on lg) -->
        <div class="lg:col-span-2">
            <form method="post" action="/portal/payments" enctype="multipart/form-data" class="form-card accent-success" style="padding: 1.5rem 1.625rem 1.75rem; border-radius: 1rem; gap: 1.125rem;">
                <div class="flex items-center gap-2.5" style="border-bottom: 1px solid #f1f0ee; padding-bottom: 0.875rem;">
                    <span style="font-size: 1.25rem;">💳</span>
                    <div>
                        <h2 class="text-heading-sm font-bold text-neutral-900">Proof of Payment Submission</h2>
                        <p class="text-caption text-neutral-500" style="margin-top: 0.2rem;">An administrator checks every receipt before it is applied to your balance</p>
                    </div>
                </div>

                <?= \App\Support\Csrf::field() ?>

                <!-- Quick Select Amount Presets -->
                <div class="bg-neutral-50 p-3 rounded-lg border border-neutral-200">
                    <div class="text-caption font-semibold text-neutral-600 mb-2">Preset Payment Options:</div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary !py-1 !px-3 !text-xs cursor-pointer font-medium" onclick="setAmounts(<?= $totalDue ?>)">
                            Full Balance (₱<?= number_format($totalDue, 2) ?>)
                        </button>
                        <?php if ($rentDue > 0 && $penDue > 0): ?>
                            <button type="button" class="btn btn-secondary !py-1 !px-3 !text-xs cursor-pointer font-medium" onclick="setAmounts(<?= $rentDue ?>)">
                                Rent Only (₱<?= number_format($rentDue, 2) ?>)
                            </button>
                            <button type="button" class="btn btn-secondary !py-1 !px-3 !text-xs cursor-pointer font-medium" onclick="setAmounts(<?= $penDue ?>)">
                                Penalties Only (₱<?= number_format($penDue, 2) ?>)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Billing Period -->
                <div>
                    <label for="billing_period" class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">
                        Billing Period (YYYY-MM) <span class="text-error-600">*</span>
                    </label>
                    <input id="billing_period"
                           name="billing_period"
                           type="month"
                           max="<?= date('Y-m', strtotime('first day of next month')) ?>"
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
                        <span class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">Amount Due (₱)</span>
                        <p class="input w-full font-mono font-bold text-neutral-900" style="padding: 0.5rem 0.75rem; border-radius: 0.5rem; background: #f8f7f5;">
                            <?= number_format($totalDue, 2) ?>
                        </p>
                        <p class="text-caption text-neutral-400" style="margin-top: 0.375rem;">Unpaid rent and penalties, calculated by the system.</p>
                    </div>

                    <div>
                        <label for="claimed_amount" class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">
                            Amount Paid on Slip (₱) <span class="text-error-600">*</span>
                        </label>
                        <input id="claimed_amount"
                               name="claimed_amount"
                               type="number"
                               step="0.01"
                               placeholder="Amount you paid"
                               required
                               class="input w-full font-mono font-bold text-emerald-700"
                               style="padding: 0.5rem 0.75rem; border-radius: 0.5rem;"
                               value="<?= htmlspecialchars(number_format($defaultExpected, 2, '.', '')) ?>">
                        <p class="text-caption text-neutral-400" style="margin-top: 0.375rem;">Exact transferred amount on receipt.</p>
                    </div>
                </div>

                <script>
                function setAmounts(val) {
                    document.getElementById('claimed_amount').value = parseFloat(val).toFixed(2);
                }
                </script>

                <!-- Proof File Upload -->
                <div>
                    <label for="proof" class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">
                        Proof of Payment Slip / Screenshot <span class="text-error-600">*</span>
                    </label>
                    <div style="padding: 0.875rem 1rem; background: #f8f7f5; border-radius: 0.625rem; border: 1px solid #e6e5e2;">
                        <input id="proof"
                               type="file"
                               name="proof"
                               accept="image/jpeg,image/png,image/webp"
                               required
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
            <div class="card" style="padding: 1.25rem 1.375rem; background: #f8f7f5; border: 1px solid #e6e5e2; border-radius: 0.875rem;">
                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-600 flex items-center gap-2" style="margin-bottom: 0.875rem;">
                    <span>🏦</span> Official Payment Channels
                </h3>
                <div style="display: flex; flex-direction: column; gap: 0.625rem; font-size: 0.8125rem; color: #555452; line-height: 1.5;">
                    <div style="padding: 0.75rem 0.875rem; border-radius: 0.5rem; background: #ffffff; border: 1px solid #e6e5e2;">
                        <span class="font-bold text-neutral-800 block">GCash / Maya:</span>
                        <span class="font-mono text-neutral-900 block font-bold" style="margin-top: 0.25rem;">0917-823-9912</span>
                        <span style="font-size: 0.6875rem; color: #9d9b97;">RJM Boardinghouse Admin</span>
                    </div>
                    <div style="padding: 0.75rem 0.875rem; border-radius: 0.5rem; background: #ffffff; border: 1px solid #e6e5e2;">
                        <span class="font-bold text-neutral-800 block">BDO Bank Deposit:</span>
                        <span class="font-mono text-neutral-900 block font-bold" style="margin-top: 0.25rem;">0012-3456-7890</span>
                        <span style="font-size: 0.6875rem; color: #9d9b97;">RJM Boardinghouse Management</span>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 1.25rem 1.375rem; background: #eef6f1; border: 1px solid #d7ebdf; border-radius: 0.875rem;">
                <h3 class="text-xs font-bold flex items-center gap-2" style="color: #245a3f; margin-bottom: 0.5rem;">
                    How approval works
                </h3>
                <p style="font-size: 0.8125rem; color: #245a3f; line-height: 1.6;">
                    An administrator compares your receipt with the amount you entered. Once approved, it pays your oldest unpaid month first, then any penalties.
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
                            <tr style="background: #f8f7f5; border-bottom: 1px solid #e6e5e2; text-align: left; color: #6b6b6b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
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
                                    <td style="padding: 0.75rem 1rem; font-family: ui-monospace, monospace; font-weight: 600; color: #0a0a0a;">
                                        <span class="id-tag"><?= htmlspecialchars($p['billing_period']) ?></span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; color: #555452; font-family: ui-monospace, monospace;">
                                        ₱<?= number_format((float) ($p['expected_amount'] ?? 0), 2) ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0a0a0a; font-family: ui-monospace, monospace;">
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
                                               style="font-size: 0.75rem; font-weight: 600; color: #b15f2c; text-decoration: underline; display: inline-flex; align-items: center; gap: 0.25rem; transition: color 0.15s;">
                                                <span>📎</span> View Receipt
                                            </a>
                                        <?php else: ?>
                                            <span class="text-caption text-neutral-400">None attached</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-family: ui-monospace, monospace; color: #9d9b97;">
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
