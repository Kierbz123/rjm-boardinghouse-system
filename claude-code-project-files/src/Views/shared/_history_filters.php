<?php
/**
 * The search and filter bar above Maintenance History and Incident History.
 *
 * Expects:
 *   $historyAction      the page's own path, so the form posts back to itself
 *   $filters            the validated filters the controller used
 *   $historyStatuses    value => label for the status select
 *   $historyTiers       value => label for the priority select, or [] to leave it out
 *   $historySearchHint  placeholder text (optional)
 *
 * Markup and classes follow the User Management filter bar (admin/users_all.php),
 * so nothing new has to be added to the compiled CSS.
 */
$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$label = 'block text-xs font-semibold text-neutral-600 mb-1';
$id = str_replace('/', '-', trim($historyAction, '/'));
$isFiltered = ($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['tier'] ?? '') !== '';
?>
<form method="get" action="<?= $e($historyAction) ?>" class="card flex flex-wrap items-end gap-3 px-5 sm:px-6 py-4" role="search">
    <div style="flex: 1 1 16rem;">
        <label for="<?= $id ?>-q" class="<?= $label ?>">Search</label>
        <input id="<?= $id ?>-q" name="q" type="search" maxlength="100" class="input w-full"
               value="<?= $e($filters['q'] ?? '') ?>" placeholder="<?= $e($historySearchHint ?? 'Description, person or room') ?>">
    </div>
    <div>
        <label for="<?= $id ?>-status" class="<?= $label ?>">Status</label>
        <select id="<?= $id ?>-status" name="status" class="input">
            <?php foreach ($historyStatuses as $value => $text): ?>
                <option value="<?= $e($value) ?>"<?= ($filters['status'] ?? '') === (string) $value ? ' selected' : '' ?>><?= $e($text) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (!empty($historyTiers)): ?>
        <div>
            <label for="<?= $id ?>-tier" class="<?= $label ?>">Priority</label>
            <select id="<?= $id ?>-tier" name="tier" class="input">
                <?php foreach ($historyTiers as $value => $text): ?>
                    <option value="<?= $e($value) ?>"<?= ($filters['tier'] ?? '') === (string) $value ? ' selected' : '' ?>><?= $e($text) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary"><?= \App\Support\Icon::svg('search', 16) ?> Apply</button>
        <?php if ($isFiltered): ?>
            <a href="<?= $e($historyAction) ?>" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </div>
</form>
