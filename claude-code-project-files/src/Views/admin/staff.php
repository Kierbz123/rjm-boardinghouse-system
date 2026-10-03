<?php
$pageTitle = 'Staff Accounts';
ob_start();
$staff = $staff ?? [];
?>
<div class="max-w-5xl mx-auto px-5 py-6 space-y-6">
    <header>
        <h1 class="text-2xl font-bold text-neutral-900">Staff Accounts</h1>
        <p class="text-sm text-neutral-500 mt-1">Add staff who handle maintenance, incidents and SOS alerts. Deactivated staff can't log in; their records are kept.</p>
    </header>

    <section class="card p-5" aria-labelledby="add-staff-heading">
        <h2 id="add-staff-heading" class="text-base font-semibold text-neutral-900 mb-4">Add a staff member</h2>
        <form method="post" action="/admin/staff" class="grid gap-4 sm:grid-cols-3">
            <?= \App\Support\Csrf::field() ?>
            <div>
                <label for="staff-name" class="block text-xs font-semibold text-neutral-700 mb-1">Full name</label>
                <input id="staff-name" name="name" required maxlength="150" class="input w-full" autocomplete="off">
            </div>
            <div>
                <label for="staff-email" class="block text-xs font-semibold text-neutral-700 mb-1">Email</label>
                <input id="staff-email" name="email" type="email" required maxlength="150" class="input w-full" autocomplete="off">
            </div>
            <div>
                <label for="staff-password" class="block text-xs font-semibold text-neutral-700 mb-1">Temporary password</label>
                <input id="staff-password" name="password" type="password" required minlength="8" class="input w-full" autocomplete="new-password" aria-describedby="staff-password-hint">
                <p id="staff-password-hint" class="text-xs text-neutral-500 mt-1">At least 8 characters. They can change it under Profile.</p>
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="btn btn-primary">Add staff member</button>
            </div>
        </form>
    </section>

    <section class="card p-0 overflow-hidden" aria-labelledby="staff-list-heading">
        <h2 id="staff-list-heading" class="text-base font-semibold text-neutral-900 px-5 pt-5 pb-3">Current staff (<?= count($staff) ?>)</h2>
        <?php if (!$staff): ?>
            <p class="px-5 pb-5 text-sm text-neutral-500">No staff accounts yet. Add one above.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Name</th>
                        <th scope="col" class="px-5 py-3">Email</th>
                        <th scope="col" class="px-5 py-3">Since</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                <?php foreach ($staff as $s):
                    $isActive = ($s['status'] ?? 'active') === 'active'; ?>
                    <tr>
                        <td class="px-5 py-3 font-medium text-neutral-900"><?= htmlspecialchars($s['name']) ?></td>
                        <td class="px-5 py-3 text-neutral-600"><?= htmlspecialchars($s['email']) ?></td>
                        <td class="px-5 py-3 text-neutral-600"><?= htmlspecialchars(date('M j, Y', strtotime($s['created_at']))) ?></td>
                        <td class="px-5 py-3">
                            <span class="badge <?= $isActive ? 'badge-success' : 'badge-neutral' ?>"><?= $isActive ? 'Active' : 'Deactivated' ?></span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <form method="post" action="/admin/staff/<?= (int) $s['id'] ?>/status" class="inline"
                                  <?= $isActive ? 'data-confirm="Deactivate ' . htmlspecialchars($s['name']) . '? They will be signed out."' : '' ?>>
                                <?= \App\Support\Csrf::field() ?>
                                <input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                                <button type="submit" class="<?= $isActive ? 'btn-danger' : 'btn btn-secondary' ?>">
                                    <?= $isActive ? 'Deactivate' : 'Reactivate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
<script>
document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => {
    if (!confirm(f.dataset.confirm)) e.preventDefault();
}));
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
