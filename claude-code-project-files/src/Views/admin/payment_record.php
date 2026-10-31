<?php
/** Admin and staff: record a payment taken in person. Variables from PaymentController::recordForm(): $residents, $pending, $selected, $balance, $selectedResident, $selectedHistory, $recentManualPayments, $kpis, $error, $success. */
$pageTitle = 'Record a Payment';
$pending = $pending ?? [];
$selectedResident = $selectedResident ?? null;
$selectedHistory = $selectedHistory ?? [];
$recentManualPayments = $recentManualPayments ?? [];
$kpis = $kpis ?? ['count_today' => 0, 'total_today' => 0.0, 'count_month' => 0, 'total_month' => 0.0];
$todayDate = date('Y-m-d');
$yesterdayDate = date('Y-m-d', strtotime('-1 day'));

// "Kierby Sese (Room 6)" links, shared by the empty state and the hint under the picker.
$pendingLinks = implode(', ', array_map(
    fn ($p) => '<a href="/admin/boarders/' . (int) $p['id'] . '" class="font-semibold text-neutral-800 underline hover:text-neutral-900">' . htmlspecialchars($p['name']) . '</a>'
        . ($p['room_number'] ? ' <span class="text-neutral-500">(Room ' . htmlspecialchars($p['room_number']) . ')</span>' : ''),
    $pending
));

// Prepare itemized obligations for display and client-side simulation
$owedLines = [];
if ($balance) {
    if (!empty($balance['unpaid_rent'])) {
        foreach ($balance['unpaid_rent'] as $period => $amt) {
            $due = $balance['rent_due_dates'][$period] ?? null;
            $owedLines[] = [
                'type' => 'rent',
                'label' => 'Rent: ' . \App\Services\BillingService::periodLabel((string) $period),
                'amount' => (float) $amt,
                'due_date' => $due,
            ];
        }
    }
    if (!empty($balance['unpaid_electricity'])) {
        foreach ($balance['unpaid_electricity'] as $e) {
            $owedLines[] = [
                'type' => 'electricity',
                'label' => 'Electricity: ' . ($e['label'] ?? 'Share'),
                'amount' => (float) ($e['amount'] ?? 0),
                'due_date' => $e['due_date'] ?? null,
            ];
        }
    }
    if (!empty($balance['unpaid_penalties'])) {
        foreach ($balance['unpaid_penalties'] as $p) {
            $owedLines[] = [
                'type' => 'penalty',
                'label' => 'Fee: ' . ($p['rule_name'] ?? 'Penalty'),
                'amount' => (float) ($p['remaining_amount'] ?? $p['amount'] ?? 0),
                'due_date' => $p['due_date'] ?? null,
            ];
        }
    }
}

