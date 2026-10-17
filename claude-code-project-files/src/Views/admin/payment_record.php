<?php
/** Admin and staff: record a payment taken in person. Variables from PaymentController::recordForm(): $residents, $selected, $balance, $error. */
$pageTitle = 'Record a Payment';
ob_start();
?>
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div class="page-banner">
        <div>
            <h1>Record a payment</h1>
            <p>Cash, a walk-in GCash or a bank slip taken in person. It counts like an approved online payment.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-lg p-3.5 border border-error-500" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="card p-5" aria-labelledby="record-heading">
        <h2 id="record-heading" class="font-semibold mb-4">Payment details</h2>
        <form method="post" action="/admin/payments/record" class="grid gap-4 sm:grid-cols-2" data-confirm-record>
            <?= \App\Support\Csrf::field() ?>
            <div class="sm:col-span-2">
                <label for="rec-boarder" class="block text-xs font-semibold text-neutral-700 mb-1">Resident</label>
                <select id="rec-boarder" name="boarder_id" required class="input w-full" data-pick-resident>
                    <option value="">Choose a resident…</option>
                    <?php foreach ($residents as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === $selected ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name']) ?><?= $r['room_number'] ? ' · Room ' . htmlspecialchars($r['room_number']) : '' ?><?= $r['status'] === 'moved_out' ? ' · moved out' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($balance !== null): ?>
                    <p class="text-xs text-neutral-500 mt-1" data-testid="record-balance">Currently owes <strong>₱<?= number_format((float) $balance['total_outstanding'], 2) ?></strong>
                        (oldest bill first). Anything extra is kept as credit.</p>
                <?php endif; ?>
            </div>
            <div>
                <label for="rec-amount" class="block text-xs font-semibold text-neutral-700 mb-1">Amount received (₱)</label>
                <input id="rec-amount" name="amount" inputmode="decimal" required pattern="\d{1,7}(\.\d{1,2})?" maxlength="10" class="input w-full" autocomplete="off">
            </div>
            <div>
                <label for="rec-method" class="block text-xs font-semibold text-neutral-700 mb-1">Paid by</label>
                <select id="rec-method" name="payment_method" required class="input w-full">
                    <?php foreach (\App\Models\Payment::MANUAL_METHODS as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="rec-date" class="block text-xs font-semibold text-neutral-700 mb-1">Date received</label>
                <input id="rec-date" name="received_on" type="date" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" class="input w-full">
            </div>
            <div>
                <label for="rec-ref" class="block text-xs font-semibold text-neutral-700 mb-1">Reference or receipt-book number (optional)</label>
                <input id="rec-ref" name="reference_number" maxlength="50" pattern="[A-Za-z0-9 \-]*" class="input w-full" autocomplete="off">
            </div>
            <div class="sm:col-span-2 flex items-center gap-3">
                <button type="submit" class="btn btn-primary">Record payment</button>
                <a href="/admin/payments" class="text-sm text-neutral-600 underline">Cancel</a>
            </div>
        </form>
    </section>
</div>
<script>
// Picking a resident reloads the form with their balance shown (a GET, so nothing is written).
document.querySelector('[data-pick-resident]').addEventListener('change', e => {
    location.href = '/admin/payments/record' + (e.target.value ? '?boarder=' + encodeURIComponent(e.target.value) : '');
});
document.querySelector('form[data-confirm-record]').addEventListener('submit', e => {
    const f = e.target, who = f.boarder_id.selectedOptions[0]?.text.trim() ?? '';
    if (!confirm('Record ₱' + f.amount.value + ' (' + f.payment_method.selectedOptions[0].text + ') from ' + who + '? The resident is told and the balance changes now.')) e.preventDefault();
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
