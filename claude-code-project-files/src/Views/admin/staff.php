<?php
$pageTitle = 'Staff Accounts';
ob_start();
$staff = $staff ?? [];
$history = $history ?? [];
$hours = \App\Models\User::TEMP_PASSWORD_HOURS;
$activeCount = count(array_filter($staff, fn ($s) => ($s['status'] ?? 'active') === 'active'));
$actionLabels = [
    'staff_created' => 'Account created',
    'password_reset' => 'Temporary password set',
    'staff_deactivated' => 'Deactivated',
    'staff_reactivated' => 'Reactivated',
    'staff_phone_changed' => 'Phone number changed',
];
/** A date people read, with the full timestamp and time zone on hover / for assistive tech. */
$when = function (?string $ts, string $format = 'M j, Y'): string {
    if ($ts === null || $ts === '') {
        return '<span class="text-neutral-400">Never</span>';
    }
    $t = strtotime($ts);
    return '<time datetime="' . date('c', $t) . '" title="' . date('Y-m-d H:i T', $t) . '">' . htmlspecialchars(date($format, $t)) . '</time>';
};
$passwordHint = "At least 10 characters. At most 72 bytes: accented letters and emoji take more than one. It works for {$hours} hours and they must choose their own password when they first sign in.";
$rules = [
    ['wrench', 'Staff do repairs, incidents and SOS only; penalties, expenses and payments stay with you.'],
    ['log-out', 'Deactivating someone signs them out at once and stops them logging in; their records and history are kept.'],
    ['users', 'Work here is not assigned to one person, so nothing needs reassigning: anyone on staff, or you, can pick up what they left open.'],
    ['building', 'Whoever is on duty should keep the staff dashboard open on the front-desk computer.'],
    ['phone', 'The phone numbers below are shown to residents beside the SOS button as the backup, not the plan.'],
];
$label = 'block text-sm font-medium text-neutral-800 mb-1.5';
$hint = 'text-xs text-neutral-500 mt-1.5 leading-relaxed';
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <!-- Executive Page Banner with Quick Launch Command Bar -->
    <header class="dash-hero-banner">
        <div class="dash-hero-top">
            <div>
                <h1 class="dash-hero-title">
                    <span>Staff Accounts</span>
                </h1>
                <p class="dash-hero-sub">Add, reset and deactivate the people who handle maintenance, incidents and SOS alerts.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="badge" style="background:rgba(255,255,255,0.08); color:#8cc2a2; border:1px solid rgba(255,255,255,0.15); padding:0.35rem 0.85rem; font-size:0.75rem; font-weight:600; border-radius:9999px; display:inline-flex; align-items:center;">
                    <span class="live-pulse-dot" style="margin-right:0.45rem;"></span>
                    <?= $activeCount ?> Staff Active
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
            <a href="#add-staff" class="dash-cmd-btn highlight" title="Add a new staff member">
                <?= \App\Support\Icon::svg('plus', 14) ?>
                <span>Add Staff Member</span>
            </a>
            <a href="#staff-directory" class="dash-cmd-btn" title="Jump to staff directory list">
                <?= \App\Support\Icon::svg('users', 14) ?>
                <span>Staff Roster (<?= $activeCount ?> active)</span>
            </a>
            <a href="#staff-rules-heading" class="dash-cmd-btn" title="Staff operational policies and rules">
                <?= \App\Support\Icon::svg('clipboard', 14) ?>
                <span>Operations Rules</span>
            </a>
            <a href="/admin/staff" class="dash-cmd-btn" title="View all users in system">
                <?= \App\Support\Icon::svg('arrow-left', 14) ?>
                <span>All Users</span>
            </a>
        </div>
    </header>

    <?php $userTab = 'staff'; require __DIR__ . '/_user_tabs.php'; ?>

    <div class="grid gap-6 xl:grid-cols-3 items-start">
        <section id="add-staff" class="card p-5 sm:p-6 xl:col-span-2" aria-labelledby="add-staff-heading">
            <h2 id="add-staff-heading" class="text-base font-semibold text-neutral-900">Add a staff member</h2>
            <p class="text-sm text-neutral-500 mt-1">They sign in with a temporary password, then choose their own.</p>

            <form method="post" action="/admin/staff" class="mt-5">
                <?= \App\Support\Csrf::field() ?>
                <div class="grid gap-x-4 gap-y-5 sm:grid-cols-2">
                    <div>
                        <label for="staff-name" class="<?= $label ?>">Full name</label>
                        <input id="staff-name" name="name" required maxlength="150" class="input w-full" autocomplete="off">
                    </div>
                    <div>
                        <label for="staff-email" class="<?= $label ?>">Email</label>
                        <input id="staff-email" name="email" type="email" required maxlength="150" class="input w-full" autocomplete="off">
                    </div>
                    <div>
                        <label for="staff-phone" class="<?= $label ?>">Phone <span class="font-normal text-neutral-500">(optional)</span></label>
                        <input id="staff-phone" name="phone" type="tel" inputmode="numeric" data-digits="15" class="input w-full" autocomplete="off" placeholder="09171234567" aria-describedby="staff-phone-hint">
                        <p id="staff-phone-hint" class="<?= $hint ?>">For SOS backup: residents see it beside the SOS button.</p>
                    </div>
                    <div>
                        <label for="staff-password" class="<?= $label ?>">Temporary password</label>
                        <input id="staff-password" name="password" type="password" data-max-bytes="72" required minlength="10" class="input w-full" autocomplete="new-password" aria-describedby="staff-password-hint">
                        <label class="mt-2 inline-flex items-center gap-1.5 text-xs text-neutral-600"><input type="checkbox" data-show-password="staff-password"> Show password</label>
                        <p id="staff-password-hint" class="<?= $hint ?>"><?= htmlspecialchars($passwordHint) ?></p>
                    </div>
                </div>
                <div class="flex justify-end border-t border-neutral-100 mt-6 pt-4">
                    <button type="submit" class="btn btn-primary"><?= \App\Support\Icon::svg('plus') ?> Add staff member</button>
                </div>
            </form>
        </section>

        <aside class="card p-5 sm:p-6" aria-labelledby="staff-rules-heading">
            <h2 id="staff-rules-heading" class="text-base font-semibold text-neutral-900">How staff accounts work</h2>
            <ul class="mt-4 space-y-3.5">
                <?php foreach ($rules as [$icon, $text]): ?>
                <li class="flex gap-3 text-sm text-neutral-600 leading-relaxed">
                    <span class="mt-0.5 text-neutral-400"><?= \App\Support\Icon::svg($icon, 16) ?></span>
                    <span><?= htmlspecialchars($text) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>
    </div>

    <section id="staff-directory" class="card p-0 overflow-hidden" aria-labelledby="staff-list-heading">
        <div class="flex items-center justify-between gap-3 px-5 sm:px-6 pt-5 pb-4">
            <h2 id="staff-list-heading" class="text-base font-semibold text-neutral-900">Current staff</h2>
            <span class="badge badge-neutral"><?= count($staff) ?> total</span>
        </div>
        <?php if (!$staff): ?>
            <div class="empty-state border-t border-neutral-100">
                <span class="empty-icon"><?= \App\Support\Icon::svg('users') ?></span>
                <span class="text-sm font-medium text-neutral-700">No staff accounts yet</span>
                <span class="text-xs text-neutral-500">Add one with the form above.</span>
            </div>
        <?php else: ?>
        <div class="relative overflow-x-auto border-t border-neutral-100">
            <table class="w-full text-sm table-compact">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Activity</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($staff as $s):
                    $sid = (int) $s['id'];
                    $isActive = ($s['status'] ?? 'active') === 'active';
                    $expired = \App\Models\User::temporaryPasswordExpired($s); ?>
                    <tr id="staff-<?= $sid ?>" class="staff-row">
                        <td>
                            <div class="font-medium text-neutral-900"><?= htmlspecialchars($s['name']) ?></div>
                            <div class="text-xs text-neutral-500"><?= htmlspecialchars($s['email']) ?></div>
                        </td>
                        <td class="whitespace-nowrap">
                            <form method="post" action="/admin/staff/<?= $sid ?>/phone" class="flex items-center gap-1.5">
                                <?= \App\Support\Csrf::field() ?>
                                <label for="staff-phone-<?= $sid ?>" class="sr-only">Phone for <?= htmlspecialchars($s['name']) ?></label>
                                <input id="staff-phone-<?= $sid ?>" name="phone" type="tel" inputmode="numeric" data-digits="15" class="input input-sm w-32"
                                       value="<?= htmlspecialchars((string) ($s['phone'] ?? '')) ?>" placeholder="None yet">
                                <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                            </form>
                        </td>
                        <td>
                            <div class="text-neutral-700 whitespace-nowrap">Since <?= $when($s['created_at']) ?></div>
                            <div class="text-xs text-neutral-500">Last sign-in <?= $when($s['last_login_at'] ?? null, 'M j, Y g:i A') ?></div>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="badge <?= $isActive ? 'badge-success' : 'badge-neutral' ?>"><?= $isActive ? 'Active' : 'Deactivated' ?></span>
                                <?php if ($expired): ?>
                                    <span class="badge badge-warning">Temporary password expired</span>
                                <?php elseif (!empty($s['must_change_password'])): ?>
                                    <span class="badge badge-neutral">Must choose a password</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <button type="button" class="btn btn-secondary btn-sm whitespace-nowrap" data-reset-url="/admin/staff/<?= $sid ?>/password"
                                        data-name="<?= htmlspecialchars($s['name']) ?>">
                                    <?= \App\Support\Icon::svg('key') ?> Reset password
                                </button>
                                <form method="post" action="/admin/staff/<?= $sid ?>/status"
                                      data-confirm-title="<?= $isActive ? 'Deactivate this account?' : 'Reactivate this account?' ?>"
                                      data-confirm="<?= htmlspecialchars($isActive
                                          ? "{$s['name']} will be signed out at once and cannot log in until reactivated."
                                          : "{$s['name']} will be able to log in again.") ?>"
                                      data-confirm-label="<?= $isActive ? 'Deactivate' : 'Reactivate' ?>"
                                      <?= $isActive ? 'data-confirm-danger' : '' ?>>
                                    <?= \App\Support\Csrf::field() ?>
                                    <input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                                    <button type="submit" class="<?= $isActive ? 'btn btn-danger' : 'btn btn-secondary' ?> btn-sm">
                                        <?= $isActive ? 'Deactivate' : 'Reactivate' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr class="staff-history-row">
                        <td colspan="5">
                            <details>
                                <summary class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500 hover:text-neutral-900">
                                    <?= \App\Support\Icon::svg('clipboard') ?> History for <?= htmlspecialchars($s['name']) ?>
                                </summary>
                                <?php if (empty($history[$sid])): ?>
                                    <p class="text-xs text-neutral-500 mt-2">Nothing recorded yet. Account changes are recorded from this version on.</p>
                                <?php else: ?>
                                    <ul class="mt-2 space-y-1 text-xs text-neutral-600">
                                        <?php foreach ($history[$sid] as $h): ?>
                                            <li><?= $when($h['created_at'], 'M j, Y g:i A') ?> · <?= htmlspecialchars($actionLabels[$h['action']] ?? $h['action']) ?> · by <?= htmlspecialchars($h['actor_name'] ?? 'the system') ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <p class="px-5 sm:px-6 py-3.5 text-xs text-neutral-500 border-t border-neutral-100 bg-neutral-50">Records retention: a deactivated staff account and its history are kept for 5 years after the person leaves, so past repairs, incidents and SOS responses still show who handled them. Automatic deletion after 5 years is not built yet; until it is, nothing is deleted. Staff see this rule on their Profile page.</p>
    </section>
