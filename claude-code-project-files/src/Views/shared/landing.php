<?php
/**
 * Public landing page — Phase 3 redesign after the approved "Lumora" reference:
 * ink + warm surfaces + one burnt-orange accent, Onest, a first-visit loader,
 * a cursor "liquid reveal" hero, ink room cards and a real-data stats panel.
 * Standalone page (no app nav). No outbound requests: the font, photo and scripts
 * are local; the map is a plain link, not an embed. Every number shown is real.
 */
$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
$c = $contactInfo ?? [];
$map = $mapData ?? ['landmarks' => []];
$rooms = $roomTiers ?? [];
$stats = $stats ?? ['total_rooms' => 0, 'total_beds' => 0, 'vacant_beds' => 0, 'residents' => 0, 'occupancy_pct' => 0, 'avg_repair_hours' => null];
$lowestPrice = $rooms ? min(array_map(fn ($r) => (int) preg_replace('/\D/', '', $r['price']), $rooms)) : null;
$mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(($map['latitude'] ?? '') . ',' . ($map['longitude'] ?? ''));
$inquirySuccess = $inquirySuccess ?? null;
$inquiryError = $inquiryError ?? null;

$statItems = [
    ['value' => (int) $stats['occupancy_pct'], 'suffix' => '%', 'label' => 'of beds occupied right now'],
    ['value' => (int) $stats['residents'], 'suffix' => '', 'label' => 'residents living here'],
    ['value' => (int) $stats['total_rooms'], 'suffix' => '', 'label' => 'rooms across the house'],
];
if ($stats['avg_repair_hours'] !== null) {
    $statItems[] = ['value' => (int) round($stats['avg_repair_hours']), 'suffix' => 'h', 'label' => 'average time to fix a reported repair'];
}