ob_start();
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- Executive Page Banner with Quick Launch Command Bar -->
    <header class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span>Record a payment</span>
                </h1>
                <p class="dash-hero-sub">Cash, walk-in GCash, Maya or bank slip taken in person. It counts like an approved online payment.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#8cc2a2; border:1px solid rgba(255,255,255,0.15); padding:0.35rem 0.85rem; font-size:0.75rem; font-weight:600; border-radius:9999px; display:inline-flex; align-items:center;">
                    <span class="live-pulse-dot" style="margin-right:0.45rem;"></span>
                    Cashier Desk Active
                </span>
                <span class="badge" style="background:rgba(255,255,255,0.05); color:#a3a19b; border:1px solid rgba(255,255,255,0.1); padding:0.35rem 0.75rem; font-size:0.75rem; border-radius:9999px; display:inline-flex; align-items:center; gap:0.35rem;">
                    <?= \App\Support\Icon::svg('calendar', 13) ?>
                    <span><?= date('l, M j, Y') ?></span>
                </span>
            </div>
        </div>

        <!-- Quick Launch Command Bar -->
        <div class="dash-cmd-bar">
            <span style="font-size:0.6875rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:rgba(255,255,255,0.5); margin-right:0.35rem;">
                Quick Launch:
            </span>
            <a href="#record-heading" class="dash-cmd-btn highlight" title="Jump to payment intake form">
                <?= \App\Support\Icon::svg('plus', 14) ?>
                <span>Payment Form</span>
            </a>
            <a href="/admin/payments" class="dash-cmd-btn" title="Back to all payments">
                <?= \App\Support\Icon::svg('arrow-left', 14) ?>
                <span>Back to Payments</span>
            </a>
            <a href="#manual-payments-recent" class="dash-cmd-btn" title="Jump to recent receipts table">
                <?= \App\Support\Icon::svg('clipboard', 14) ?>
                <span>Recent Receipts</span>
            </a>
            <a href="/admin/ledger/export" class="dash-cmd-btn" title="Export ledger as CSV">
                <?= \App\Support\Icon::svg('download', 14) ?>
                <span>Ledger Export</span>
            </a>
        </div>
    </header>

    <!-- KPI Cards: adapted to the system's standard .kpi-grid layout and design -->
    <div class="kpi-grid">
        <div class="reveal-card kpi-card metric-accent-success" title="Cash and walk-in collections received today">
            <div class="kpi-card-header">
                <span class="kpi-label">Collected Today</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('banknote') ?></span>
            </div>
            <div class="kpi-value">₱<?= number_format((float) ($kpis['total_today'] ?? 0), 2) ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400"><?= (int) ($kpis['count_today'] ?? 0) ?> payment<?= (int) ($kpis['count_today'] ?? 0) === 1 ? '' : 's' ?> recorded today</span>
            </div>
        </div>

        <div class="reveal-card kpi-card metric-accent-primary" title="In-person collections this month">
            <div class="kpi-card-header">
                <span class="kpi-label">Month (In-Person)</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('file-text') ?></span>
            </div>
            <div class="kpi-value">₱<?= number_format((float) ($kpis['total_month'] ?? 0), 2) ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400"><?= (int) ($kpis['count_month'] ?? 0) ?> transaction<?= (int) ($kpis['count_month'] ?? 0) === 1 ? '' : 's' ?> logged in <?= date('F') ?></span>
            </div>
        </div>

        <div class="reveal-card kpi-card metric-accent-info" title="Active residents eligible to pay">
            <div class="kpi-card-header">
                <span class="kpi-label">Eligible Residents</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('users') ?></span>
            </div>
            <div class="kpi-value"><?= count($residents) ?></div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Active boarders moved in</span>
            </div>
        </div>

        <a href="/admin/payments" class="reveal-card kpi-card metric-accent-warning" title="View all payment records and reviews">
            <div class="kpi-card-header">
                <span class="kpi-label">Payments Desk</span>
                <span class="kpi-icon"><?= \App\Support\Icon::svg('credit-card') ?></span>
            </div>
            <div class="kpi-value flex items-center justify-between text-neutral-900">
                <span>Ledger</span>
                <span class="text-sm font-semibold text-primary-700 font-sans">&rarr;</span>
            </div>
            <div class="kpi-subtext">
                <span class="text-neutral-400">Back to reviews queue</span>
            </div>
        </a>
    </div>

    <!-- Flash Alerts -->
    <?php if ($error): ?>
        <div class="bg-error-50 text-error-700 text-body-sm rounded-xl p-4 border border-error-500 flex items-center gap-3 shadow-sm" role="alert">
            <span class="flex-shrink-0 text-error-600"><?= \App\Support\Icon::svg('alert-triangle', 20) ?></span>
            <span class="font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="bg-emerald-50 text-emerald-800 text-body-sm rounded-xl p-4 border border-emerald-300 flex items-center gap-3 shadow-sm" role="alert">
            <span class="flex-shrink-0 text-emerald-600"><?= \App\Support\Icon::svg('check-circle', 20) ?></span>
            <span class="font-medium"><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$residents): ?>
        <!-- Empty State (No residents moved in) -->
        <section class="card p-8 text-center" aria-labelledby="record-heading" data-testid="record-empty">
            <div class="empty-state max-w-lg mx-auto">
                <span class="empty-icon mx-auto mb-4 w-16 h-16 rounded-2xl bg-neutral-100 text-neutral-500 flex items-center justify-center">
                    <?= \App\Support\Icon::svg('banknote', 32) ?>
                </span>
                <h2 id="record-heading" class="text-xl font-bold text-neutral-900 mb-2">No resident to record a payment for yet</h2>
                <?php if ($pending): ?>
                    <p class="text-sm text-neutral-600 mb-6 leading-relaxed" data-testid="record-pending">
                        Rent is charged from the move-in date, so a resident can only pay once they have moved in.
                        Waiting to move in: <?= $pendingLinks ?>.
                        Open their profile and set the status to <strong>Active</strong>.
                    </p>
                <?php else: ?>
                    <p class="text-sm text-neutral-600 mb-6 leading-relaxed">Add a boarder and assign them a bed first. Payments can be recorded once they have moved in.</p>
                <?php endif; ?>
                <div class="flex flex-wrap justify-center gap-3">
                    <a href="/admin/boarders" class="btn btn-primary px-5 py-2.5">Go to boarders</a>
                    <a href="/admin/payments" class="btn btn-secondary px-5 py-2.5">Back to payments</a>
                </div>
            </div>
        </section>
    <?php else: ?>

    <!-- Two-Column Form & Context Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Left Column: The Recording Form (7 cols) -->
        <section class="lg:col-span-7 card p-6 border border-neutral-200/80 shadow-sm" aria-labelledby="record-heading">
            <div class="flex items-center justify-between pb-4 mb-5 border-b border-neutral-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-lg bg-neutral-900 text-white flex items-center justify-center">
                        <?= \App\Support\Icon::svg('banknote', 18) ?>
                    </span>
                    <div>
                        <h2 id="record-heading" class="font-bold text-neutral-900 text-base">Payment details</h2>
                        <p class="text-xs text-neutral-500">Official RJM receipt issued immediately upon recording</p>
                    </div>
                </div>
                <span class="badge badge-neutral text-xs">Direct Ledger Credit</span>
            </div>

            <form method="post" action="/admin/payments/record" class="space-y-5" data-confirm-record>
                <?= \App\Support\Csrf::field() ?>

                <!-- Field 1: Resident Selector -->
                <div>
                    <label for="rec-boarder" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-1.5">
                        Resident <span class="text-error-600">*</span>
                    </label>
                    <div class="relative">
                        <select id="rec-boarder" name="boarder_id" required class="input w-full font-medium" data-pick-resident>
                            <option value="">Choose a resident…</option>
                            <?php foreach ($residents as $r): ?>
                                <option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === $selected ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['name']) ?><?= $r['room_number'] ? ' · Room ' . htmlspecialchars($r['room_number']) : '' ?><?= !empty($r['bed_label']) ? ' (' . (stripos($r['bed_label'], 'bed') !== false ? htmlspecialchars($r['bed_label']) : 'Bed ' . htmlspecialchars($r['bed_label'])) . ')' : '' ?><?= $r['status'] === 'moved_out' ? ' · moved out' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($pending): ?>
                        <p class="text-xs text-neutral-500 mt-2 flex items-center gap-1.5" data-testid="record-pending">
                            <?= \App\Support\Icon::svg('alert-circle', 14, 'text-amber-600 flex-shrink-0') ?>
                            <span>Not listed, still waiting to move in: <?= $pendingLinks ?>.</span>
                        </p>
                    <?php endif; ?>

                    <?php if ($balance !== null): ?>
                        <!-- Test-verified Balance Container -->
                        <div class="mt-3.5 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-700" data-testid="record-balance">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <div class="text-sm">
                                    Currently owes <strong class="text-neutral-900 font-mono text-base">₱<?= number_format((float) $balance['total_outstanding'], 2) ?></strong>
                                    <span class="text-xs text-neutral-500">(oldest bill first). Anything extra is kept as credit.</span>
                                </div>
                                <?php if ((float) $balance['total_outstanding'] > 0): ?>
                                    <span class="badge badge-warning text-xs font-semibold">Payment Due</span>
                                <?php else: ?>
                                    <span class="badge badge-success text-xs font-semibold">Fully Settled</span>
                                <?php endif; ?>
                            </div>

                            <!-- Mini breakdown tags -->
                            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-neutral-200/60 text-xs">
                                <span class="text-neutral-600 font-medium">Rent: <strong class="text-neutral-900 font-mono">₱<?= number_format((float) ($balance['rent_due'] ?? 0), 2) ?></strong></span>
                                <span class="text-neutral-300">|</span>
                                <span class="text-neutral-600 font-medium">Electricity: <strong class="text-neutral-900 font-mono">₱<?= number_format((float) ($balance['electricity_due'] ?? 0), 2) ?></strong></span>
                                <span class="text-neutral-300">|</span>
                                <span class="text-neutral-600 font-medium">Penalties: <strong class="text-neutral-900 font-mono">₱<?= number_format((float) ($balance['penalties_due'] ?? 0), 2) ?></strong></span>
                                <?php if ((float) ($balance['credit'] ?? 0) > 0): ?>
                                    <span class="text-neutral-300">|</span>
                                    <span class="text-emerald-700 font-semibold font-mono">Credit Avail: ₱<?= number_format((float) $balance['credit'], 2) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Field 2: Amount Received & Quick Presets -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="rec-amount" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">
                            Amount received <span class="text-error-600">*</span>
                        </label>
                        <?php if ($balance !== null && (float) $balance['total_outstanding'] > 0): ?>
                            <span class="text-xs text-neutral-500">Pick preset or type custom</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($balance !== null): ?>
                        <!-- Quick presets chips -->
                        <div class="flex flex-wrap gap-1.5 mb-2.5">
                            <?php if ((float) $balance['total_outstanding'] > 0): ?>
                                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs font-medium cursor-pointer" onclick="setPresetAmount(<?= (float) $balance['total_outstanding'] ?>)">
                                    Full Balance (₱<?= number_format((float) $balance['total_outstanding'], 2) ?>)
                                </button>
                            <?php endif; ?>
                            <?php if ((float) ($balance['rent_due'] ?? 0) > 0 && abs((float) $balance['total_outstanding'] - (float) $balance['rent_due']) > 0.01): ?>
                                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs font-medium cursor-pointer" onclick="setPresetAmount(<?= (float) $balance['rent_due'] ?>)">
                                    Rent (₱<?= number_format((float) $balance['rent_due'], 2) ?>)
                                </button>
                            <?php endif; ?>
                            <?php if ((float) ($selectedResident['base_price'] ?? 0) > 0 && abs((float) ($selectedResident['base_price'] ?? 0) - (float) $balance['total_outstanding']) > 0.01): ?>
                                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs font-medium cursor-pointer" onclick="setPresetAmount(<?= (float) $selectedResident['base_price'] ?>)">
                                    1 Month Rent (₱<?= number_format((float) $selectedResident['base_price'], 2) ?>)
                                </button>
                            <?php endif; ?>
                            <?php if ((float) ($balance['electricity_due'] ?? 0) > 0): ?>
                                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs font-medium cursor-pointer" onclick="setPresetAmount(<?= (float) $balance['electricity_due'] ?>)">
                                    Electricity (₱<?= number_format((float) $balance['electricity_due'], 2) ?>)
                                </button>
                            <?php endif; ?>
                            <?php if ((float) ($balance['penalties_due'] ?? 0) > 0): ?>
                                <button type="button" class="btn btn-secondary !py-1 !px-2.5 !text-xs font-medium cursor-pointer" onclick="setPresetAmount(<?= (float) $balance['penalties_due'] ?>)">
                                    Penalties (₱<?= number_format((float) $balance['penalties_due'], 2) ?>)
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="relative">
                        <span class="absolute left-3.5 text-neutral-400 font-mono font-bold text-lg pointer-events-none" style="top:50%;transform:translateY(-50%)" aria-hidden="true">₱</span>
                        <input id="rec-amount" name="amount" inputmode="decimal" required pattern="\d{1,7}(\.\d{1,2})?" maxlength="10" placeholder="0.00" class="input w-full font-mono text-xl font-bold tracking-tight text-neutral-900" style="padding-left:2.25rem" autocomplete="off">
                    </div>
                    <p class="text-caption text-neutral-400 mt-1">Accepts decimal amounts up to ₱1,000,000.00.</p>
                </div>

                <!-- Field 3: Payment Method (Visual Cards + Select Sync) -->
                <div>
                    <label for="rec-method" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-1.5">
                        Paid by <span class="text-error-600">*</span>
                    </label>

                    <!-- Visual Method Buttons -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-2" id="method-pills">
                        <?php 
                        $methodIcons = [
                            'cash'          => 'banknote',
                            'gcash'         => 'credit-card',
                            'maya'          => 'wallet',
                            'bank_transfer' => 'landmark',
                            'other'         => 'receipt',
                        ];
                        foreach (\App\Models\Payment::MANUAL_METHODS as $key => $label): 
                            $iconName = $methodIcons[$key] ?? 'receipt';
                        ?>
                            <button type="button" 
                                    class="method-pill flex flex-col items-center justify-center p-2.5 rounded-xl border border-neutral-200 bg-white hover:bg-neutral-50 cursor-pointer transition-all text-center <?= $key === 'cash' ? 'ring-2 ring-primary-600 border-primary-600 bg-primary-50/40 text-primary-900' : 'text-neutral-700' ?>"
                                    data-method="<?= htmlspecialchars($key) ?>"
                                    onclick="selectMethod('<?= htmlspecialchars($key) ?>')">
                                <span class="w-6 h-6 flex items-center justify-center mb-1 text-inherit">
                                    <?= \App\Support\Icon::svg($iconName, 18) ?>
                                </span>
                                <span class="text-xs font-semibold leading-tight"><?= htmlspecialchars($label) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Native Select for accessibility & test compat -->
                    <select id="rec-method" name="payment_method" required class="input w-full" onchange="syncMethodPills(this.value)">
                        <?php foreach (\App\Models\Payment::MANUAL_METHODS as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $key === 'cash' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Field 4: Date & Reference in 2 Columns with exact matching baseline and height -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    <div>
                        <div class="flex items-center justify-between mb-1.5 h-5">
                            <label for="rec-date" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">
                                Date received <span class="text-error-600">*</span>
                            </label>
                            <div class="flex items-center gap-1.5 text-caption">
                                <button type="button" class="font-semibold text-primary-700 hover:underline cursor-pointer" onclick="document.getElementById('rec-date').value='<?= $todayDate ?>'">Today</button>
                                <span class="text-neutral-300">·</span>
                                <button type="button" class="font-semibold text-neutral-500 hover:underline cursor-pointer" onclick="document.getElementById('rec-date').value='<?= $yesterdayDate ?>'">Yesterday</button>
                            </div>
                        </div>
                        <input id="rec-date" name="received_on" type="date" required value="<?= $todayDate ?>" max="<?= $todayDate ?>" class="input w-full font-medium" style="height: 2.625rem;">
                        <p class="text-caption text-neutral-400 mt-1">Late fees stop counting on the received date.</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5 h-5">
                            <label for="rec-ref" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700">
                                Reference or receipt-book no. <span class="font-normal lowercase text-neutral-400">(optional)</span>
                            </label>
                        </div>
                        <input id="rec-ref" name="reference_number" maxlength="50" pattern="[A-Za-z0-9 \-]*" placeholder="e.g. SLIP-1029 or GCash Ref" class="input w-full font-mono text-sm" style="height: 2.625rem;" autocomplete="off">
                        <p class="text-caption text-neutral-400 mt-1">Letters, numbers, spaces, and dashes only.</p>
                    </div>
                </div>

                <!-- Form Action Buttons -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-neutral-200">
                    <div class="flex items-center gap-3">
                        <button type="submit" class="btn btn-primary text-sm font-semibold px-6 py-2.5 shadow-sm flex items-center gap-2 cursor-pointer">
                            <?= \App\Support\Icon::svg('check', 16) ?>
                            <span>Record payment</span>
                        </button>
                        <a href="/admin/payments" class="btn btn-secondary text-sm px-4 py-2.5">Cancel</a>
                    </div>
                    <span class="text-caption text-neutral-400 flex items-center gap-1">
                        <?= \App\Support\Icon::svg('receipt', 14) ?>
                        Printable receipt opens immediately
                    </span>
                </div>
            </form>
        </section>

        <!-- Right Column: Context, Bills Breakdown & History (5 cols) -->
        <div class="lg:col-span-5 space-y-6">

            <?php if ($selected && $selectedResident): ?>
                <!-- Selected Resident Card -->
                <div class="card p-5 border border-neutral-200/80 shadow-sm space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-full bg-primary-100 text-primary-800 font-bold flex items-center justify-center text-sm font-mono flex-shrink-0">
                                <?= htmlspecialchars(strtoupper(substr($selectedResident['name'], 0, 2))) ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-neutral-900 leading-snug"><?= htmlspecialchars($selectedResident['name']) ?></h3>
                                <p class="text-xs text-neutral-500">
                                    Room <?= htmlspecialchars($selectedResident['room_number'] ?? 'Unassigned') ?>
                                    <?= !empty($selectedResident['bed_label']) ? ' · ' . (stripos($selectedResident['bed_label'], 'bed') !== false ? htmlspecialchars($selectedResident['bed_label']) : 'Bed ' . htmlspecialchars($selectedResident['bed_label'])) : '' ?>
                                </p>
                            </div>
                        </div>
                        <span class="badge <?= $selectedResident['status'] === 'active' ? 'badge-success' : 'badge-neutral' ?> text-caption">
                            <?= ucfirst(str_replace('_', ' ', $selectedResident['status'])) ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs py-2 border-y border-neutral-100">
                        <div>
                            <span class="text-neutral-400 block">Monthly Rent Rate:</span>
                            <span class="font-bold font-mono text-neutral-800">₱<?= number_format((float) ($selectedResident['base_price'] ?? 0), 2) ?></span>
                        </div>
                        <div>
                            <span class="text-neutral-400 block">Contact Phone:</span>
                            <span class="font-semibold text-neutral-800"><?= htmlspecialchars($selectedResident['phone'] ?? 'None recorded') ?></span>
                        </div>
                    </div>

                    <a href="/admin/boarders/<?= (int) $selected ?>" class="btn btn-secondary !py-1.5 !px-3 !text-xs w-full flex items-center justify-center gap-1.5 font-medium">
                        <?= \App\Support\Icon::svg('user', 14) ?>
                        <span>Open resident profile &amp; billing</span>
                    </a>
                </div>

                <!-- Itemized Breakdown of Owed Obligations -->
                <div class="card p-5 border border-neutral-200/80 shadow-sm">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-neutral-100">
                        <h4 class="font-bold text-neutral-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                            <?= \App\Support\Icon::svg('file-text', 14) ?>
                            <span>Itemized Bills Owed</span>
                        </h4>
                        <span class="badge badge-warning text-caption"><?= count($owedLines) ?> item<?= count($owedLines) === 1 ? '' : 's' ?></span>
                    </div>

                    <?php if ($owedLines): ?>
                        <ul class="space-y-2.5 text-xs">
                            <?php foreach ($owedLines as $line): 
                                $isOverdue = !empty($line['due_date']) && $line['due_date'] < $todayDate;
                            ?>
                                <li class="p-2.5 rounded-lg border border-neutral-200/80 bg-neutral-50/50 flex items-center justify-between gap-2">
                                    <div class="space-y-0.5">
                                        <div class="font-semibold text-neutral-900"><?= htmlspecialchars($line['label']) ?></div>
                                        <?php if (!empty($line['due_date'])): ?>
                                            <div class="text-caption <?= $isOverdue ? 'text-error-600 font-semibold' : 'text-neutral-400' ?>">
                                                <?= $isOverdue ? 'Overdue since ' : 'Due ' ?><?= date('M j, Y', strtotime($line['due_date'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-mono font-bold text-neutral-900">₱<?= number_format($line['amount'], 2) ?></div>
                                        <span class="badge <?= $isOverdue ? 'badge-error' : 'badge-neutral' ?> !text-[10px] !px-1.5 !py-0.5">
                                            <?= $isOverdue ? 'Overdue' : 'Due' ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-xs text-neutral-500 py-3 text-center">No outstanding unpaid charges for this resident.</p>
                    <?php endif; ?>

                    <?php if (!empty($balance['credit']) && (float) $balance['credit'] > 0): ?>
                        <div class="mt-3 p-2.5 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs flex items-center justify-between">
                            <span class="font-medium">Prepaid Credit Balance:</span>
                            <span class="font-mono font-bold">₱<?= number_format((float) $balance['credit'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Resident's Recent Payments -->
                <?php if ($selectedHistory): ?>
                    <div class="card p-5 border border-neutral-200/80 shadow-sm">
                        <h4 class="font-bold text-neutral-900 text-xs uppercase tracking-wider mb-3 pb-2 border-b border-neutral-100 flex items-center gap-1.5">
                            <?= \App\Support\Icon::svg('receipt', 14) ?>
                            <span>Recent Payments by <?= htmlspecialchars($selectedResident['name']) ?></span>
                        </h4>
                        <ul class="space-y-2 text-xs">
                            <?php foreach ($selectedHistory as $hp): ?>
                                <li class="flex items-center justify-between p-2 rounded-lg bg-neutral-50 border border-neutral-100">
                                    <div>
                                        <a href="/payments/<?= (int) $hp['id'] ?>/receipt" target="_blank" class="font-mono font-bold text-neutral-900 underline hover:text-primary-700">
                                            <?= htmlspecialchars($hp['receipt_number'] ?: ('#' . $hp['id'])) ?>
                                        </a>
                                        <div class="text-caption text-neutral-400">
                                            <?= htmlspecialchars(\App\Models\Payment::methodLabel($hp['payment_method'])) ?> · <?= date('M j, Y', strtotime($hp['received_on'] ?? $hp['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-mono font-bold text-neutral-900">₱<?= number_format((float) ($hp['approved_amount'] ?? $hp['claimed_amount']), 2) ?></div>
                                        <a href="/payments/<?= (int) $hp['id'] ?>/receipt" target="_blank" class="text-caption text-primary-700 hover:underline">Print</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Cashier Guidance Box (When no resident selected yet) -->
                <div class="card p-5 border border-neutral-200/80 shadow-sm space-y-4 bg-gradient-to-br from-white to-neutral-50">
                    <div class="flex items-center gap-2.5 pb-2 border-b border-neutral-200/60">
                        <span class="w-8 h-8 rounded-lg bg-primary-100 text-primary-800 flex items-center justify-center">
                            <?= \App\Support\Icon::svg('clipboard', 16) ?>
                        </span>
                        <div>
                            <h3 class="font-bold text-neutral-900 text-sm">Walk-in Payment SOP</h3>
                            <p class="text-caption text-neutral-500">Step-by-step cashier recording guide</p>
                        </div>
                    </div>
                    <ol class="space-y-3 text-xs text-neutral-600">
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-neutral-900 text-white font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">1</span>
                            <span><strong>Select Resident</strong> from the dropdown above to load their real-time ledger balance and unpaid cycles.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-neutral-900 text-white font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">2</span>
                            <span><strong>Count received cash</strong> or verify the physical deposit slip / GCash transaction reference number.</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-neutral-900 text-white font-bold flex items-center justify-center text-[10px] flex-shrink-0 mt-0.5">3</span>
                            <span><strong>Click Record Payment</strong> to automatically apply the amount oldest-first and print their official RJM receipt.</span>
                        </li>
                    </ol>

                    <div class="pt-3 border-t border-neutral-200/60 space-y-2 text-caption text-neutral-500">
                        <div class="flex items-center gap-1.5 text-neutral-700 font-semibold">
                            <?= \App\Support\Icon::svg('shield-check', 14, 'text-emerald-600') ?>
                            <span>Billing System Guarantees</span>
                        </div>
                        <p>• Payments are applied strictly oldest-first (Rent &rarr; Electricity &rarr; Penalties).</p>
                        <p>• Overpayments are automatically safeguarded and saved as resident ledger credit.</p>
                        <p>• Late fee calculations stop on the exact <em>Date Received</em> entered.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent In-Person Payments (Always Shown) -->
            <div class="card p-5 border border-neutral-200/80 shadow-sm" id="manual-payments-recent">
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-neutral-100">
                    <h4 class="font-bold text-neutral-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                        <?= \App\Support\Icon::svg('receipt', 14) ?>
                        <span>Recent In-Person Payments</span>
                    </h4>
                    <span class="badge badge-neutral text-caption">Last 5</span>
                </div>

                <?php if ($recentManualPayments): ?>
                    <ul class="space-y-2.5 text-xs">
                        <?php foreach ($recentManualPayments as $mp): ?>
                            <li class="p-2.5 rounded-lg bg-neutral-50 border border-neutral-200/60 flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-neutral-900"><?= htmlspecialchars($mp['resident_name']) ?></div>
                                    <div class="text-caption text-neutral-400">
                                        <?= htmlspecialchars(\App\Models\Payment::methodLabel($mp['payment_method'])) ?> · 
                                        <?= date('M j, Y', strtotime($mp['received_on'] ?? $mp['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="font-mono font-bold text-neutral-900">₱<?= number_format((float) ($mp['approved_amount'] ?? $mp['claimed_amount']), 2) ?></div>
                                    <a href="/payments/<?= (int) $mp['id'] ?>/receipt" target="_blank" class="font-mono text-caption text-primary-700 hover:underline block">
                                        <?= htmlspecialchars($mp['receipt_number'] ?: ('#' . $mp['id'])) ?>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-xs text-neutral-400 py-3 text-center">No walk-in payments recorded yet.</p>
                <?php endif; ?>
            </div>

        </div>

    </div>
    <?php endif; ?>

</div>

<?php if ($residents): ?>
<script>
// Picking a resident reloads the form with their balance shown (a GET, so nothing is written).
document.querySelector('[data-pick-resident]').addEventListener('change', e => {
    location.href = '/admin/payments/record' + (e.target.value ? '?boarder=' + encodeURIComponent(e.target.value) : '');
});

// Quick preset setter
function setPresetAmount(val) {
    const input = document.getElementById('rec-amount');
    if (!input) return;
    input.value = Number(val).toFixed(2);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.focus();
}

// Payment method visual sync
function selectMethod(methodKey) {
    const sel = document.getElementById('rec-method');
    if (sel) {
        sel.value = methodKey;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
    }
    syncMethodPills(methodKey);
}

function syncMethodPills(methodKey) {
    document.querySelectorAll('.method-pill').forEach(btn => {
        if (btn.getAttribute('data-method') === methodKey) {
            btn.className = 'method-pill flex flex-col items-center justify-center p-2.5 rounded-xl border border-primary-600 ring-2 ring-primary-600 bg-primary-50/50 text-primary-900 cursor-pointer transition-all text-center';
        } else {
            btn.className = 'method-pill flex flex-col items-center justify-center p-2.5 rounded-xl border border-neutral-200 bg-white hover:bg-neutral-50 text-neutral-700 cursor-pointer transition-all text-center';
        }
    });
}

// Initialize method pills to match default select value
const initialMethod = document.getElementById('rec-method')?.value || 'cash';
syncMethodPills(initialMethod);

// Form confirmation modal
document.querySelector('form[data-confirm-record]').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target, who = f.boarder_id.selectedOptions[0]?.text.trim() ?? '';
    const method = f.payment_method.selectedOptions[0]?.text ?? '';
    const confirmed = await window.showConfirmModal({
        title: 'Record Payment?',
        message: 'Record ₱' + f.amount.value + ' (' + method + ') from ' + who + '? The resident is told and the balance changes now.',
        confirmText: 'Record Payment',
        cancelText: 'Cancel',
        type: 'info'
    });
    if (confirmed) {
        f.submit();
    }
});
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
