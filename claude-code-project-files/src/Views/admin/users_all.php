<?php
$pageTitle = 'User Management';
ob_start();
$userTab = 'all';
$e = fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = function (?string $ts, string $format = 'M j, Y'): string {
    if ($ts === null || $ts === '') {
        return '<span class="text-neutral-400">Never</span>';
    }
    $t = strtotime($ts);
    return '<time datetime="' . date('c', $t) . '" title="' . date('Y-m-d H:i T', $t) . '">' . htmlspecialchars(date($format, $t)) . '</time>';
};
// The current filters, minus defaults, so links stay short and paging keeps what the admin chose.
$link = function (array $change) use ($filters, $page): string {
    $params = array_merge($filters + ['page' => $page], $change);
    $params = array_filter($params, fn ($v, $k) => $v !== '' && !($k === 'page' && (int) $v === 1) && !($k === 'sort' && $v === 'name'), ARRAY_FILTER_USE_BOTH);
    return '/admin/staff' . ($params ? '?' . http_build_query($params) : '');
};
$initials = function (string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    return mb_strtoupper(mb_substr($words[0], 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
};
$residency = [
    'pending' => ['badge-warning', 'Move-in pending'],
    'active' => ['badge-info', 'Living here'],
    'on_notice' => ['badge-warning', 'On notice'],
    'moved_out' => ['badge-neutral', 'Moved out'],
];
$filtered = $filters['q'] !== '' || $filters['type'] !== '' || $filters['status'] !== '';
$stats = [
    ['total', 'Total users', 'Residents and staff, archived not included', 'metric-accent-primary'],
    ['boarders', 'Boarders', 'Current residents', 'metric-accent-info'],
    ['staff', 'Staff', 'Staff accounts', 'metric-accent-info'],
    ['active', 'Active accounts', 'Can sign in', 'metric-accent-success'],
    ['inactive', 'Deactivated or archived', 'Cannot sign in', 'metric-accent-warning'],
];
$label = 'block text-xs font-medium text-neutral-600 mb-1';
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div class="page-banner">
        <div>
            <h1>User Management</h1>
            <p>Manage boarders, staff members, and their accounts in one place.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/admin/boarders#add-boarder" class="btn btn-secondary btn-sm"><?= \App\Support\Icon::svg('plus', 14) ?> Add boarder</a>
            <a href="/admin/staff?view=staff#add-staff" class="btn btn-secondary btn-sm"><?= \App\Support\Icon::svg('plus', 14) ?> Add staff</a>
        </div>
    </div>

    <?php require __DIR__ . '/_user_tabs.php'; ?>

    <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr));">
        <?php foreach ($stats as [$key, $title, $sub, $accent]): ?>
            <div class="card p-4 <?= $accent ?>">
                <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider"><?= $title ?></p>
                <p class="text-3xl font-bold text-neutral-900 mt-1.5 tabular" data-stat="<?= $key ?>"><?= (int) $counts[$key] ?></p>
                <p class="text-caption text-neutral-400 mt-1"><?= $sub ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="card p-0 overflow-hidden" aria-labelledby="users-heading">
        <form method="get" action="/admin/staff" class="flex flex-wrap items-end gap-3 px-5 sm:px-6 pt-5 pb-4" role="search">
            <div style="flex: 1 1 16rem;">
                <label for="users-q" class="<?= $label ?>">Search</label>
                <input id="users-q" name="q" type="search" maxlength="100" class="input w-full" value="<?= $e($filters['q']) ?>"
                       placeholder="Name, email, phone, room or #id">
            </div>
            <div>
                <label for="users-type" class="<?= $label ?>">Type</label>
                <select id="users-type" name="type" class="input">
                    <?php foreach (['' => 'Everyone', 'boarder' => 'Boarders', 'staff' => 'Staff'] as $v => $t): ?>
                        <option value="<?= $v ?>"<?= $filters['type'] === $v ? ' selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="users-status" class="<?= $label ?>">Account status</label>
                <select id="users-status" name="status" class="input">
                    <?php foreach (['' => 'Current (not archived)', 'active' => 'Active', 'inactive' => 'Deactivated staff', 'pending' => 'Move-in pending', 'archived' => 'Archived residents'] as $v => $t): ?>
                        <option value="<?= $v ?>"<?= $filters['status'] === $v ? ' selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="users-sort" class="<?= $label ?>">Sort by</label>
                <select id="users-sort" name="sort" class="input">
                    <?php foreach (['name' => 'Name', 'newest' => 'Newest first', 'last_login' => 'Last sign-in'] as $v => $t): ?>
                        <option value="<?= $v ?>"<?= $filters['sort'] === $v ? ' selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary"><?= \App\Support\Icon::svg('search', 16) ?> Apply</button>
                <?php if ($filtered || $filters['sort'] !== 'name'): ?>
                    <a href="/admin/staff" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="flex items-center justify-between gap-3 px-5 sm:px-6 pb-3">
            <h2 id="users-heading" class="text-base font-semibold text-neutral-900">People</h2>
            <span class="text-xs text-neutral-500" role="status">
                <?= $total ?> <?= $total === 1 ? 'person' : 'people' ?><?= $filtered ? ($total === 1 ? ' matches' : ' match') : '' ?><?= $pages > 1 ? " · page {$page} of {$pages}" : '' ?>
            </span>
        </div>

        <?php if (!$users): ?>
            <div class="empty-state border-t border-neutral-100">
                <span class="empty-icon"><?= \App\Support\Icon::svg('users') ?></span>
                <?php if ($total > 0): ?>
                    <span class="text-sm font-medium text-neutral-700">This page is past the end of the list</span>
                    <a href="<?= $e($link(['page' => 1])) ?>" class="text-xs font-medium underline">Go to the first page</a>
                <?php elseif ($filtered): ?>
                    <span class="text-sm font-medium text-neutral-700">No one matches these filters</span>
                    <a href="/admin/staff" class="text-xs font-medium underline">Clear the filters</a>
                <?php else: ?>
                    <span class="text-sm font-medium text-neutral-700">No residents or staff yet</span>
                    <span class="text-xs text-neutral-500">Use Add boarder or Add staff above.</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <?php // relative: the screen-reader-only header label is positioned inside this scroll box, not against the page. ?>
        <div class="relative overflow-x-auto border-t border-neutral-100">
            <table class="w-full text-sm table-compact">
                <thead>
                    <tr>
                        <th scope="col">User</th>
                        <th scope="col">Type</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Assignment</th>
                        <th scope="col">Account</th>
                        <th scope="col">Registered</th>
                        <th scope="col" class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $id = (int) $u['id'];
                    $isBoarder = $u['role'] === 'boarder';
                    $phone = $isBoarder ? $u['contact_number'] : $u['phone'];
                    [$accountClass, $accountText] = match ($u['status']) {
                        'active' => ['badge-success', 'Active'],
                        'archived' => ['badge-neutral', 'Archived'],
                        default => ['badge-neutral', 'Deactivated'],
                    }; ?>
                    <tr data-user-id="<?= $id ?>">
                        <td>
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-xs font-semibold text-neutral-600" aria-hidden="true"><?= $e($initials($u['name'])) ?></span>
                                <div class="min-w-0">
                                    <div class="font-medium text-neutral-900 truncate"><?= $e($u['name']) ?></div>
                                    <div class="text-xs text-neutral-500">#<?= $id ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge <?= $isBoarder ? 'badge-info' : 'badge-neutral' ?>"><?= $isBoarder ? 'Boarder' : 'Staff' ?></span></td>
                        <td>
                            <div class="text-neutral-700"><?= $e($u['email']) ?></div>
                            <div class="text-xs text-neutral-500"><?= $phone ? $e($phone) : 'No phone' ?></div>
                        </td>
                        <td class="whitespace-nowrap">
                            <?php if (!$isBoarder): ?>
                                <span class="text-neutral-700">Repairs, incidents, SOS</span>
                            <?php elseif ($u['room_number'] !== null): ?>
                                <span class="text-neutral-700">Room <?= $e($u['room_number']) ?></span><?php if ($u['bed_label']): ?><span class="text-xs text-neutral-500"> · <?= $e($u['bed_label']) ?></span><?php endif; ?>
                            <?php else: ?>
                                <span class="text-neutral-400">No room yet</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="badge <?= $accountClass ?>"><?= $accountText ?></span>
                                <?php if ($isBoarder && isset($residency[$u['residency_status']]) && $u['status'] !== 'archived'): ?>
                                    <span class="badge <?= $residency[$u['residency_status']][0] ?>"><?= $residency[$u['residency_status']][1] ?></span>
                                <?php endif; ?>
                                <?php if (\App\Models\User::temporaryPasswordExpired($u)): ?>
                                    <span class="badge badge-warning">Temporary password expired</span>
                                <?php elseif (!empty($u['must_change_password'])): ?>
                                    <span class="badge badge-neutral">Must choose a password</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="text-neutral-700 whitespace-nowrap"><?= $when($u['created_at']) ?></div>
                            <div class="text-xs text-neutral-500 whitespace-nowrap">Last sign-in <?= $when($u['last_login_at'], 'M j, Y') ?></div>
                        </td>
                        <td class="text-right">
                            <?php if ($isBoarder): ?>
                                <a href="/admin/boarders/<?= $id ?>" class="btn btn-secondary btn-sm whitespace-nowrap" aria-label="View profile of <?= $e($u['name']) ?>">View profile</a>
                            <?php else: ?>
                                <a href="/admin/staff?view=staff#staff-<?= $id ?>" class="btn btn-secondary btn-sm whitespace-nowrap" aria-label="Manage account of <?= $e($u['name']) ?>">Manage</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="flex items-center justify-between gap-3 px-5 sm:px-6 py-3.5 border-t border-neutral-100" aria-label="Pages">
                <?php if ($page > 1): ?>
                    <a href="<?= $e($link(['page' => min($page - 1, $pages)])) ?>" rel="prev" class="btn btn-secondary btn-sm">Previous</a>
                <?php else: ?><span></span><?php endif; ?>
                <span class="text-xs text-neutral-500">Page <?= min($page, $pages) ?> of <?= $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a href="<?= $e($link(['page' => $page + 1])) ?>" rel="next" class="btn btn-secondary btn-sm">Next</a>
                <?php else: ?><span></span><?php endif; ?>
            </nav>
        <?php endif; ?>

        <p class="px-5 sm:px-6 py-3.5 text-xs text-neutral-500 border-t border-neutral-100 bg-neutral-50">
            "Account" is whether the person can sign in; a resident's stay (move-in pending, living here, on notice, moved out) is shown beside it.
            Deactivating a staff member keeps all their records. Archiving a resident, from their profile, moves them out and frees their bed
            but keeps every payment and record, and can be undone. Edit a resident's details and bed on their profile, and staff access on the Staff tab.
        </p>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../shared/layout.php';
