<?php
/**
 * A printable receipt, standalone (no sidebar) so it prints cleanly.
 * Variables from PaymentController::receipt(): $payment, $resident, $receivedBy, $allocations.
 */
$amount = (float) ($payment['approved_amount'] ?? $payment['claimed_amount']);
$number = $payment['receipt_number'] ?: 'Payment #' . (int) $payment['id'];
$day = $payment['received_on'] ?: substr((string) $payment['created_at'], 0, 10);
$home = match ($_SESSION['role'] ?? null) {
    'admin' => '/admin/payments',
    default => '/portal/payments/new',
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Receipt <?= htmlspecialchars($number) ?> — RJM Boardinghouse</title>
    <style>
        body { font: 15px/1.5 system-ui, sans-serif; color: #1c1917; background: #f5f5f4; margin: 0; padding: 1.5rem; }
        .sheet { max-width: 34rem; margin: 0 auto; background: #fff; border: 1px solid #d6d3d1; border-radius: .5rem; padding: 2rem; }
        h1 { font-size: 1.25rem; margin: 0; } .muted { color: #78716c; font-size: .85rem; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1c1917; padding-bottom: .75rem; margin-bottom: 1rem; }
        dl { display: grid; grid-template-columns: 9rem 1fr; gap: .35rem 1rem; margin: 0 0 1rem; } dt { color: #78716c; } dd { margin: 0; }
        .total { font-size: 1.6rem; font-weight: 700; border-top: 1px solid #d6d3d1; border-bottom: 1px solid #d6d3d1; padding: .6rem 0; margin: 1rem 0; display: flex; justify-content: space-between; }
        ul { margin: .25rem 0 1rem 1.1rem; padding: 0; }
        .sign { margin-top: 2.5rem; display: flex; justify-content: space-between; gap: 2rem; }
        .sign div { flex: 1; border-top: 1px solid #1c1917; padding-top: .25rem; text-align: center; font-size: .8rem; color: #78716c; }
        .actions { max-width: 34rem; margin: 1rem auto 0; display: flex; gap: .75rem; }
        .actions a, .actions button { font: inherit; padding: .5rem 1rem; border-radius: .375rem; border: 1px solid #1c1917; background: #1c1917; color: #fff; text-decoration: none; cursor: pointer; }
        .actions a { background: #fff; color: #1c1917; }
        @media print { body { background: #fff; padding: 0; } .sheet { border: 0; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="head">
            <div><h1>RJM Boardinghouse</h1><div class="muted">Official receipt</div></div>
            <div style="text-align:right"><strong><?= htmlspecialchars($number) ?></strong><div class="muted"><?= htmlspecialchars(date('M j, Y', strtotime($day))) ?></div></div>
        </div>
        <dl>
            <dt>Received from</dt><dd><?= htmlspecialchars($resident['name']) ?></dd>
            <?php if (!empty($resident['room_number'])): ?>
                <dt>Room</dt><dd><?= htmlspecialchars($resident['room_number']) ?><?= !empty($resident['bed_label']) ? ' · ' . htmlspecialchars($resident['bed_label']) : '' ?></dd>
            <?php endif; ?>
            <dt>Paid by</dt><dd><?= htmlspecialchars(\App\Models\Payment::methodLabel($payment['payment_method'] ?? null)) ?></dd>
            <?php if (!empty($payment['reference_number'])): ?>
                <dt>Reference</dt><dd><?= htmlspecialchars($payment['reference_number']) ?></dd>
            <?php endif; ?>
            <?php if ($receivedBy): ?>
                <dt>Received by</dt><dd><?= htmlspecialchars($receivedBy) ?></dd>
            <?php endif; ?>
        </dl>
        <div class="total"><span>Amount received</span><span>₱<?= number_format($amount, 2) ?></span></div>
        <?php if ($allocations): ?>
            <div class="muted">Applied to</div>
            <ul>
                <?php foreach ($allocations as $al): ?>
                    <li><?= htmlspecialchars(match ($al['allocation_type']) {
                        'rent' => \App\Services\BillingService::periodLabel($al['reference_id']) . ' rent',
                        'electricity' => 'Electricity (' . ($al['electricity_label'] ?? 'room bill') . ')',
                        default => 'Late fee',
                    }) ?> — ₱<?= number_format((float) $al['amount'], 2) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="sign"><div>Resident's signature</div><div>Received by</div></div>
    </div>
    <div class="actions">
        <button type="button" id="print-receipt">Print receipt</button>
        <a href="<?= htmlspecialchars($home) ?>">Back</a>
    </div>
    <script>document.getElementById('print-receipt').addEventListener('click', () => window.print());</script>
</body>
</html>