</div>

<style>
/* A staff member's row and its History row read as one unit: no line between them, one after. */
.staff-row td { border-bottom: 0 !important; }
.staff-history-row td { padding-top: 0 !important; }
.staff-row:hover, .staff-row:hover + .staff-history-row { background-color: var(--color-neutral-50); }
.staff-history-row:hover { background-color: transparent; }
</style>

<!-- Reset password: one dialog for every row; the row's button fills in whose password and where it posts. -->
<dialog id="reset-dialog" class="dialog" aria-labelledby="reset-dialog-title">
    <form method="post" id="reset-form" class="modal-card text-left">
        <?= \App\Support\Csrf::field() ?>
        <div class="modal-icon"><?= \App\Support\Icon::svg('key') ?></div>
        <h3 id="reset-dialog-title" class="modal-title text-center">Reset password</h3>
        <p class="modal-text text-center" style="margin-bottom:1.25rem;">Set a new temporary password for <strong id="reset-name" class="text-neutral-900"></strong>. They will be signed out.</p>
        <label for="staff-reset" class="<?= $label ?>">New temporary password</label>
        <input id="staff-reset" name="new_password" type="password" data-max-bytes="72" required minlength="10" class="input w-full" autocomplete="new-password" aria-describedby="staff-reset-hint">
        <label class="mt-2 inline-flex items-center gap-1.5 text-xs text-neutral-600"><input type="checkbox" data-show-password="staff-reset"> Show password</label>
        <p id="staff-reset-hint" class="<?= $hint ?>"><?= htmlspecialchars($passwordHint) ?></p>
        <div class="modal-actions mt-6">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn-primary">Set password</button>
        </div>
    </form>
