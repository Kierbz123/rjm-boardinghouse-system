<?php
/** @var array $inquiry @var string $visitorMessage @var string $staffNotes @var ?string $flashOk */
$pageTitle = 'Inquiry #' . (int) $inquiry['id'];
$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
$statusStyle = [
    'new'       => ['#fbf4ef', '#b15f2c', 'New'],
    'contacted' => ['#fbf6e8', '#9a6700', 'Contacted'],
    'closed'    => ['#eef6f1', '#2f6f4e', 'Closed'],
][$inquiry['status']] ?? ['#f1f0ee', '#555452', ucfirst((string) $inquiry['status'])];
$source = $inquiry['source'] === 'staff'
    ? 'Logged by staff' . ($inquiry['submitted_by_name'] ? ' (' . $inquiry['submitted_by_name'] . ')' : '')
    : 'Website form';
$moveIn = $inquiry['move_in_date'] ? date('F j, Y', strtotime($inquiry['move_in_date'])) : 'Not given';
$received = date('F j, Y \a\t g:i a', strtotime($inquiry['created_at']));
$phoneDigits = preg_replace('/[^0-9+]/', '', (string) $inquiry['phone']);
ob_start();
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-6">
    <a href="/admin/inquiry-center" class="text-sm font-semibold text-neutral-600 hover:text-neutral-900">← Back to Inquiry Center</a>

    <div class="page-banner">
        <div>
            <h1>Inquiry #<?= (int) $inquiry['id'] ?></h1>
            <p>From <?= $e($inquiry['name']) ?> · received <?= $e($received) ?></p>
        </div>
        <span class="badge" style="background: <?= $statusStyle[0] ?>; color: <?= $statusStyle[1] ?>; width: fit-content; padding: 0.4rem 0.85rem; font-size: 0.75rem;"><?= $e($statusStyle[2]) ?></span>
    </div>

    <?php if ($flashOk): ?>
        <div role="status" style="padding: 0.75rem 1rem; border-radius: 0.75rem; font-size: 0.875rem; background: #eef6f1; border: 1px solid #d7ebdf; color: #245a3f;"><?= $e($flashOk) ?></div>
    <?php endif; ?>

    <div class="card p-5 space-y-5">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div>
                <dt class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Name</dt>
                <dd class="mt-1 font-medium text-neutral-900"><?= $e($inquiry['name']) ?></dd>
            </div>
            <div>
                <dt class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Phone</dt>
                <dd class="mt-1 font-medium"><a href="tel:<?= $e($phoneDigits) ?>" class="text-neutral-900 underline"><?= $e($inquiry['phone']) ?></a></dd>
            </div>
            <div>
                <dt class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Email</dt>
                <dd class="mt-1 font-medium">
                    <?php if ($inquiry['email']): ?>
                        <a href="mailto:<?= $e($inquiry['email']) ?>" class="text-neutral-900 underline" style="overflow-wrap:anywhere;"><?= $e($inquiry['email']) ?></a>
                    <?php else: ?><span class="text-neutral-400">Not given</span><?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Requested room</dt>
                <dd class="mt-1 font-medium text-neutral-900"><?= $e($inquiry['room_type'] ?: 'Not given') ?></dd>
            </div>
            <div>
                <dt class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Target move-in / viewing</dt>
                <dd class="mt-1 font-medium text-neutral-900"><?= $e($moveIn) ?></dd>
            </div>
            <div>
                <dt class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Source</dt>
                <dd class="mt-1 font-medium text-neutral-900"><?= $e($source) ?></dd>
            </div>
        </dl>

        <div>
            <h2 class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Message</h2>
            <p class="mt-1 text-sm text-neutral-900" style="white-space: pre-wrap; overflow-wrap: anywhere;"><?= trim($visitorMessage) !== '' ? $e($visitorMessage) : '<span class="text-neutral-400">No message left.</span>' ?></p>
        </div>

        <?php if (trim($staffNotes) !== ''): ?>
            <div style="background:#f8f7f5; border-radius:0.5rem; padding:0.75rem 1rem;">
                <h2 class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Staff notes (internal)</h2>
                <p class="mt-1 text-sm text-neutral-900" style="white-space: pre-wrap; overflow-wrap: anywhere;"><?= $e($staffNotes) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" action="/admin/inquiries/<?= (int) $inquiry['id'] ?>/status" class="card p-5 flex flex-wrap items-end gap-3">
        <?= \App\Support\Csrf::field() ?>
        <div>
            <label for="inq-status" class="text-caption font-semibold text-neutral-500 uppercase tracking-wider block mb-1">Follow-up status</label>
            <select id="inq-status" name="status" class="inq-input" style="padding: 0.5rem 0.75rem; border: 1px solid #d4d2ce; border-radius: 0.5rem; font-size: 0.875rem; background: white;">
                <?php foreach (\App\Models\Inquiry::STATUSES as $s): ?>
                    <option value="<?= $s ?>" <?= $inquiry['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1.1rem; background: #b15f2c; color: white; border: 0; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; cursor: pointer;">Save status</button>
    </form>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
