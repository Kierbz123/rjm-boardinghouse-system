<?php
$pageTitle = 'Resident Portal';
ob_start();

$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$notifications = $notifications ?? [];
$boarder = $boarder ?? null;
$payments = $payments ?? [];
$maintenanceRequests = $maintenanceRequests ?? [];

// Fallback if rendered directly without controller variables
if ($boarder === null && !empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $pdo = \App\Database::getConnection();
    $stmt = $pdo->prepare('
        SELECT bp.*, u.name, u.email, r.room_number, r.base_price, r.floor, b.label AS bed_label
        FROM boarder_profiles bp
        JOIN users u ON u.id = bp.user_id
        LEFT JOIN rooms r ON r.id = bp.room_id
        LEFT JOIN beds b ON b.id = bp.bed_id
        WHERE bp.user_id = ?
    ');
    $stmt->execute([$userId]);
    $boarder = $stmt->fetch() ?: null;

    if (empty($payments)) {
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE boarder_id = ? ORDER BY created_at DESC LIMIT 10');
        $stmt->execute([$userId]);
        $payments = $stmt->fetchAll();
    }
    if (empty($maintenanceRequests)) {
        $stmt = $pdo->prepare('SELECT * FROM maintenance_requests WHERE boarder_id = ? ORDER BY created_at DESC LIMIT 10');
        $stmt->execute([$userId]);
        $maintenanceRequests = $stmt->fetchAll();
    }
}

$notificationsCount = count($notifications ?? []);
$openRequestsCount = count(array_filter($maintenanceRequests ?? [], fn($m) => ($m['status'] ?? '') !== 'resolved'));

$tierBadges = [
    'critical' => 'badge-error',
    'high'     => 'badge-warning',
    'medium'   => 'badge-info',
    'low'      => 'badge-neutral',
];
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-6">

    <!-- Page Banner -->
    <div class="page-banner" style="padding: 1.5rem 1.75rem; border-radius: 1rem;">
        <div>
            <h1 class="text-heading-lg font-bold">Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Resident') ?></h1>
            <p class="text-body-sm" style="margin-top: 0.35rem;">
                Resident Portal &middot;
                <?= !empty($boarder['room_number']) ? 'Room ' . htmlspecialchars($boarder['room_number']) . ' &middot; ' . htmlspecialchars($boarder['bed_label'] ?? 'Bed') : 'Accommodations &amp; Services' ?>
            </p>
        </div>
        <span class="badge badge-success" style="background:rgba(255,255,255,0.12); color:#86efac; border:1px solid rgba(255,255,255,0.15); padding: 0.35rem 0.75rem;">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#4ade80;display:inline-block;margin-right:0.4rem;"></span>
            <?= htmlspecialchars(ucfirst($boarder['status'] ?? 'Active')) ?> Resident
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

    <!-- Emergency SOS Command Station -->
    <div class="reveal-card" style="background: linear-gradient(135deg, #fff5f5 0%, #ffffff 50%, #fef2f2 100%); border: 2px solid #fecaca; border-radius: 1.125rem; padding: 1.75rem 2rem; box-shadow: 0 2px 8px 0 rgba(239,68,68,0.06);">
        <div class="flex flex-col md:flex-row items-center justify-between gap-5">
            <div class="flex items-start gap-4">
                <div style="width: 3.25rem; height: 3.25rem; border-radius: 0.875rem; background: #dc2626; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; box-shadow: 0 2px 6px rgba(220,38,38,0.25);">
                    🚨
                </div>
                <div>
                    <h2 class="text-heading-sm font-bold text-neutral-900 flex items-center gap-2.5">
                        Emergency SOS Dispatch
                        <span class="badge badge-error uppercase" style="font-size: 0.625rem; letter-spacing: 0.05em; padding: 0.2rem 0.6rem;">Priority 1</span>
                    </h2>
                    <p class="text-body-sm text-neutral-600" style="margin-top: 0.4rem; line-height: 1.6; max-width: 34rem;">
                        Press below to broadcast an immediate emergency alert to staff and security. Your location will automatically broadcast as 
                        <strong>Room <?= htmlspecialchars($boarder['room_number'] ?? '101') ?><?= !empty($boarder['bed_label']) ? ' (' . htmlspecialchars($boarder['bed_label']) . ')' : '' ?></strong>.
                    </p>
                </div>
            </div>
            <div class="flex-shrink-0 w-full md:w-auto text-center md:text-right">
                <button id="sos-button" type="button"
                        class="w-full md:w-auto text-white font-bold text-sm cursor-pointer inline-flex items-center justify-center gap-2"
                        style="background: linear-gradient(135deg, #dc2626, #b91c1c); border-radius: 0.75rem; padding: 0.9rem 1.75rem; box-shadow: 0 4px 12px rgba(220,38,38,0.3); transition: all 0.15s ease; border: none;"
                        onmouseover="this.style.boxShadow='0 6px 16px rgba(220,38,38,0.4)'; this.style.transform='translateY(-1px)'"
                        onmouseout="this.style.boxShadow='0 4px 12px rgba(220,38,38,0.3)'; this.style.transform='translateY(0)'">
                    <span style="font-size: 1.15rem;">🚨</span>
                    <span>Trigger Emergency SOS</span>
                </button>
            </div>
        </div>
        <p id="sos-status" class="text-body-sm text-center font-medium mt-3 empty:hidden"></p>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="reveal-card card metric-accent-primary" style="padding: 1.25rem 1.375rem; border-radius: 0.875rem;">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">My Accommodation</p>
            <p class="text-2xl font-bold text-neutral-900" style="margin-top: 0.5rem;">
                <?= !empty($boarder['room_number']) ? 'Room ' . htmlspecialchars($boarder['room_number']) : 'Unassigned' ?>
            </p>
            <p class="text-caption text-neutral-400" style="margin-top: 0.375rem;">
                Bed: <?= htmlspecialchars($boarder['bed_label'] ?? 'Pending') ?>
            </p>
        </div>
        <div class="reveal-card card metric-accent-success" style="padding: 1.25rem 1.375rem; border-radius: 0.875rem;">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Monthly Rent</p>
            <p class="text-2xl font-bold text-neutral-900" style="margin-top: 0.5rem;">
                ₱<?= number_format((float)($boarder['base_price'] ?? 3500), 2) ?>
            </p>
            <p class="text-caption text-neutral-400" style="margin-top: 0.375rem;">Due every cycle</p>
        </div>
        <div class="reveal-card card metric-accent-info" style="padding: 1.25rem 1.375rem; border-radius: 0.875rem;">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Rent Payments</p>
            <p class="text-2xl font-bold text-neutral-900" style="margin-top: 0.5rem;">
                <?= count($payments ?? []) ?>
            </p>
            <p class="text-caption text-neutral-400" style="margin-top: 0.375rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= !empty($payments[0]) ? 'Latest: ' . htmlspecialchars($payments[0]['billing_period']) : 'No records yet' ?>
            </p>
        </div>
        <div class="reveal-card card metric-accent-warning" style="padding: 1.25rem 1.375rem; border-radius: 0.875rem;">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Repair Requests</p>
            <p class="text-2xl font-bold <?= $openRequestsCount > 0 ? 'text-amber-600' : 'text-neutral-900' ?>" style="margin-top: 0.5rem;">
                <?= $openRequestsCount ?> Open
            </p>
            <p class="text-caption text-neutral-400" style="margin-top: 0.375rem;">
                <?= count($maintenanceRequests ?? []) ?> total tickets
            </p>
        </div>
    </div>

    <!-- Quick Action Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Action 1: Report a Repair -->
        <a href="/portal/maintenance/new"
           class="reveal-card group block relative overflow-hidden"
           style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.5rem 1.625rem; box-shadow: 0 1px 3px rgba(15,23,42,0.06); transition: all 0.2s ease; text-decoration: none;"
           onmouseover="this.style.borderColor='#cbd5e1'; this.style.boxShadow='0 4px 12px rgba(15,23,42,0.1)'; this.style.transform='translateY(-1px)'"
           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 1px 3px rgba(15,23,42,0.06)'; this.style.transform='translateY(0)'">
            <div class="flex items-start gap-4">
                <div style="width: 2.875rem; height: 2.875rem; border-radius: 0.75rem; background: #fffbeb; color: #d97706; border: 1px solid #fde68a; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    🔧
                </div>
                <div style="flex: 1; min-width: 0;">
                    <h3 class="text-heading-sm font-bold text-neutral-900 flex items-center gap-2">
                        Report a Repair
                        <span class="badge badge-warning" style="font-size: 0.625rem; padding: 0.15rem 0.5rem;">AI Triaged</span>
                    </h3>
                    <p class="text-body-sm text-neutral-500" style="margin-top: 0.4rem; line-height: 1.6;">
                        Experiencing plumbing, electrical, or structural issues? Submit details with optional photos or videos for automated priority scoring and rapid dispatch.
                    </p>
                </div>
            </div>
            <div style="margin-top: 1.125rem; padding-top: 0.875rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; font-weight: 600; color: #2563eb;">
                <span>Submit Repair Request</span>
                <span style="transition: transform 0.15s ease;">&rarr;</span>
            </div>
        </a>

        <!-- Action 2: Pay Rent -->
        <a href="/portal/payments/new"
           class="reveal-card group block relative overflow-hidden"
           style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.5rem 1.625rem; box-shadow: 0 1px 3px rgba(15,23,42,0.06); transition: all 0.2s ease; text-decoration: none;"
           onmouseover="this.style.borderColor='#cbd5e1'; this.style.boxShadow='0 4px 12px rgba(15,23,42,0.1)'; this.style.transform='translateY(-1px)'"
           onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 1px 3px rgba(15,23,42,0.06)'; this.style.transform='translateY(0)'">
            <div class="flex items-start gap-4">
                <div style="width: 2.875rem; height: 2.875rem; border-radius: 0.75rem; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    💳
                </div>
                <div style="flex: 1; min-width: 0;">
                    <h3 class="text-heading-sm font-bold text-neutral-900 flex items-center gap-2">
                        Pay Rent &amp; Upload Receipt
                        <span class="badge badge-success" style="font-size: 0.625rem; padding: 0.15rem 0.5rem;">Auto Match</span>
                    </h3>
                    <p class="text-body-sm text-neutral-500" style="margin-top: 0.4rem; line-height: 1.6;">
                        Settle your monthly accommodation fee. Enter billing period details and upload GCash, Maya, or bank transfer confirmation for automated verification.
                    </p>
                </div>
            </div>
            <div style="margin-top: 1.125rem; padding-top: 0.875rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; font-weight: 600; color: #16a34a;">
                <span>Submit Proof of Payment</span>
                <span style="transition: transform 0.15s ease;">&rarr;</span>
            </div>
        </a>
    </div>

    <!-- Data Tables Grid: My Repair Requests & My Payment History -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Left Table: My Repair Requests -->
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div class="section-header" style="padding-bottom: 0.625rem;">
                <div class="flex items-center gap-2">
                    <h2 class="text-heading-sm font-semibold text-neutral-900">My Repair Requests</h2>
                    <span class="badge badge-neutral"><?= count($maintenanceRequests ?? []) ?> total</span>
                </div>
                <a href="/portal/maintenance/new" class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    + New Ticket
                </a>
            </div>

            <div class="card overflow-hidden" style="border-radius: 0.875rem;">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Priority</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Issue</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600; text-align: right;">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <?php foreach (array_slice($maintenanceRequests, 0, 5) as $m): ?>
                                <tr class="hover:bg-neutral-50 transition-colors">
                                    <td style="padding: 0.75rem 1rem;">
                                        <span class="badge <?= $tierBadges[$m['priority_tier'] ?? ''] ?? 'badge-neutral' ?> uppercase" style="font-size: 0.625rem; font-weight: 700; padding: 0.15rem 0.5rem;">
                                            <?= htmlspecialchars($m['priority_tier'] ?? 'pending') ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <div class="font-medium text-neutral-900 capitalize text-xs">
                                            <?= htmlspecialchars($m['category'] ?? 'other') ?>
                                        </div>
                                        <div class="text-caption text-neutral-500" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 14rem;" title="<?= htmlspecialchars($m['description']) ?>">
                                            <?= htmlspecialchars($m['description']) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <span class="badge <?= ($m['status'] ?? '') === 'resolved' ? 'badge-success' : (($m['status'] ?? '') === 'in_progress' ? 'badge-info' : 'badge-warning') ?>">
                                            <?= htmlspecialchars($m['status'] ?? 'open') ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-family: ui-monospace, monospace; color: #94a3b8; white-space: nowrap;">
                                        <?= date('M j', strtotime($m['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($maintenanceRequests)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div style="padding: 2.5rem 1.5rem; text-align: center; color: #94a3b8;">
                                            <div style="font-size: 1.75rem; margin-bottom: 0.4rem;">🔧</div>
                                            <p class="font-semibold text-neutral-700 text-body-sm">No repair tickets yet.</p>
                                            <p class="text-caption text-neutral-400" style="margin-top: 0.25rem;">Everything in your room is in working order.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Table: My Payment Ledger -->
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div class="section-header" style="padding-bottom: 0.625rem;">
                <div class="flex items-center gap-2">
                    <h2 class="text-heading-sm font-semibold text-neutral-900">My Rent Ledger</h2>
                    <span class="badge badge-neutral"><?= count($payments ?? []) ?> payments</span>
                </div>
                <a href="/portal/payments/new" class="text-xs font-semibold" style="color: #16a34a; transition: color 0.15s;">
                    + Pay Rent
                </a>
            </div>

            <div class="card overflow-hidden" style="border-radius: 0.875rem;">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Period</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Paid</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Verification</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600; text-align: right;">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <?php foreach (array_slice($payments, 0, 5) as $p): ?>
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
                                    <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0f172a;">
                                        ₱<?= number_format((float) ($p['claimed_amount'] ?? 0), 2) ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-family: ui-monospace, monospace; color: #94a3b8; white-space: nowrap;">
                                        <?= date('M j', strtotime($p['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div style="padding: 2.5rem 1.5rem; text-align: center; color: #94a3b8;">
                                            <div style="font-size: 1.75rem; margin-bottom: 0.4rem;">💳</div>
                                            <p class="font-semibold text-neutral-700 text-body-sm">No payment records yet.</p>
                                            <p class="text-caption text-neutral-400" style="margin-top: 0.25rem;">Submit your first proof of payment when rent is due.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Notifications Section -->
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <div class="section-header" style="padding-bottom: 0.625rem;">
            <div class="flex items-center gap-2">
                <h2 class="text-heading-sm font-semibold text-neutral-900">Notifications &amp; Activity Log</h2>
                <span class="badge <?= $notificationsCount > 0 ? 'badge-primary' : 'badge-neutral' ?>"><?= $notificationsCount ?> unread</span>
            </div>
            <a href="/notifications" class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                Notification Center &rarr;
            </a>
        </div>

        <div class="card overflow-hidden" style="border-radius: 0.875rem;">
            <ul class="divide-y divide-neutral-100 text-body-sm">
                <?php foreach ($notifications as $n): ?>
                    <li style="padding: 1rem 1.25rem; transition: background 0.12s;" class="hover:bg-neutral-50 flex items-start gap-3.5">
                        <span style="width: 0.5rem; height: 0.5rem; border-radius: 50%; background: #3b82f6; margin-top: 0.45rem; flex-shrink: 0;"></span>
                        <div style="flex: 1; min-width: 0;">
                            <div class="text-neutral-800" style="line-height: 1.5;"><?= htmlspecialchars($n['message']) ?></div>
                            <span class="text-caption text-neutral-400 block font-mono" style="margin-top: 0.35rem; font-size: 0.6875rem;">
                                <?= htmlspecialchars($n['created_at']) ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($notifications)): ?>
                    <li>
                        <div style="padding: 2.5rem 1.5rem; text-align: center; color: #94a3b8;">
                            <div style="font-size: 1.75rem; margin-bottom: 0.4rem;">🔔</div>
                            <p class="font-semibold text-neutral-700 text-body-sm">All caught up!</p>
                            <p class="text-caption text-neutral-400" style="margin-top: 0.25rem;">No unread notices or broadcasts at this time.</p>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

</div>

<script>
/* Emergency SOS AJAX Dispatch */
const csrfToken = <?= json_encode(\App\Support\Csrf::token()) ?>;
document.getElementById('sos-button').addEventListener('click', async () => {
    const statusEl = document.getElementById('sos-status');
    statusEl.textContent = 'Sending emergency broadcast...';
    statusEl.className = 'text-body-sm text-center font-medium mt-3 text-neutral-600';
    try {
        const res = await fetch('/api/sos', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(csrfToken),
        });
        const data = await res.json();
        statusEl.textContent = data.ok ? 'Alert sent — staff have been notified.' : (data.message || 'Failed to send alert.');
        statusEl.className = data.ok ? 'text-body-sm text-center font-semibold mt-3 text-emerald-700 bg-emerald-50 py-1.5 px-3 rounded-md border border-emerald-200' : 'text-body-sm text-center font-semibold mt-3 text-red-700 bg-red-50 py-1.5 px-3 rounded-md border border-red-200';
        if (data.ok && window.gsap) { gsap.fromTo('#sos-status', {opacity: 0, y: 4}, {opacity: 1, y: 0, duration: 0.3}); }
    } catch (e) {
        statusEl.textContent = 'Network error sending alert. Please contact management directly.';
        statusEl.className = 'text-body-sm text-center font-semibold mt-3 text-red-700 bg-red-50 py-1.5 px-3 rounded-md border border-red-200';
    }
});

/* GSAP Card Reveal Animation — uses fromTo() so end-state is always guaranteed */
if (window.gsap) {
    gsap.fromTo(".reveal-card",
        { opacity: 0, y: 12 },
        { opacity: 1, y: 0, duration: 0.35, stagger: 0.05, ease: "power2.out" }
    );
} else {
    /* Fallback: ensure all cards are visible even without GSAP */
    document.querySelectorAll('.reveal-card').forEach(el => { el.style.opacity = '1'; });
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