</dialog>

<!-- Confirmation for deactivate / reactivate (forms carrying data-confirm). -->
<dialog id="confirm-dialog" class="dialog" aria-labelledby="confirm-dialog-title">
    <div class="modal-card">
        <div class="modal-icon" id="confirm-dialog-icon"><?= \App\Support\Icon::svg('alert-circle') ?></div>
        <h3 id="confirm-dialog-title" class="modal-title"></h3>
        <p id="confirm-dialog-text" class="modal-text"></p>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" data-close-dialog>Cancel</button>
            <button type="button" class="btn btn-primary" id="confirm-dialog-ok"></button>
        </div>
    </div>
</dialog>

<script>
(function () {
    const resetDialog = document.getElementById('reset-dialog');
    const resetForm = document.getElementById('reset-form');
    const resetInput = document.getElementById('staff-reset');
    document.querySelectorAll('[data-reset-url]').forEach(btn => btn.addEventListener('click', () => {
        resetForm.action = btn.dataset.resetUrl;
        document.getElementById('reset-name').textContent = btn.dataset.name;
        resetForm.reset();
        resetInput.type = 'password';
        resetInput.dispatchEvent(new Event('input')); // refresh the byte counter
        resetDialog.showModal();
        resetInput.focus();
    }));

    // Styled confirmation instead of the browser's confirm(): the form submits only after "Deactivate"/"Reactivate".
    const confirmDialog = document.getElementById('confirm-dialog');
    const ok = document.getElementById('confirm-dialog-ok');
    let pending = null;
    document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => {
        e.preventDefault();
        pending = f;
        const danger = f.hasAttribute('data-confirm-danger');
        document.getElementById('confirm-dialog-title').textContent = f.dataset.confirmTitle;
        document.getElementById('confirm-dialog-text').textContent = f.dataset.confirm;
        document.getElementById('confirm-dialog-icon').className = 'modal-icon' + (danger ? ' modal-icon-danger' : '');
        ok.textContent = f.dataset.confirmLabel;
        ok.className = 'btn ' + (danger ? 'btn-danger-solid' : 'btn-primary');
        confirmDialog.showModal();
        confirmDialog.querySelector('[data-close-dialog]').focus();
    }));
    ok.addEventListener('click', () => {
        if (!pending) return;
        ok.classList.add('is-loading');
        pending.submit();
    });

    document.querySelectorAll('[data-close-dialog]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));
    // A click on the dimmed backdrop (the dialog element itself, outside its card) closes it.
    document.querySelectorAll('dialog.dialog').forEach(d => d.addEventListener('click', e => { if (e.target === d) d.close(); }));

    document.querySelectorAll('[data-show-password]').forEach(cb => cb.addEventListener('change', () => {
        document.getElementById(cb.dataset.showPassword).type = cb.checked ? 'text' : 'password';
    }));
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