$amenities = [
    ['Security', 'Curfew at 10:00 PM with gate-pass entry, and an SOS button in every resident\'s portal that alerts staff at once.'],
    ['Repairs', 'Report a problem from your phone with a photo; urgent issues go to the top of the caretaker\'s list.'],
    ['Utilities', 'Fiber Wi-Fi, submetered electricity and purified drinking water.'],
    ['Payments', 'Upload your receipt online; your balance updates as soon as it is approved.'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>RJM Boardinghouse — Rooms in Tupi, South Cotabato</title>
    <meta name="description" content="Safe, quiet rooms and bedspaces in Tupi, South Cotabato, minutes from SEAIT and the town center." />
    <meta name="theme-color" content="#0a0a0a" />
    <link rel="preload" href="/assets/fonts/onest-latin.woff2" as="font" type="font/woff2" crossorigin />
    <link rel="stylesheet" href="/assets/css/app.css" />
    <style>
        :root { --shell: 88rem; }
        body { background: #fff; overflow-x: hidden; }
        .shell { max-width: var(--shell); margin-inline: auto; padding-inline: 1.25rem; }
        @media (min-width: 640px) { .shell { padding-inline: 2rem; } }
        .mark { width: 1em; height: 1em; display: inline-block; flex: none; }
        .pill-btn { display: inline-flex; align-items: center; gap: .75rem; border-radius: 9999px; font-size: .9375rem; font-weight: 500; padding: .45rem .45rem .45rem 1.5rem; transition: transform 180ms var(--ease-spring), background-color 160ms; }
        .pill-btn:hover { transform: scale(1.03); }
        .pill-btn .arrow { width: 2.25rem; height: 2.25rem; display: grid; place-items: center; border-radius: 9999px; transition: transform 200ms var(--ease-spring); }
        .pill-btn:hover .arrow { transform: translateX(3px); }
        .pill-dark { background: var(--color-ink); color: #fff; } .pill-dark .arrow { background: #fff; color: var(--color-ink); }
        .pill-light { background: var(--color-surface); color: var(--color-ink); } .pill-light .arrow { background: var(--color-ink); color: #fff; }
        .pill-outline { box-shadow: inset 0 0 0 1px var(--color-line); padding: .85rem 1.6rem; background: rgb(255 255 255 / .6); }
        .eyebrow { display: inline-flex; align-items: center; gap: .5rem; font-size: .875rem; font-weight: 500; color: rgb(17 17 17 / .72); }
        .eyebrow::before { content: ""; width: .375rem; height: .375rem; border-radius: 9999px; background: currentColor; opacity: .7; }
        .eyebrow-light { color: rgb(255 255 255 / .72); }

        /* Loader: first visit per session only */
        #loader { position: fixed; inset: 0; z-index: 120; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2rem; background: var(--color-ink); color: #fff; border-radius: 0 0 2rem 2rem; transition: transform 700ms var(--ease-spring); }
        #loader.is-done { transform: translateY(-100%); }
        #loader .track { width: min(22rem, 72vw); height: 1px; background: rgb(255 255 255 / .15); }
        #loader .fill { height: 100%; width: 0; background: var(--color-accent-from); }
        .no-loader #loader { display: none; }

        /* Hero */
        #home { position: relative; isolation: isolate; overflow: hidden; border-radius: 0 0 2rem 2rem; background: #c9c9c9; }
        #home .base, #home canvas { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        #home canvas { pointer-events: none; }
        #home .veil { position: absolute; inset: 0; z-index: 1; pointer-events: none; background: linear-gradient(90deg, rgb(255 255 255 / .82) 0%, rgb(255 255 255 / .55) 42%, rgb(255 255 255 / .12) 72%), linear-gradient(to bottom, transparent 65%, rgb(255 255 255 / .65)); }
        @media (max-width: 1023px) { #home .veil { background: rgb(255 255 255 / .72); } }
        .watermark { position: absolute; inset-inline: 0; text-align: center; font-weight: 700; line-height: 1; letter-spacing: -.04em; font-size: clamp(7rem, 22vw, 18rem); pointer-events: none; user-select: none; }
        #home .watermark { bottom: 4.5rem; z-index: 1; color: rgb(255 255 255 / .45); }
        .hero-grid { position: relative; z-index: 2; display: flex; flex-direction: column; gap: 2rem; padding-top: 7rem; padding-bottom: 4rem; }
        @media (min-width: 1024px) { .hero-grid { display: grid; grid-template-columns: 7fr 5fr; gap: 2.5rem; min-height: 100svh; padding-top: 9rem; padding-bottom: 7rem; align-items: start; } }
        .hero-title { max-width: 16ch; font-size: clamp(2.5rem, 6vw, 4.75rem); font-weight: 600; line-height: .98; letter-spacing: -.035em; }
        .line { display: block; overflow: hidden; }
        .line > span { display: block; transform: translateY(105%); transition: transform 900ms cubic-bezier(.215,.61,.355,1); }
        .is-in .line > span { transform: none; }
        .rise { opacity: 0; transform: translateY(14px); transition: opacity 700ms var(--ease-spring), transform 700ms var(--ease-spring); }
        .is-in .rise, .rise.is-in { opacity: 1; transform: none; }
        .glass { background: rgb(255 255 255 / .72); backdrop-filter: blur(12px); box-shadow: 0 0 0 1px rgb(230 229 226 / .7); }

        /* Sections */
        .band-tile { display: grid; place-items: center; height: 6rem; border-radius: 9999px; font-size: 1.875rem; font-weight: 500; transition: transform 200ms var(--ease-spring); }
        .band-tile:hover { transform: scale(1.03); }
        @media (min-width: 640px) { .band-tile { height: 10rem; font-size: 2.25rem; } }
        .room-card { position: relative; min-height: 24rem; overflow: hidden; border-radius: 2rem; background: var(--color-ink); color: #fff; padding: 1.75rem; display: flex; flex-direction: column; transition: transform 260ms var(--ease-spring); }
        .room-card:hover { transform: translateY(-6px); }
        .room-card .spark { position: absolute; right: -1.5rem; top: -1.5rem; font-size: 9rem; color: rgb(255 255 255 / .06); }
        .row-link { display: flex; align-items: center; gap: 1.25rem; border-radius: 1.25rem; padding: 1.75rem 1.5rem; transition: background-color 240ms var(--ease-out-soft), padding 240ms var(--ease-out-soft); }
        .row-link:hover { background: var(--color-surface); padding-left: 2rem; padding-right: 1.25rem; }
        .row-link .badge-arrow { width: 2.75rem; height: 2.75rem; display: grid; place-items: center; border-radius: 9999px; background: var(--color-ink); color: #fff; flex: none; transition: transform 240ms var(--ease-spring); }
        .row-link:hover .badge-arrow { transform: translateX(5px); }
        .chip { display: inline-flex; border-radius: 9999px; padding: .4rem .9rem; font-size: .8125rem; }
        .chip-light { box-shadow: inset 0 0 0 1px rgb(255 255 255 / .25); color: #fff; }

        /* Overlays */
        #menu { position: fixed; inset: 0; z-index: 115; display: flex; flex-direction: column; background: var(--color-ink); color: #fff; opacity: 0; pointer-events: none; transition: opacity 280ms var(--ease-out-soft); }
        #menu.is-open { opacity: 1; pointer-events: auto; }
        #menu .menu-item { transform: translateY(1rem); opacity: 0; transition: transform 500ms var(--ease-spring), opacity 500ms var(--ease-spring), color 200ms; }
        #menu.is-open .menu-item { transform: none; opacity: 1; }
        dialog#inquiry { width: min(32rem, calc(100vw - 2rem)); border: 0; padding: 0; border-radius: 2rem; box-shadow: var(--shadow-raised); }
        dialog#inquiry::backdrop { background: rgb(17 17 17 / .3); backdrop-filter: blur(14px); }
        dialog#inquiry[open] { animation: toast-in 280ms var(--ease-spring) both; }
        .field label { display: block; font-size: .8125rem; font-weight: 500; color: rgb(17 17 17 / .62); margin-bottom: .35rem; }

        @media (prefers-reduced-motion: reduce) {
            .line > span, .rise { transform: none !important; opacity: 1 !important; }
            #loader { display: none; }
        }
    </style>
    <script>
        // Decide before first paint: loader only on the first visit this session, never with reduced motion.
        try {
            if (sessionStorage.getItem('rjm-loader-seen') || matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.documentElement.classList.add('no-loader');
            }
        } catch (err) { document.documentElement.classList.add('no-loader'); }
    </script>
</head>
<body class="text-neutral-900">
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-mark" viewBox="0 0 48 48"><path fill="currentColor" d="M24 2c2.2 13.8 7.9 19.6 22 22-14.1 2.4-19.8 8.2-22 22-2.2-13.8-7.9-19.6-22-22 14.1-2.4 19.8-8.2 22-22Z"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-arrow-ur" viewBox="0 0 24 24"><path d="M7 17 17 7M8 7h9v9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M4 4l16 16M20 4 4 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0C18.5 15.4 12 21 12 21Z" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="10" r="2.4" fill="currentColor"/></symbol>
</svg>

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[200] focus:rounded-xl focus:bg-ink focus:px-4 focus:py-2 focus:text-white">Skip to content</a>

<!-- Loader -->
<div id="loader" aria-hidden="true">
    <div class="flex flex-col items-center gap-5 text-center">
        <div class="flex items-center gap-3 text-2xl font-semibold sm:text-3xl"><svg class="mark text-[2rem] text-[#cf8047]"><use href="#i-mark"/></svg> RJM Boardinghouse</div>
        <p class="max-w-[26ch] text-sm text-white/60">A quiet, safe place to live while you study or work in Tupi.</p>
    </div>
    <div class="flex flex-col gap-3" style="width:min(22rem,72vw)">
        <div class="track"><div class="fill" id="loader-fill"></div></div>
        <div class="flex justify-between text-xs font-medium text-white/50"><span>Loading</span><span id="loader-count" class="tabular text-white/80">000</span></div>
    </div>
</div>

<!-- Header -->
<header class="absolute inset-x-0 top-0 z-50">
    <div class="shell flex items-center justify-between gap-6 py-5 sm:py-6">
        <a href="#home" class="flex items-center gap-2 text-lg font-semibold tracking-tight"><svg class="mark text-xl text-accent"><use href="#i-mark"/></svg> RJM Boardinghouse</a>
        <nav aria-label="Primary" class="hidden lg:block">
            <ul class="flex gap-8 text-sm font-medium">
                <li><a href="#rooms" class="opacity-80 hover:opacity-100">Rooms</a></li>
                <li><a href="#amenities" class="opacity-80 hover:opacity-100">Amenities</a></li>
                <li><a href="#location" class="opacity-80 hover:opacity-100">Location</a></li>
                <li><button type="button" data-open-inquiry class="opacity-80 hover:opacity-100">Contact</button></li>
            </ul>
        </nav>
        <div class="flex items-center gap-3">
            <div class="glass hidden items-center gap-3 rounded-[0.875rem] px-3 py-2 text-xs text-neutral-700 md:flex" aria-label="Local time in Tupi">
                <span class="text-neutral-500">Local time</span>
                <span class="min-w-14 font-medium text-neutral-900 tabular" data-clock-time>—</span>
                <span class="text-neutral-400" aria-hidden="true">•</span>
                <span class="font-medium" data-clock-date>—</span>
            </div>
            <a href="<?= $e($dashboardUrl ?? '/login') ?>" class="glass hidden rounded-[0.875rem] px-4 py-2 text-xs font-medium sm:inline-flex"><?= $dashboardUrl ? 'My dashboard' : 'Resident log in' ?></a>
            <button type="button" id="menu-open" class="glass inline-flex items-center gap-2 rounded-[0.875rem] px-4 py-2 text-xs font-medium" aria-controls="menu" aria-expanded="false">
                <svg class="mark text-sm"><use href="#i-menu"/></svg><span class="hidden sm:inline">Menu</span>
            </button>
        </div>
    </div>
</header>

<main id="main">
    <!-- Hero -->
    <section id="home" aria-label="Introduction">
        <img class="base" src="/assets/images/landing-bg.jpg" alt="" fetchpriority="high" />
        <canvas id="reveal" aria-hidden="true"></canvas>
        <div class="veil"></div>
        <div class="watermark rise" style="transition-delay:300ms" aria-hidden="true">RJM</div>

        <div class="shell hero-grid" data-intro>
            <div class="flex flex-col gap-7">
                <p class="eyebrow rise" style="transition-delay:200ms">Boarding house in Tupi, South Cotabato</p>
                <h1 class="hero-title">
                    <span class="line"><span style="transition-delay:250ms">A quiet room,</span></span>
                    <span class="line"><span style="transition-delay:370ms">close to school,</span></span>
                    <span class="line"><span style="transition-delay:490ms">kept safe.</span></span>
                </h1>
                <?php if ((int) $stats['total_beds'] > 0): ?>
                <p class="rise flex items-center gap-3 text-sm font-medium text-neutral-700" style="transition-delay:650ms">
                    <span class="inline-block h-2 w-2 rounded-full <?= (int) $stats['vacant_beds'] > 0 ? 'bg-success-500' : 'bg-neutral-400' ?>"></span>
                    <?= (int) $stats['vacant_beds'] > 0
                        ? (int) $stats['vacant_beds'] . ' of ' . (int) $stats['total_beds'] . ' beds available now'
                        : 'Fully occupied — ask to join the waiting list' ?>
                </p>
                <?php endif; ?>
                <div class="rise flex flex-wrap gap-3" style="transition-delay:750ms">
                    <button type="button" class="pill-btn pill-dark" data-open-inquiry>Ask about a room <span class="arrow"><svg class="mark"><use href="#i-arrow"/></svg></span></button>
                    <a href="#rooms" class="pill-btn pill-outline">See rooms<?= $lowestPrice ? ' from ₱' . number_format($lowestPrice) : '' ?></a>
                </div>
            </div>

            <div class="flex flex-col items-start gap-8 lg:items-end">
                <div class="glass rise w-full max-w-sm rounded-[1.25rem] p-2 lg:w-[19rem]" style="transition-delay:400ms">
                    <div class="flex gap-2">
                        <div class="grid aspect-square w-24 flex-none place-items-center rounded-[0.875rem] bg-ink text-3xl text-[#cf8047]"><svg class="mark"><use href="#i-mark"/></svg></div>
                        <div class="flex flex-1 flex-col justify-between rounded-[0.875rem] bg-surface/70 p-3">
                            <div class="relative min-h-[3.4rem]" aria-live="polite">
                                <p class="text-[.7rem] font-medium text-neutral-500" data-card-caption>Safety</p>
                                <p class="max-w-[9rem] text-sm font-medium leading-snug" data-card-title>Curfew at 10 PM, gate-pass entry.</p>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex gap-1" data-card-dots></div>
                                <div class="flex gap-1">
                                    <button type="button" class="grid h-7 w-7 place-items-center rounded-full bg-white text-neutral-700 shadow-[0_0_0_1px_var(--color-line)] hover:text-ink" data-card-step="-1" aria-label="Previous"><svg class="mark rotate-180 text-xs"><use href="#i-arrow"/></svg></button>
                                    <button type="button" class="grid h-7 w-7 place-items-center rounded-full bg-white text-neutral-700 shadow-[0_0_0_1px_var(--color-line)] hover:text-ink" data-card-step="1" aria-label="Next"><svg class="mark text-xs"><use href="#i-arrow"/></svg></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($map['landmarks'])): ?>
                <div class="rise w-full max-w-sm lg:w-[19rem]" style="transition-delay:550ms">
                    <p class="mb-3 text-xs font-medium text-neutral-500 lg:text-right">Nearby, on foot or by tricycle</p>
                    <ul class="grid gap-2">
                        <?php foreach ($map['landmarks'] as $lm): ?>
                        <li class="flex items-baseline justify-between gap-3 text-sm text-neutral-700">
                            <span class="truncate"><?= $e($lm['name']) ?></span><span class="flex-none font-medium tabular text-neutral-900"><?= $e($lm['distance']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="shell relative z-[2] flex items-center justify-between gap-3 border-t border-neutral-900/10 py-5 text-xs font-medium text-neutral-600">
            <span><?= $e($c['address_line2'] ?? 'Tupi, South Cotabato') ?></span>
            <span class="hidden sm:inline"><?= $e($c['office_hours'] ?? '') ?></span>
            <a href="#about" class="inline-flex items-center gap-2">Scroll to explore <span aria-hidden="true">↓</span></a>
        </div>
    </section>

    <!-- About -->
    <section id="about" class="bg-white">
        <div class="shell grid items-center gap-12 py-20 lg:grid-cols-2 lg:py-28">
            <div class="relative min-h-56 lg:min-h-80">
                <svg class="mark absolute -left-4 top-1/2 -translate-y-1/2 text-[12rem] text-neutral-900/[.06] sm:text-[16rem] lg:text-[20rem]" aria-hidden="true"><use href="#i-pin"/></svg>
                <p class="eyebrow relative">The house</p>
                <p class="rise absolute bottom-0 left-0 flex max-w-xs items-center gap-3 text-sm text-neutral-700">
                    <svg class="mark text-2xl text-ink"><use href="#i-pin"/></svg>
                    <?= $e(($c['address_line1'] ?? '') . ', ' . ($c['address_line2'] ?? '')) ?>
                </p>
            </div>
            <div class="flex flex-col gap-10">
                <h2 class="rise text-2xl font-medium leading-snug tracking-tight sm:text-3xl">
                    We run a small boarding house for students and young workers in Tupi — clean rooms, fair monthly rates, and a caretaker who answers when something breaks.
                </h2>
                <div class="rise flex flex-wrap items-end justify-between gap-6 border-t border-line pt-6">
                    <div>
                        <p class="text-sm text-neutral-500">On-site caretakers</p>
                        <p class="mt-1 font-medium"><?= $e($c['caretakers'] ?? '') ?></p>
                    </div>
                    <a href="#location" class="pill-btn pill-light">Visit us <span class="arrow"><svg class="mark"><use href="#i-arrow"/></svg></span></a>
                </div>
            </div>
        </div>
    </section>

    <!-- Band -->
    <section class="bg-white" aria-label="Study, rest, home">
        <ul class="shell flex flex-col gap-3 py-10 sm:flex-row sm:gap-4">
            <li class="rise flex-1"><span class="band-tile bg-surface">Study</span></li>
            <li class="rise flex-1" style="transition-delay:120ms"><span class="band-tile text-white" style="background:linear-gradient(to bottom right,#cf8047,#97501f)">Rest</span></li>
            <li class="rise flex-1" style="transition-delay:240ms" aria-hidden="true"><span class="band-tile bg-ink text-white"><svg class="mark text-4xl sm:text-5xl"><use href="#i-arrow"/></svg></span></li>
            <li class="rise flex-1" style="transition-delay:360ms"><span class="band-tile bg-surface/60 text-neutral-900/40">Home</span></li>
        </ul>
    </section>

    <!-- Rooms -->
    <section id="rooms" class="bg-white">
        <div class="shell pb-20 pt-10 lg:pb-28">
            <p class="eyebrow rounded-full px-4 py-1.5 shadow-[inset_0_0_0_1px_var(--color-line)]">Rooms</p>
            <h2 class="mt-5 text-4xl font-semibold tracking-tight sm:text-5xl">Choose how you live</h2>
            <ul class="mt-10 grid gap-6 md:grid-cols-3">
                <?php foreach ($rooms as $i => $room): ?>
                <li class="rise" style="transition-delay:<?= $i * 90 ?>ms">
                    <article class="room-card">
                        <svg class="mark spark" aria-hidden="true"><use href="#i-mark"/></svg>
                        <div class="flex items-center justify-between text-xs text-white/60">
                            <span><?= $e($room['capacity']) ?></span>
                            <?php if (!empty($room['popular'])): ?><span class="rounded-full bg-[#cf8047] px-3 py-1 font-medium text-white"><?= $e($room['badge']) ?></span><?php endif; ?>
                        </div>
                        <h3 class="mt-auto pt-16 text-2xl font-medium tracking-tight sm:text-3xl"><?= $e($room['type']) ?></h3>
                        <p class="mt-2 text-white/70"><span class="text-3xl font-semibold text-white tabular"><?= $e($room['price']) ?></span> <?= $e($room['period']) ?></p>
                        <ul class="mt-5 flex flex-wrap gap-2">
                            <?php foreach (array_slice($room['features'], 0, 3) as $feature): ?>
                            <li class="chip chip-light"><?= $e($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="pill-btn pill-light mt-6 self-start" data-open-inquiry data-room="<?= $e($room['type']) ?>">Ask about this room <span class="arrow"><svg class="mark"><use href="#i-arrow-ur"/></svg></span></button>
                    </article>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <!-- Amenities -->
    <section id="amenities" class="bg-white">
        <div class="shell py-20 lg:py-28">
            <p class="eyebrow">Living here</p>
            <h2 class="mb-12 mt-5 max-w-[16ch] text-4xl font-semibold tracking-tight sm:text-5xl">What every resident gets</h2>
            <ul>
                <?php foreach ($amenities as $i => [$title, $text]): ?>
                <li class="rise <?= $i > 0 ? 'border-t border-line' : '' ?>" style="transition-delay:<?= $i * 80 ?>ms">
                    <div class="row-link">
                        <h3 class="flex-1 text-2xl font-medium tracking-tight sm:text-3xl md:text-4xl"><?= $e($title) ?></h3>
                        <p class="hidden max-w-sm text-sm text-neutral-600 lg:block"><?= $e($text) ?></p>
                        <span class="badge-arrow" aria-hidden="true"><svg class="mark"><use href="#i-arrow-ur"/></svg></span>
                    </div>
                    <p class="px-6 pb-5 text-sm text-neutral-600 lg:hidden"><?= $e($text) ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <!-- By the numbers (real data only) -->
    <?php if ((int) $stats['total_beds'] > 0): ?>
    <section class="bg-white" aria-labelledby="numbers-title">
        <div class="shell pb-20 lg:pb-28">
            <div class="rise rounded-[2rem] bg-ink px-6 py-12 text-white sm:px-8 sm:py-16 md:px-16">
                <p class="eyebrow eyebrow-light">By the numbers</p>
                <h2 id="numbers-title" class="mt-4 max-w-[22ch] text-3xl font-medium tracking-tight md:text-4xl">Live figures from the house records, updated every day.</h2>
                <ul class="mt-14 grid grid-cols-2 gap-x-8 gap-y-12 lg:grid-cols-<?= count($statItems) ?>">
                    <?php foreach ($statItems as $s): ?>
                    <li>
                        <p class="text-5xl font-semibold tracking-tight sm:text-6xl md:text-7xl tabular"><span data-count="<?= (int) $s['value'] ?>"><?= (int) $s['value'] ?></span><?= $e($s['suffix']) ?></p>
                        <p class="mt-3 text-sm text-white/60"><?= $e($s['label']) ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Location -->
    <section id="location" class="bg-white">
        <div class="shell grid gap-10 pb-24 lg:grid-cols-2">
            <div>
                <p class="eyebrow">Find us</p>
                <h2 class="mt-5 text-4xl font-semibold tracking-tight sm:text-5xl"><?= $e($map['display_name'] ?? 'RJM Boardinghouse') ?></h2>
                <p class="mt-4 text-neutral-600"><?= $e($map['address'] ?? '') ?></p>
                <a class="pill-btn pill-dark mt-8" href="<?= $e($mapsUrl) ?>" target="_blank" rel="noopener">Open in Google Maps <span class="arrow"><svg class="mark"><use href="#i-arrow-ur"/></svg></span></a>
            </div>
            <dl class="grid gap-6 self-end sm:grid-cols-2">
                <div class="rounded-[1.25rem] bg-surface p-5"><dt class="text-sm text-neutral-500">Office hours</dt><dd class="mt-1 font-medium"><?= $e($c['office_hours'] ?? '') ?></dd></div>
                <div class="rounded-[1.25rem] bg-surface p-5"><dt class="text-sm text-neutral-500">Curfew</dt><dd class="mt-1 font-medium"><?= $e($c['curfew_hours'] ?? '') ?></dd></div>
                <div class="rounded-[1.25rem] bg-surface p-5"><dt class="text-sm text-neutral-500">Call or text</dt><dd class="mt-1 font-medium"><a href="<?= $e($c['primary_tel'] ?? '#') ?>"><?= $e($c['primary_phone'] ?? '') ?></a></dd></div>
                <div class="rounded-[1.25rem] bg-surface p-5"><dt class="text-sm text-neutral-500">Landline</dt><dd class="mt-1 font-medium"><a href="<?= $e($c['landline_tel'] ?? '#') ?>"><?= $e($c['landline'] ?? '') ?></a></dd></div>
            </dl>
        </div>
    </section>
</main>

<!-- Footer -->
<footer class="relative overflow-hidden rounded-t-[2rem] bg-ink text-white">
    <div class="shell relative z-10 pb-10 pt-20 lg:pt-24">
        <div class="flex flex-col gap-8 border-b border-white/10 pb-16 lg:flex-row lg:items-end lg:justify-between">
            <h2 class="max-w-[16ch] text-4xl font-semibold tracking-tight sm:text-5xl md:text-6xl">Have a question about a room?</h2>
            <button type="button" class="pill-btn pill-light self-start lg:self-auto" data-open-inquiry>Ask about a room <span class="arrow"><svg class="mark"><use href="#i-arrow-ur"/></svg></span></button>
        </div>
        <div class="grid gap-12 py-16 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="flex items-center gap-2 text-lg font-semibold"><svg class="mark text-xl text-[#cf8047]"><use href="#i-mark"/></svg> RJM Boardinghouse</p>
                <p class="mt-3 max-w-xs text-sm text-white/60">Rooms and bedspaces in Tupi, South Cotabato.</p>
            </div>
            <div>
                <p class="text-xs text-white/50">Contact</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a class="opacity-75 hover:opacity-100" href="<?= $e($c['primary_tel'] ?? '#') ?>"><?= $e($c['primary_phone'] ?? '') ?></a></li>
                    <li><a class="opacity-75 hover:opacity-100" href="mailto:<?= $e($c['inquiry_email'] ?? '') ?>"><?= $e($c['inquiry_email'] ?? '') ?></a></li>
                </ul>
            </div>
            <div>
                <p class="text-xs text-white/50">Follow</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a class="opacity-75 hover:opacity-100" href="<?= $e($c['facebook_url'] ?? '#') ?>" target="_blank" rel="noopener">Facebook</a></li>
                    <li><a class="opacity-75 hover:opacity-100" href="<?= $e($c['messenger_url'] ?? '#') ?>" target="_blank" rel="noopener">Messenger</a></li>
                </ul>
            </div>
            <div>
                <p class="text-xs text-white/50">Residents</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a class="opacity-75 hover:opacity-100" href="<?= $e($dashboardUrl ?? '/login') ?>"><?= $dashboardUrl ? 'My dashboard' : 'Log in' ?></a></li>
                </ul>
            </div>
        </div>
        <p class="border-t border-white/10 pt-8 text-xs text-white/50">© <?= date('Y') ?> RJM Boardinghouse · <?= $e($c['address_line2'] ?? '') ?></p>
    </div>
    <div class="watermark -bottom-6 z-0 text-white/[.05]" aria-hidden="true">RJM</div>
</footer>

<!-- Menu overlay -->
<div id="menu" role="dialog" aria-modal="true" aria-label="Menu" hidden>
    <div class="shell flex items-center justify-between py-5 sm:py-6">
        <span class="flex items-center gap-2 text-lg font-semibold"><svg class="mark text-xl text-[#cf8047]"><use href="#i-mark"/></svg> RJM Boardinghouse</span>
        <button type="button" id="menu-close" class="inline-flex items-center gap-2 rounded-[0.875rem] px-4 py-2 text-xs font-medium text-white/70 shadow-[inset_0_0_0_1px_rgb(255_255_255/.15)] hover:text-white"><svg class="mark text-sm"><use href="#i-x"/></svg> Close</button>
    </div>
    <nav class="shell flex flex-1 flex-col justify-center" aria-label="Menu">
        <ul class="flex flex-col gap-1">
            <?php foreach ([['#home', 'Home'], ['#rooms', 'Rooms'], ['#amenities', 'Amenities'], ['#location', 'Location']] as $i => [$href, $label]): ?>
            <li><a href="<?= $href ?>" class="menu-item block py-2 text-4xl font-semibold tracking-tight text-white/70 hover:text-white sm:text-6xl" style="transition-delay:<?= $i * 45 + 80 ?>ms"><?= $label ?></a></li>
            <?php endforeach; ?>
            <li><button type="button" data-open-inquiry class="menu-item py-2 text-left text-4xl font-semibold tracking-tight text-white/70 hover:text-white sm:text-6xl" style="transition-delay:260ms">Contact</button></li>
        </ul>
    </nav>
    <div class="shell flex flex-col gap-3 border-t border-white/10 py-6 text-xs text-white/50 sm:flex-row sm:justify-between">
        <span>Local time <span data-clock-time>—</span></span>
        <a href="<?= $e($dashboardUrl ?? '/login') ?>" class="text-white/70 hover:text-white"><?= $dashboardUrl ? 'My dashboard' : 'Resident log in' ?></a>
    </div>
</div>

<!-- Inquiry dialog (real submission to /inquire) -->
<dialog id="inquiry" aria-labelledby="inquiry-title">
    <div class="relative p-6 sm:p-8">
        <button type="button" class="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full bg-surface text-neutral-600 hover:bg-surface-2 hover:text-ink" data-close-inquiry aria-label="Close"><svg class="mark"><use href="#i-x"/></svg></button>
        <div data-inquiry-form-wrap>
            <p class="eyebrow text-accent">Room inquiry</p>
            <h2 id="inquiry-title" class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Tell us what you're looking for.</h2>
            <form id="inquiry-form" action="/inquire" method="post" class="mt-6 flex flex-col gap-4" novalidate>
                <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="field"><label for="inq-name">Name</label><input id="inq-name" name="name" class="input" required maxlength="150" autocomplete="name"></div>
                    <div class="field"><label for="inq-phone">Mobile number</label><input id="inq-phone" name="phone" type="tel" class="input" required pattern="[0-9+()\-\s]{7,20}" autocomplete="tel" placeholder="0917 123 4567"></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="field"><label for="inq-room">Room</label>
                        <select id="inq-room" name="room_type" class="input">
                            <option value="General Inquiry">Not sure yet</option>
                            <?php foreach ($rooms as $room): ?><option value="<?= $e($room['type']) ?>"><?= $e($room['type']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label for="inq-date">Move-in date (optional)</label><input id="inq-date" name="move_in_date" type="date" class="input" min="<?= date('Y-m-d') ?>"></div>
                </div>
                <div class="field"><label for="inq-email">Email (optional)</label><input id="inq-email" name="email" type="email" class="input" maxlength="150" autocomplete="email"></div>
                <div class="field"><label for="inq-message">Anything we should know? (optional)</label><textarea id="inq-message" name="message" rows="3" maxlength="2000" class="input resize-none" placeholder="School or work, budget, questions about the house…"></textarea></div>
                <p class="field-error hidden" id="inquiry-error" role="alert"></p>
                <div class="mt-2 flex items-center justify-between gap-4">
                    <p class="text-xs text-neutral-500">The caretaker usually replies the same day.</p>
                    <button type="submit" class="pill-btn pill-dark" id="inquiry-submit"><span>Send inquiry</span> <span class="arrow"><svg class="mark"><use href="#i-arrow-ur"/></svg></span></button>
                </div>
            </form>
        </div>
        <div class="hidden flex-col items-center gap-4 py-8 text-center" data-inquiry-success>
            <span class="grid h-14 w-14 place-items-center rounded-full bg-ink text-2xl text-[#cf8047]"><svg class="mark"><use href="#i-mark"/></svg></span>
            <h2 class="text-2xl font-semibold">Inquiry sent</h2>
            <p class="max-w-[34ch] text-sm text-neutral-600" data-inquiry-success-text></p>
            <button type="button" class="btn btn-primary" data-close-inquiry>Close</button>
        </div>
    </div>
</dialog>

<?php if ($inquirySuccess || $inquiryError): ?>
<div class="toast-stack" aria-live="polite">
    <div class="toast <?= $inquiryError ? 'toast-error' : '' ?>" role="<?= $inquiryError ? 'alert' : 'status' ?>"><span><?= $e($inquiryError ?: $inquirySuccess) ?></span><button type="button" aria-label="Dismiss" onclick="this.parentElement.remove()">&times;</button></div>
</div>
<?php endif; ?>

<script>
(() => {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];

    // ---- Clock (Tupi local time, matches the server's Asia/Manila) ----
    const fmtTime = new Intl.DateTimeFormat('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila' });
    const fmtDate = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Manila' });
    const tick = () => {
        const now = new Date();
        $$('[data-clock-time]').forEach(el => el.textContent = fmtTime.format(now).replace(/\s/g, '').toLowerCase());
        $$('[data-clock-date]').forEach(el => el.textContent = fmtDate.format(now));
    };
    tick(); setInterval(tick, 1000);

    // ---- Intro: loader (first visit) then hero reveal ----
    const startIntro = () => { $('[data-intro]').classList.add('is-in'); $$('#home .rise').forEach(el => el.classList.add('is-in')); };
    const loader = $('#loader');
    if (document.documentElement.classList.contains('no-loader')) {
        loader.remove(); startIntro();
    } else {
        document.documentElement.style.overflow = 'hidden';
        const fill = $('#loader-fill'), count = $('#loader-count'), FILL_MS = 1100, t0 = performance.now();
        const ease = t => t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
        const step = now => {
            const p = Math.round(ease(Math.min((now - t0) / FILL_MS, 1)) * 100);
            fill.style.width = p + '%'; count.textContent = String(p).padStart(3, '0');
            if (p < 100) return requestAnimationFrame(step);
            loader.classList.add('is-done');
            loader.addEventListener('transitionend', () => { loader.remove(); document.documentElement.style.overflow = ''; startIntro(); }, { once: true });
            try { sessionStorage.setItem('rjm-loader-seen', '1'); } catch (err) {}
        };
        requestAnimationFrame(step);
    }

    // ---- Scroll reveals ----
    const io = new IntersectionObserver(entries => entries.forEach(en => {
        if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
    }), { rootMargin: '0px 0px -10% 0px' });
    $$('main .rise, footer .rise').forEach(el => { if (!el.closest('#home')) io.observe(el); });

    // ---- Hero card carousel ----
    const items = [
        ['Safety', 'Curfew at 10 PM, gate-pass entry.'],
        ['Emergencies', 'One SOS tap alerts the caretaker.'],
        ['Repairs', 'Reported online, tracked to done.'],
    ];
    let idx = 0;
    const cap = $('[data-card-caption]'), title = $('[data-card-title]'), dots = $('[data-card-dots]');
    const render = () => {
        cap.textContent = items[idx][0]; title.textContent = items[idx][1];
        dots.innerHTML = items.map((_, i) => `<span class="block h-1 rounded-full transition-all duration-300 ${i === idx ? 'w-4 bg-neutral-900/70' : 'w-1.5 bg-neutral-900/20'}"></span>`).join('');
    };
    $$('[data-card-step]').forEach(b => b.addEventListener('click', () => { idx = (idx + Number(b.dataset.cardStep) + items.length) % items.length; render(); }));
    render();

    // ---- Count-up stats ----
    const counters = $$('[data-count]');
    if (!reduce && counters.length) {
        counters.forEach(el => el.textContent = '0');
        const cio = new IntersectionObserver(entries => entries.forEach(en => {
            if (!en.isIntersecting) return;
            cio.unobserve(en.target);
            const target = Number(en.target.dataset.count), t0 = performance.now();
            const run = now => { const t = Math.min((now - t0) / 1200, 1); en.target.textContent = Math.round((1 - Math.pow(1 - t, 3)) * target); if (t < 1) requestAnimationFrame(run); };
            requestAnimationFrame(run);
        }), { threshold: .5 });
        counters.forEach(el => cio.observe(el));
    }

    // ---- Menu overlay ----
    const menu = $('#menu'), openBtn = $('#menu-open');
    const setMenu = open => {
        if (open) { menu.hidden = false; requestAnimationFrame(() => menu.classList.add('is-open')); document.documentElement.style.overflow = 'hidden'; $('#menu-close').focus(); }
        else { menu.classList.remove('is-open'); document.documentElement.style.overflow = ''; setTimeout(() => { menu.hidden = true; }, 280); openBtn.focus(); }
        openBtn.setAttribute('aria-expanded', String(open));
    };
    openBtn.addEventListener('click', () => setMenu(true));
    $('#menu-close').addEventListener('click', () => setMenu(false));
    $$('#menu a').forEach(a => a.addEventListener('click', () => setMenu(false)));
    document.addEventListener('keydown', ev => { if (ev.key === 'Escape' && menu.classList.contains('is-open')) setMenu(false); });

    // ---- Inquiry dialog: real POST to /inquire (JSON), success state ----
    const dlg = $('#inquiry'), form = $('#inquiry-form'), err = $('#inquiry-error');
    const formWrap = $('[data-inquiry-form-wrap]'), success = $('[data-inquiry-success]');
    $$('[data-open-inquiry]').forEach(b => b.addEventListener('click', () => {
        if (menu.classList.contains('is-open')) setMenu(false);
        if (b.dataset.room) $('#inq-room').value = b.dataset.room;
        dlg.showModal(); $('#inq-name').focus();
    }));
    $$('[data-close-inquiry]').forEach(b => b.addEventListener('click', () => dlg.close()));
    dlg.addEventListener('click', ev => { if (ev.target === dlg) dlg.close(); });
    dlg.addEventListener('close', () => setTimeout(() => {
        if (!success.classList.contains('hidden')) { form.reset(); success.classList.add('hidden'); success.classList.remove('flex'); formWrap.classList.remove('hidden'); }
    }, 300));
    form.addEventListener('submit', async ev => {
        ev.preventDefault();
        err.classList.add('hidden');
        $$('.input', form).forEach(i => i.removeAttribute('aria-invalid'));
        const bad = $$('.input', form).find(i => !i.checkValidity());
        if (bad) {
            bad.setAttribute('aria-invalid', 'true'); bad.focus();
            err.textContent = bad.id === 'inq-phone' ? 'Enter a mobile number (7–20 digits).' : 'Please fill in your name and mobile number.';
            err.classList.remove('hidden'); return;
        }
        const btn = $('#inquiry-submit span'); btn.textContent = 'Sending…';
        try {
            const res = await fetch('/inquire', { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Could not send your inquiry.');
            $('[data-inquiry-success-text]').textContent = data.message;
            formWrap.classList.add('hidden'); success.classList.remove('hidden'); success.classList.add('flex');
        } catch (e2) {
            err.textContent = e2.message || 'Could not send your inquiry. Please call us instead.';
            err.classList.remove('hidden');
        } finally { btn.textContent = 'Send inquiry'; }
    });

    // ---- Liquid reveal: the cursor paints a night duotone of the hero photo ----
    const canvas = $('#reveal'), hero = $('#home'), img = $('#home .base');
    if (reduce || !matchMedia('(pointer: fine)').matches) { canvas.remove(); return; }
    const ctx = canvas.getContext('2d'), brush = document.createElement('canvas'), bctx = brush.getContext('2d');
    const cover = document.createElement('canvas'), cctx = cover.getContext('2d');
    const BRUSH = 143, DECAY = 0.016;
    let dpr = 1, radius = 0, points = [], last = null, idle = 999;
    const layout = () => {
        const r = hero.getBoundingClientRect();
        dpr = Math.min(devicePixelRatio || 1, 2);
        canvas.width = cover.width = Math.round(r.width * dpr);
        canvas.height = cover.height = Math.round(r.height * dpr);
        radius = BRUSH * dpr;
        brush.width = brush.height = Math.ceil(radius * 2);
        if (!img.complete || !img.naturalWidth) return;
        const scale = Math.max(cover.width / img.naturalWidth, cover.height / img.naturalHeight);
        const w = img.naturalWidth * scale, h = img.naturalHeight * scale;
        cctx.filter = 'grayscale(1) sepia(.55) hue-rotate(-12deg) saturate(1.4) brightness(.42) contrast(1.15)';
        cctx.drawImage(img, (cover.width - w) / 2, (cover.height - h) / 2, w, h);
    };
    new ResizeObserver(layout).observe(hero);
    img.complete ? layout() : img.addEventListener('load', layout);
    addEventListener('pointermove', ev => {
        const r = hero.getBoundingClientRect();
        const x = (ev.clientX - r.left) * dpr, y = (ev.clientY - r.top) * dpr;
        if (x < -radius || y < -radius || x > canvas.width + radius || y > canvas.height + radius) { last = null; return; }
        if (last) {
            const dist = Math.hypot(x - last.x, y - last.y), n = Math.min(Math.ceil(dist / Math.max(radius * .3, 1)), 60);
            for (let i = 1; i <= n; i++) points.push({ x: last.x + (x - last.x) * i / n, y: last.y + (y - last.y) * i / n });
        } else points.push({ x, y });
        last = { x, y };
        if (idle > 120) { idle = 0; requestAnimationFrame(frame); }
    }, { passive: true });
    const stamp = (x, y) => {
        const d = brush.width, c = d / 2;
        bctx.globalCompositeOperation = 'source-over'; bctx.clearRect(0, 0, d, d);
        const g = bctx.createRadialGradient(c, c, 0, c, c, c);
        g.addColorStop(0, 'rgba(255,255,255,1)'); g.addColorStop(.55, 'rgba(255,255,255,.82)'); g.addColorStop(1, 'rgba(255,255,255,0)');
        bctx.fillStyle = g; bctx.fillRect(0, 0, d, d);
        bctx.globalCompositeOperation = 'source-in';
        bctx.drawImage(cover, x - c, y - c, d, d, 0, 0, d, d);
        ctx.globalCompositeOperation = 'source-over'; ctx.drawImage(brush, x - c, y - c);
    };
    const frame = () => {
        const drawing = points.length > 0;
        idle = drawing ? 0 : idle + 1;
        ctx.globalCompositeOperation = 'destination-out';
        ctx.fillStyle = `rgba(0,0,0,${drawing ? DECAY : Math.min(DECAY + idle * .004, .5)})`;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        if (drawing) { points.forEach(p => stamp(p.x, p.y)); points = []; }
        if (idle > 120) { ctx.clearRect(0, 0, canvas.width, canvas.height); return; }
        requestAnimationFrame(frame);
    };
})();
</script>
</body>
</html>
