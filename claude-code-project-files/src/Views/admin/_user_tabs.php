<?php
/** The User Management tabs, on top of All Users, Boarders and Staff. Set $userTab to 'all', 'boarders' or 'staff' first. */
(function (string $current): void {
    $tabs = [
        'all' => ['All users', '/admin/staff', 'users'],
        'boarders' => ['Boarders', '/admin/boarders', 'building'],
        'staff' => ['Staff', '/admin/staff?view=staff', 'wrench'],
    ];
    echo '<nav data-user-tabs aria-label="User Management" class="flex flex-wrap gap-2">';
    foreach ($tabs as $key => [$text, $href, $icon]) {
        $on = $key === $current;
        echo '<a href="' . $href . '" class="btn btn-sm ' . ($on ? 'btn-primary' : 'btn-secondary') . '"' . ($on ? ' aria-current="page"' : '') . '>'
            . \App\Support\Icon::svg($icon, 14) . ' ' . $text . '</a>';
    }
    echo '</nav>';
})($userTab ?? '');
