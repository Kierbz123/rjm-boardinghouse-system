<?php
$pageTitle = 'Report a Repair';
ob_start();

$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$boarder = $boarder ?? null;
$recentRequests = $recentRequests ?? [];

// Fallback if rendered without controller variables
if ($boarder === null && !empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $pdo = \App\Database::getConnection();
    $stmt = $pdo->prepare('
        SELECT bp.*, r.room_number, b.label AS bed_label
        FROM boarder_profiles bp
        LEFT JOIN rooms r ON r.id = bp.room_id
        LEFT JOIN beds b ON b.id = bp.bed_id
        WHERE bp.user_id = ?
    ');
    $stmt->execute([$userId]);
    $boarder = $stmt->fetch() ?: null;

    if (empty($recentRequests)) {
        $stmt = $pdo->prepare('SELECT * FROM maintenance_requests WHERE boarder_id = ? ORDER BY created_at DESC LIMIT 5');
        $stmt->execute([$userId]);
        $recentRequests = $stmt->fetchAll();
    }
}

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
            <h1 class="text-heading-lg font-bold">Report a Repair</h1>
            <p class="text-body-sm" style="margin-top: 0.35rem;">
                Submit maintenance tickets directly to staff. Natural language AI scores urgency for prioritized dispatch.
            </p>
        </div>
        <span class="badge badge-warning" style="background:rgba(255,255,255,0.12); color:#fde047; border:1px solid rgba(255,255,255,0.15); padding: 0.35rem 0.75rem;">
            <span style="width:0.4rem;height:0.4rem;border-radius:50%;background:#eab308;display:inline-block;margin-right:0.4rem;"></span>
            AI Triaged
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

    <!-- Accommodation Header Bar -->
    <?php if (!empty($boarder['room_number'])): ?>
        <div class="card flex items-center justify-between text-xs" style="padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.875rem;">
            <div class="flex items-center gap-2.5 text-neutral-700 font-medium">
                <span>📍</span>
                <span>Ticket Origin:</span>
                <span class="font-bold text-neutral-900 font-mono">
                    Room <?= htmlspecialchars($boarder['room_number']) ?>
                    <?= !empty($boarder['bed_label']) ? ' &middot; ' . htmlspecialchars($boarder['bed_label']) : '' ?>
                </span>
            </div>
            <span class="text-neutral-500 text-caption">Location auto-assigned</span>
        </div>
    <?php endif; ?>

    <!-- Main Grid: Form + Sidebar -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        
        <!-- Left: Form Card (2/3 width on lg) -->
        <div class="lg:col-span-2">
            <form method="post" action="/portal/maintenance" enctype="multipart/form-data" class="form-card accent-neutral" style="padding: 1.5rem 1.625rem 1.75rem; border-radius: 1rem; gap: 1.125rem;">
                <div class="flex items-center gap-2.5" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 0.875rem;">
                    <span style="font-size: 1.25rem;">🔧</span>
                    <div>
                        <h2 class="text-heading-sm font-bold text-neutral-900">Repair Ticket Details</h2>
                        <p class="text-caption text-neutral-500" style="margin-top: 0.2rem;">Every submission is analyzed by the AI priority scoring model</p>
                    </div>
                </div>

                <?= \App\Support\Csrf::field() ?>
                <input type="hidden" name="room_id" value="<?= (int) ($boarder['room_id'] ?? 0) ?>">

                <!-- Category -->
                <div>
                    <label for="category" class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">
                        Issue Category <span class="text-error-600">*</span>
                    </label>
                    <select id="category" name="category" required class="input w-full" style="padding: 0.5rem 0.75rem; border-radius: 0.5rem;">
                        <option value="electrical">Electrical (outlets, light fixtures, breakers, sparking)</option>
                        <option value="plumbing">Plumbing (leaks, clogs, low pressure, drainage)</option>
                        <option value="structural">Structural (doors, window locks, flooring, ceiling)</option>
                        <option value="appliance">Appliance (fan, air conditioner, heater, dispenser)</option>
                        <option value="other" selected>Other / General Repairs</option>
                    </select>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">
                        Problem Description <span class="text-error-600">*</span>
                    </label>
                    <textarea id="description"
                              name="description"
                              placeholder="Describe the problem in detail (e.g. bathroom faucet leaking water, power outlet sparking, door knob stuck)..."
                              required
                              class="input w-full resize-y"
                              rows="4"
                              style="padding: 0.625rem 0.75rem; border-radius: 0.5rem;"></textarea>
                    <p class="text-caption text-neutral-400" style="margin-top: 0.375rem;">
                        Specific keywords (e.g. leak, sparking, broken, flooded) trigger automatic priority triage.
                    </p>
                </div>

                <!-- Media Attachment -->
                <div>
                    <label for="media" class="block text-caption font-semibold text-neutral-700" style="margin-bottom: 0.375rem;">
                        Photo or Video Evidence (Optional)
                    </label>
                    <div style="padding: 0.875rem 1rem; background: #f8fafc; border-radius: 0.625rem; border: 1px solid #e2e8f0;">
                        <input id="media"
                               type="file"
                               name="media"
                               accept="image/*,video/mp4"
                               class="w-full text-body-sm text-neutral-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-neutral-200 file:text-neutral-800 hover:file:bg-neutral-300 cursor-pointer">
                        <p class="text-caption text-neutral-400" style="margin-top: 0.5rem;">
                            Upload JPG, PNG, WEBP, or MP4 files up to 10MB. Visual proof assists staff diagnosis.
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="padding-top: 0.5rem; display: flex; flex-direction: column; gap: 0.625rem;">
                    <button type="submit" class="btn btn-primary w-full text-sm font-semibold justify-center" style="padding: 0.7rem 1rem; border-radius: 0.625rem;">
                        Submit Request
                    </button>
                    <a href="/portal/dashboard" class="btn btn-secondary w-full text-center text-xs justify-center" style="padding: 0.575rem 1rem; border-radius: 0.625rem;">
                        &larr; Cancel and return to dashboard
                    </a>
                </div>
            </form>
        </div>

        <!-- Right: Guidance Sidebar (1/3 width on lg) -->
        <div style="display: flex; flex-direction: column; gap: 1.125rem;">
            <div class="card" style="padding: 1.25rem 1.375rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.875rem;">
                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-600 flex items-center gap-2" style="margin-bottom: 0.875rem;">
                    <span>💡</span> Rapid Resolution Tips
                </h3>
                <ul class="text-neutral-600" style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.8125rem; line-height: 1.6;">
                    <li class="flex items-start gap-2.5">
                        <span class="text-primary-600 font-bold" style="margin-top: 0.1rem;">•</span>
                        <span><strong>State the exact item:</strong> Specify if it is the sink, shower, ceiling fan, or a power socket.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="text-primary-600 font-bold" style="margin-top: 0.1rem;">•</span>
                        <span><strong>Note severity:</strong> Mention if water is actively pooling or if an electrical hazard is present.</span>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <span class="text-primary-600 font-bold" style="margin-top: 0.1rem;">•</span>
                        <span><strong>Photos help:</strong> Staff can bring the right tools and replacement parts immediately.</span>
                    </li>
                </ul>
            </div>

            <div class="card" style="padding: 1.25rem 1.375rem; border: 1px solid #fecaca; background: #fef2f2; border-radius: 0.875rem;">
                <h3 class="text-xs font-bold flex items-center gap-2" style="color: #991b1b; margin-bottom: 0.5rem;">
                    <span>🚨</span> Life-Safety Hazard?
                </h3>
                <p style="font-size: 0.8125rem; color: #b91c1c; line-height: 1.6;">
                    If this is an immediate emergency (gas leak, active fire, live exposed electrical sparking, major water flooding), do not wait for a maintenance ticket.
                </p>
                <a href="/portal/dashboard" style="display: inline-block; font-size: 0.8125rem; font-weight: 700; color: #b91c1c; text-decoration: underline; margin-top: 0.5rem; transition: color 0.15s;">
                    Use Emergency SOS on Dashboard &rarr;
                </a>
            </div>
        </div>

    </div>

    <!-- Bottom Section: My Recent Repair Tickets -->
    <?php if (!empty($recentRequests)): ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; padding-top: 0.375rem;">
            <div class="section-header" style="padding-bottom: 0.625rem;">
                <h2 class="text-heading-sm font-semibold text-neutral-900">My Recent Repair Requests</h2>
                <span class="badge badge-neutral"><?= count($recentRequests) ?> recorded</span>
            </div>

            <div class="card overflow-hidden" style="border-radius: 0.875rem;">
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Priority</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Category</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Problem Description</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600;">Current Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 600; text-align: right;">Submitted Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <?php foreach ($recentRequests as $r): ?>
                                <tr class="hover:bg-neutral-50 transition-colors">
                                    <td style="padding: 0.75rem 1rem;">
                                        <span class="badge <?= $tierBadges[$r['priority_tier'] ?? ''] ?? 'badge-neutral' ?> uppercase" style="font-size: 0.625rem; font-weight: 700; padding: 0.15rem 0.5rem;">
                                            <?= htmlspecialchars($r['priority_tier'] ?? 'pending') ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; font-weight: 500; color: #334155; text-transform: capitalize; font-size: 0.75rem;">
                                        <?= htmlspecialchars($r['category'] ?? 'other') ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; color: #0f172a; max-width: 24rem;">
                                        <?= htmlspecialchars($r['description']) ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <span class="badge <?= ($r['status'] ?? '') === 'resolved' ? 'badge-success' : (($r['status'] ?? '') === 'in_progress' ? 'badge-info' : 'badge-warning') ?>">
                                            <?= htmlspecialchars($r['status'] ?? 'open') ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-family: ui-monospace, monospace; color: #94a3b8;">
                                        <?= date('M j, Y', strtotime($r['created_at'])) ?>
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
