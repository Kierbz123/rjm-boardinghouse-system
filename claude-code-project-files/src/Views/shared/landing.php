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
$latLng = ($map['latitude'] ?? '') . ',' . ($map['longitude'] ?? '');
$mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($latLng);
$mapEmbedUrl = 'https://maps.google.com/maps?q=' . rawurlencode($latLng) . '&z=17&hl=en&output=embed';
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

// Each row opens to show the details. Everything here describes how the system really works.
$amenities = [
    [
        'title' => 'Security',
        'summary' => 'Live CCTV covers the entire boardinghouse, and an SOS button in every resident\'s portal alerts staff at once. No curfew.',
        'points' => [
            'Live CCTV covers the entire boardinghouse.',
            'No curfew: come and go when you need to.',
            'One tap on SOS in the resident portal alerts every staff member with your room and bed; you get a notice when they acknowledge and when it is resolved.',
            'Lost items, noise or other concerns can be reported as incidents and followed until staff close them.',
            'On-site caretakers: ' . ($c['caretakers'] ?? 'available on site') . '.',
        ],
    ],
    [
        'title' => 'Repairs',
        'summary' => 'Report a problem from your phone with a photo; urgent issues go to the top of the caretaker\'s list.',
        'points' => [
            'Describe the problem and attach a photo or short video from the resident portal (up to ' . \App\Support\Uploads::maxMb() . ' MB per file: any phone photo, or about 10–20 seconds of video).',
            'Each request is rated critical, high, medium or low — gas leaks, sparks or flooding go straight to the top.',
            'You are notified when work starts and when the repair is done.',
        ],
    ],
    [
        'title' => 'Utilities',
        'summary' => 'Free water, solar power back-up, fiber Wi-Fi and laundry service.',
        'points' => [
            'Water is free.',
            'Solar power back-up, so there are no power interruptions.',
            'Each room has its own electricity submeter. The room\'s bill is divided equally among the people staying in it, and your share is due together with your rent.',
            'Laundry service is available: just ask the caretakers.',
            'Fiber Wi-Fi throughout the house.',
            'Purified drinking water station and a shared study hall and pantry.',
        ],
    ],
    [
        'title' => 'Payments',
        'summary' => 'Upload your receipt online; your balance updates as soon as it is approved.',
        'points' => [
            'Rent is due every 30 days, counted from the day you move in: move in on October 20 and your first payment is due on November 19, then every 30 days after that.',
            'Your share of your room\'s electricity bill (the bill divided by the number of people in the room) is due together with your rent.',
            'Pay by GCash, Maya or bank transfer, then upload the receipt in the resident portal.',
            'An administrator checks every receipt. Until then it shows as Pending review, and you are notified as soon as it is approved or rejected.',
            'Approved payments clear your oldest unpaid rent first; anything extra becomes credit.',
            'A small daily late fee applies after a due date while that rent is unpaid. It stops on the day you upload a receipt that is approved, so review time is never charged, and an administrator can waive it for a good reason.',
        ],
    ],
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
        .pill-ghost { box-shadow: inset 0 0 0 1px rgb(255 255 255 / .35); color: #fff; background: rgb(255 255 255 / .12); backdrop-filter: blur(8px); }
        .pill-ghost:hover { background: rgb(255 255 255 / .22); }
        .pill-ghost .arrow { background: rgb(255 255 255 / .22); color: #fff; }
        .eyebrow { display: inline-flex; align-items: center; gap: .5rem; font-size: .875rem; font-weight: 500; color: rgb(17 17 17 / .72); }
        .eyebrow::before { content: ""; width: .375rem; height: .375rem; border-radius: 9999px; background: currentColor; opacity: .7; }
        .eyebrow-light { color: rgb(255 255 255 / .72); }

        /* Floating sticky nav */
        #topbar { pointer-events: none; }
        #topbar .nav-float { pointer-events: auto; }
        .nav-float { background: rgb(255 255 255 / .72); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); box-shadow: 0 0 0 1px rgb(230 229 226 / .8); transition: background-color 240ms var(--ease-out-soft), box-shadow 240ms var(--ease-out-soft); }
        #topbar.is-scrolled .nav-float { background: rgb(255 255 255 / .94); box-shadow: 0 0 0 1px var(--color-line), 0 12px 32px -16px rgb(10 10 10 / .25); }
        .nav-link { display: inline-flex; border-radius: 9999px; padding: .45rem .85rem; color: rgb(17 17 17 / .72); transition: background-color 160ms, color 160ms; }
        .nav-link:hover { background: rgb(10 10 10 / .05); color: var(--color-ink); }
        .nav-link[aria-current="true"] { background: var(--color-ink); color: #fff; }
        html { scroll-padding-top: 6rem; }
        /* Loader: first visit per session only */
        #loader { position: fixed; inset: 0; z-index: 120; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2rem; background: var(--color-ink); color: #fff; border-radius: 0 0 2rem 2rem; transition: transform 700ms var(--ease-spring); }
        #loader.is-done { transform: translateY(-100%); }
        #loader .track { width: min(22rem, 72vw); height: 1px; background: rgb(255 255 255 / .15); }
        #loader .fill { height: 100%; width: 0; background: var(--color-accent-from); }
        .no-loader #loader { display: none; }

        /* Hero & Cinematic Background Layer */
        #home { position: relative; isolation: isolate; border-radius: 0 0 2rem 2rem; min-height: 100svh; }

        .watermark { position: absolute; inset-inline: 0; text-align: center; font-weight: 700; line-height: 1; letter-spacing: -.04em; font-size: clamp(7rem, 22vw, 18rem); pointer-events: none; user-select: none; }
        #home .watermark { bottom: 4.5rem; z-index: 3; color: rgb(10 10 10 / .2); transition: color 700ms ease; }
        .hero-grid { position: relative; z-index: 4; display: flex; flex-direction: column; gap: 2rem; padding-top: 7rem; padding-bottom: 4rem; }
        @media (min-width: 1024px) { .hero-grid { display: grid; grid-template-columns: 7fr 5fr; gap: 2.5rem; min-height: 100svh; padding-top: 9rem; padding-bottom: 7rem; align-items: start; } }
        .hero-title { max-width: 16ch; font-size: clamp(2.5rem, 6vw, 4.75rem); font-weight: 600; line-height: .98; letter-spacing: -.035em; transition: color 700ms ease; }
        .line { display: block; overflow: hidden; }
        .line > span { display: block; transform: translateY(105%); transition: transform 900ms cubic-bezier(.215,.61,.355,1); }
        .is-in .line > span { transform: none; }
        .rise { opacity: 0; transform: translateY(14px); transition: opacity 700ms var(--ease-spring), transform 700ms var(--ease-spring); }
        .is-in .rise, .rise.is-in { opacity: 1; transform: none; }
        .glass { background: rgb(255 255 255 / .72); backdrop-filter: blur(12px); box-shadow: 0 0 0 1px rgb(230 229 226 / .7); }

        /* Liquid Glass */
        .liquid-glass {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: none;
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.4), 0 8px 32px -8px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
            transition: background-color 700ms ease, box-shadow 700ms ease;
        }
        .liquid-glass::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1.4px;
            background: linear-gradient(180deg, rgba(255,255,255,0.65) 0%, rgba(255,255,255,0.25) 20%, rgba(255,255,255,0) 40%, rgba(255,255,255,0) 60%, rgba(255,255,255,0.25) 80%, rgba(255,255,255,0.65) 100%);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }


        /* Sections */
        .room-card-item { perspective: 1200px; }
        .room-flipper { position: relative; width: 100%; height: 100%; min-height: 28rem; border-radius: 2rem; transform-style: preserve-3d; transition: transform 600ms cubic-bezier(.2, .8, .2, 1); }
        .room-flipper.is-flipped { transform: rotateY(180deg); }
        .room-face { width: 100%; height: 100%; min-height: 28rem; border-radius: 2rem; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
        .room-face-front { position: relative; z-index: 2; transform: rotateY(0deg); }
        .room-face-back { position: absolute; inset: 0; transform: rotateY(180deg); overflow: hidden; background: var(--color-ink); color: #fff; display: flex; flex-direction: column; }
        .room-card { position: relative; min-height: 28rem; overflow: hidden; border-radius: 2rem; background: var(--color-ink); color: #fff; padding: 1.75rem; display: flex; flex-direction: column; transition: transform 260ms var(--ease-spring), box-shadow 260ms var(--ease-spring); }
        .room-flipper:not(.is-flipped) .room-face-front:hover { transform: translateY(-6px); box-shadow: 0 16px 36px -12px rgb(0 0 0 / .45); }
        .room-flipper.is-flipped .room-face-back:hover { box-shadow: 0 16px 36px -12px rgb(0 0 0 / .55); }
        .room-card .spark { position: absolute; right: -1.5rem; top: -1.5rem; font-size: 9rem; color: rgb(255 255 255 / .06); pointer-events: none; }
        .room-preview-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; transform: scale(1.03); transition: transform 700ms var(--ease-out-soft); }
        .room-flipper.is-flipped .room-preview-img { transform: scale(1); }
        .room-preview-glow { position: absolute; width: 180px; height: 180px; border-radius: 50%; background: radial-gradient(circle, rgba(207, 128, 71, .4) 0%, transparent 70%); top: -30px; right: -30px; filter: blur(25px); pointer-events: none; z-index: 1; }
        .room-preview-scrim { position: absolute; inset: 0; z-index: 1; background: linear-gradient(to bottom, rgba(17,17,17,.6) 0%, rgba(17,17,17,.1) 25%, rgba(17,17,17,.1) 65%, rgba(17,17,17,.8) 100%); pointer-events: none; }
        .room-back-content { position: relative; z-index: 2; height: 100%; padding: 1.75rem; display: flex; flex-direction: column; justify-content: space-between; }
        .row-link { display: flex; align-items: center; gap: 1.25rem; border-radius: 1.25rem; padding: 1.75rem 1.5rem; transition: background-color 240ms var(--ease-out-soft), padding 240ms var(--ease-out-soft); }
        .row-link:hover { background: var(--color-surface); padding-left: 2rem; padding-right: 1.25rem; }
        .row-link .badge-arrow { width: 2.75rem; height: 2.75rem; display: grid; place-items: center; border-radius: 9999px; background: var(--color-ink); color: #fff; flex: none; transition: transform 240ms var(--ease-spring); }
        .row-link:hover .badge-arrow { transform: translateX(5px); }
        /* Amenity rows: native <details>, the whole row is the toggle */
        .amenity summary { list-style: none; cursor: pointer; }
        .amenity summary::-webkit-details-marker { display: none; }
        .amenity .badge-arrow svg { transition: transform 240ms var(--ease-spring); }
        .amenity[open] summary { background: var(--color-surface); padding-left: 2rem; padding-right: 1.25rem; border-radius: 1.25rem 1.25rem 0 0; }
        .amenity[open] .badge-arrow { background: var(--color-accent); }
        .amenity[open] .badge-arrow svg { transform: rotate(90deg); }
        .amenity[open] .amenity-body { background: var(--color-surface); border-radius: 0 0 1.25rem 1.25rem; animation: toast-in 260ms var(--ease-spring) both; }
        .chip { display: inline-flex; border-radius: 9999px; padding: .4rem .9rem; font-size: .8125rem; }
        .chip-light { box-shadow: inset 0 0 0 1px rgb(255 255 255 / .25); color: #fff; }

        /* House Showcase & Media View */
        .house-media-frame {
            position: relative;
            width: 100%;
            max-width: 100%;
            aspect-ratio: 4 / 3;
            border-radius: 1.25rem;
            overflow: hidden;
            box-shadow: 0 16px 40px -16px rgba(17, 17, 17, 0.14), inset 0 0 0 1px var(--color-line);
            background: var(--color-surface);
        }
        @media (min-width: 640px) {
            .house-media-frame {
                border-radius: 1.75rem;
            }
        }
        .house-media-view {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            transition: opacity 350ms ease, transform 350ms ease;
        }
        .house-media-view[hidden] {
            opacity: 0;
            pointer-events: none;
            transform: scale(0.98);
        }
        /* Caretaker Card */
        .caretaker-card {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1rem;
            border-radius: 1.25rem;
            background: var(--color-surface);
            border: 1px solid var(--color-line);
            transition: border-color 200ms ease, box-shadow 200ms ease, transform 200ms ease;
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }
        @media (min-width: 640px) {
            .caretaker-card {
                gap: 1rem;
                padding: 1.1rem 1.35rem;
            }
        }
        .caretaker-card:hover {
            border-color: rgba(207, 128, 71, 0.4);
            box-shadow: 0 10px 28px -10px rgba(17, 17, 17, 0.1);
            transform: translateY(-2px);
        }

        /* Address Bar in #about */
        .house-address-bar {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            padding: 0.75rem 0.875rem;
            border-radius: 1rem;
            background: rgba(246, 245, 243, 0.85);
            box-shadow: inset 0 0 0 1px var(--color-line);
            width: 100%;
            min-width: 0;
        }
        .house-address-main {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            min-width: 0;
            flex: 1;
        }
        .house-address-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            flex: none;
        }
        .house-address-actions > * {
            flex: 1;
            text-align: center;
            justify-content: center;
        }
        @media (min-width: 640px) {
            .house-address-bar {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                padding: 0.875rem 1rem;
                gap: 1rem;
            }
            .house-address-actions {
                width: auto;
                flex: none;
            }
            .house-address-actions > * {
                flex: none;
            }
        }

        /* Caretaker Spotlight Row in #about */
        .caretaker-row {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            border-top: 1px solid var(--color-line);
            padding-top: 1.25rem;
            width: 100%;
        }
        .caretaker-row .caretaker-card {
            width: 100%;
            min-width: 0;
        }
        .caretaker-row .pill-btn {
            width: 100%;
            justify-content: center;
            flex: none;
        }
        @media (min-width: 640px) {
            .caretaker-row {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 1.25rem;
                padding-top: 1.5rem;
            }
            .caretaker-row .caretaker-card {
                flex: 1;
                width: auto;
            }
            .caretaker-row .pill-btn {
                width: auto;
                flex: none;
            }
        }
        .value-pillar {
            display: flex;
            align-items: flex-start;
            gap: .875rem;
            padding: .875rem 1rem;
            border-radius: 1rem;
            background: #faf9f6;
            border: 1px solid var(--color-line);
            transition: background-color 200ms ease, transform 200ms ease, box-shadow 200ms ease;
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }
        .value-pillar:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px -8px rgba(17, 17, 17, 0.08);
        }

        /* Location Info Cards & Transit Tags */
        .location-info-card {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            padding: 1.15rem 1.25rem;
            border-radius: 1.25rem;
            background: var(--color-surface);
            border: 1px solid var(--color-line);
            transition: border-color 200ms ease, box-shadow 200ms ease, transform 200ms ease;
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }
        @media (min-width: 640px) {
            .location-info-card {
                padding: 1.25rem 1.35rem;
            }
        }
        .location-info-card:hover {
            border-color: rgba(207, 128, 71, 0.4);
            box-shadow: 0 10px 28px -10px rgba(17, 17, 17, 0.08);
            transform: translateY(-2px);
        }
        .transit-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.85rem;
            border-radius: 9999px;
            background: var(--color-surface);
            border: 1px solid var(--color-line);
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--color-ink);
            transition: all 180ms ease;
        }
        .transit-badge:hover {
            background: #fff;
            border-color: rgba(207, 128, 71, 0.5);
            box-shadow: 0 4px 12px -4px rgba(17, 17, 17, 0.08);
        }
        .amenity[open] .amenity-body { background: var(--color-surface); border-radius: 0 0 1.25rem 1.25rem; animation: toast-in 260ms var(--ease-spring) both; }
        .chip { display: inline-flex; border-radius: 9999px; padding: .4rem .9rem; font-size: .8125rem; }
        .chip-light { box-shadow: inset 0 0 0 1px rgb(255 255 255 / .25); color: #fff; }

        /* Overlays */
        #menu { position: fixed; inset: 0; z-index: 115; display: flex; flex-direction: column; background: var(--color-ink); color: #fff; opacity: 0; pointer-events: none; transition: opacity 280ms var(--ease-out-soft); }
        #menu.is-open { opacity: 1; pointer-events: auto; }
        #menu .menu-item { transform: translateY(1rem); opacity: 0; transition: transform 500ms var(--ease-spring), opacity 500ms var(--ease-spring), color 200ms; }
        #menu.is-open .menu-item { transform: none; opacity: 1; }
        /* Tailwind's reset zeroes every margin, which cancels <dialog>'s built-in centring — restore it. */
        dialog#inquiry { position: fixed !important; inset: 0 !important; margin: auto; width: min(36rem, calc(100vw - 2rem)); max-height: calc(100dvh - 2rem); overflow-y: auto; border: 0; padding: 0; border-radius: 2rem; box-shadow: var(--shadow-raised); }
        dialog#inquiry::backdrop { background: rgb(17 17 17 / .3); backdrop-filter: blur(14px); }
        dialog#inquiry[open] { animation: toast-in 280ms var(--ease-spring) both; }
        .field label { display: block; font-size: .8125rem; font-weight: 500; color: rgb(17 17 17 / .62); margin-bottom: .35rem; }

        @media (prefers-reduced-motion: reduce) {
            .line > span, .rise { transform: none !important; opacity: 1 !important; }
            #loader { display: none; }
            .room-flipper { transition: none !important; }
            .room-preview-img { transition: none !important; transform: none !important; }
        }
        .bp-room-poly:focus { outline: none; }
        .bp-room-poly:focus-visible { stroke: #cf8047; stroke-width: 4; }
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
<!-- Floating sticky navigation: stays reachable while scrolling; the full-screen menu is the phone version -->
<header id="topbar" class="fixed inset-x-0 top-3 z-50 px-3 sm:top-4 sm:px-6 pointer-events-none">
    <nav aria-label="Primary" class="nav-float pointer-events-auto mx-auto flex max-w-6xl items-center gap-2 rounded-full py-2 pl-5 pr-2">
        <a href="#home" class="mr-2 flex flex-none items-center gap-2 text-base font-semibold tracking-tight"><svg class="mark text-lg text-accent"><use href="#i-mark"/></svg> RJM Boardinghouse</a>
        <ul class="flex flex-1 items-center gap-1 text-sm font-medium max-lg:hidden">
            <li><a href="#rooms" class="nav-link" data-nav-link>Rooms</a></li>
            <li><a href="#amenities" class="nav-link" data-nav-link>Amenities</a></li>
            <li><a href="#location" class="nav-link" data-nav-link>Location</a></li>
            <li><button type="button" class="nav-link" data-open-inquiry>Contact</button></li>
        </ul>
        <span class="ml-auto flex items-center gap-2 pr-2 text-xs text-neutral-600 max-xl:hidden" aria-label="Local time in Tupi">
            <span class="tabular font-medium text-ink" data-clock-time>—</span>
            <span aria-hidden="true">·</span>
            <a class="hover:text-ink" href="<?= !empty($c['primary_tel']) ? $e($c['primary_tel']) : 'javascript:void(0);' ?>"><?= $e($c['primary_phone'] ?? '') ?></a>
        </span>
        <a href="<?= $e($dashboardUrl ?? '/login') ?>" class="ml-auto flex-none rounded-full px-4 py-2 text-sm font-medium shadow-[inset_0_0_0_1px_var(--color-line)] transition-colors hover:bg-white xl:ml-0"><?= $dashboardUrl ? 'My dashboard' : 'Log in' ?></a>
        <button type="button" class="flex-none rounded-full bg-ink px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-ink-soft max-sm:hidden" data-open-inquiry>Ask about a room</button>
        <button type="button" id="menu-open" class="grid h-9 w-9 flex-none place-items-center rounded-full bg-ink text-white lg:hidden" aria-controls="menu" aria-expanded="false" aria-label="Open menu">
            <svg class="mark text-sm" aria-hidden="true"><use href="#i-menu"/></svg>
        </button>
    </nav>
</header>

<main id="main">
    <!-- Hero -->
    <section id="home" aria-label="Introduction">
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
                <p class="rise availability-text flex items-center gap-3 text-sm font-medium text-neutral-700 transition-colors" style="transition-delay:650ms">
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
                <div class="liquid-glass rise w-full max-w-sm rounded-[1.25rem] p-2 lg:w-[19rem]" style="transition-delay:400ms">
                    <div class="flex gap-2">
                        <div class="grid aspect-square w-24 flex-none place-items-center rounded-[0.875rem] bg-ink text-3xl text-[#cf8047]"><svg class="mark"><use href="#i-mark"/></svg></div>
                        <div class="flex flex-1 flex-col justify-between rounded-[0.875rem] bg-surface/70 p-3">
                            <div class="relative min-h-[3.4rem]" aria-live="polite">
                                <p class="text-[.7rem] font-medium text-neutral-500" data-card-caption>Safety</p>
                                <p class="max-w-[9rem] text-sm font-medium leading-snug" data-card-title>Live CCTV across the whole house.</p>
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
                <div class="liquid-glass rise w-full max-w-sm rounded-[1.25rem] p-2 lg:w-[20rem]" style="transition-delay:550ms">
                    <div class="flex items-baseline justify-between px-3 pb-2 pt-1.5">
                        <p class="text-xs font-medium text-neutral-800">Nearby</p>
                        <p class="text-[0.7rem] text-neutral-500">from the house</p>
                    </div>
                    <ul class="grid gap-1">
                        <?php foreach ($map['landmarks'] as $lm): ?>
                        <li>
                            <a href="<?= $e($lm['directions']) ?>" target="_blank" rel="noopener" title="Walking directions to <?= $e($lm['name']) ?> on Google Maps"
                               class="group flex items-center gap-3 rounded-[0.875rem] bg-white/75 px-3 py-2.5 transition-colors hover:bg-white">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-ink"><?= $e($lm['short']) ?></span>
                                    <span class="block truncate text-xs text-neutral-500"><?= $e($lm['type']) ?></span>
                                </span>
                                <span class="flex-none text-sm font-semibold text-ink tabular"><?= number_format($lm['km'], 1) ?> km</span>
                                <svg class="mark flex-none text-xs text-neutral-400 transition-colors group-hover:text-accent" aria-hidden="true"><use href="#i-arrow-ur"/></svg>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="px-3 pb-1.5 pt-2 text-[0.7rem] leading-snug text-neutral-500">Straight-line distance. Tap a place for the walking route on Google Maps.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="shell relative z-[2] flex items-center justify-between gap-3 border-t border-neutral-900/10 py-5 text-xs font-medium text-neutral-600">
            <span><?= $e($c['address_line2'] ?? 'Tupi, South Cotabato') ?></span>
            <span class="max-sm:hidden inline"><?= $e($c['office_hours'] ?? '') ?></span>
            <a href="#about" class="inline-flex items-center gap-2">Scroll to explore <span aria-hidden="true">↓</span></a>
        </div>
    </section>

    <!-- About / The House -->
    <section id="about" class="relative overflow-hidden bg-white">
        <!-- Subtle warm gradient wash in background -->
        <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-gradient-to-b from-[#f9f8f5] to-transparent"></div>

        <div class="shell relative grid items-center gap-10 py-14 sm:py-20 lg:grid-cols-2 lg:gap-16 lg:py-28">
            <!-- Left Column: Dual-View Media Showcase -->
            <div class="flex flex-col gap-4 sm:gap-5 min-w-0 w-full">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="eyebrow">The house</p>
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Active Student Residence
                    </span>
                </div>

                <!-- Showcase Frame with Photo & Map -->
                <div class="rise house-media-frame w-full">
                    <!-- View A: Exterior Photograph -->
                    <div class="house-media-view" id="house-view-photo">
                        <?php $houseImg = is_file(__DIR__ . '/../../../public/assets/images/house_compound.jpg') ? 'house_compound.jpg' : 'house_exterior.jpg'; ?>
                        <img src="/assets/images/<?= $houseImg ?>" alt="RJM Boardinghouse compound in Tupi, South Cotabato" class="h-full w-full object-cover" loading="lazy" />
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-black/20"></div>
                        <div class="absolute bottom-3 left-3 right-3 sm:bottom-4 sm:left-4 sm:right-4 flex items-end justify-between gap-2 text-white">
                            <div class="min-w-0 flex-1">
                                <span class="rounded-full bg-black/60 px-2.5 py-0.5 text-[0.6875rem] font-medium backdrop-blur-md">Purok Sanctuary</span>
                                <h3 class="mt-1 text-sm font-semibold leading-tight drop-shadow-sm sm:text-lg truncate sm:whitespace-normal">Main Residence & Grounds</h3>
                            </div>
                            <span class="hidden sm:inline-flex flex-none rounded-full bg-white/20 px-3 py-1 text-xs font-medium backdrop-blur-md">Gated & CCTV-secured</span>
                        </div>
                    </div>

                    <!-- View B: Interactive Google Map -->
                    <div class="house-media-view" id="house-view-map" hidden>
                        <p class="absolute inset-0 grid place-items-center p-6 text-center text-sm text-neutral-500">Map needs an internet connection</p>
                        <iframe class="relative h-full w-full border-0" title="Map showing RJM Boardinghouse in Tupi, South Cotabato"
                                src="<?= $e($mapEmbedUrl) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                    </div>

                    <!-- Floating View Switcher Pill -->
                    <div class="absolute right-3 top-3 z-10 flex items-center gap-1 rounded-full bg-black/60 p-1 text-xs font-medium text-white shadow-lg backdrop-blur-md">
                        <button type="button" class="rounded-full px-3 py-1 transition-all bg-white text-ink shadow-sm font-semibold" data-house-view="photo">Photo</button>
                        <button type="button" class="rounded-full px-3 py-1 opacity-70 transition-all hover:opacity-100" data-house-view="map">Live map</button>
                    </div>

                    <!-- Campus Distance Badge -->
                    <div class="absolute left-3 top-3 z-10 hidden sm:flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1 text-xs font-medium text-white backdrop-blur-md">
                        <svg class="mark text-accent" aria-hidden="true"><use href="#i-pin"/></svg>
                        <span>3 mins from SEAIT</span>
                    </div>
                </div>

                <!-- Interactive Address Row -->
                <div class="rise house-address-bar">
                    <div class="house-address-main">
                        <span class="grid h-8 w-8 flex-none place-items-center rounded-xl bg-ink text-white">
                            <svg class="mark text-sm" aria-hidden="true"><use href="#i-pin"/></svg>
                        </span>
                        <span class="truncate font-medium text-neutral-800 text-xs sm:text-sm" title="<?= $e(($c['address_line1'] ?? '') . ', ' . ($c['address_line2'] ?? '')) ?>">
                            <?= $e(($c['address_line1'] ?? '') . ', ' . ($c['address_line2'] ?? '')) ?>
                        </span>
                    </div>
                    <div class="house-address-actions">
                        <button type="button" class="rounded-full bg-white px-3 py-1.5 text-xs font-medium shadow-[0_0_0_1px_var(--color-line)] transition-colors hover:bg-neutral-50" data-copy-address="<?= $e(($c['address_line1'] ?? '') . ', ' . ($c['address_line2'] ?? '')) ?>">
                            <span>Copy address</span>
                        </button>
                        <a href="<?= $e($mapsUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full bg-accent px-3 py-1.5 text-xs font-medium text-white transition-opacity hover:opacity-90">
                            <span>Open in Maps</span>
                            <svg class="mark text-[0.7rem]" aria-hidden="true"><use href="#i-arrow-ur"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column: Editorial & Trust Hub -->
            <div class="flex flex-col gap-6 sm:gap-8 lg:gap-10 min-w-0 w-full">
                <div class="flex flex-col gap-3 sm:gap-4">
                    <h2 class="rise text-2xl font-semibold leading-snug tracking-tight sm:text-3xl lg:text-4xl break-words">
                        Clean rooms, fair monthly rates, and a caretaker who answers when something breaks.
                    </h2>
                    <p class="rise text-sm leading-relaxed text-neutral-600 sm:text-base lg:text-lg break-words">
                        We run an intentional, quiet boarding house in Purok Sanctuary tailored for students and young workers. Here, everyday living is uncomplicated, transparent, and secure.
                    </p>
                </div>

                <!-- Three Core Value Pillars -->
                <div class="rise grid gap-2.5 sm:gap-3 sm:grid-cols-3">
                    <div class="value-pillar">
                        <span class="grid h-8 w-8 flex-none place-items-center rounded-xl bg-accent/10 text-accent">
                            <?= \App\Support\Icon::svg('shield-check', 18) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-xs sm:text-sm font-semibold text-neutral-900">Peace of mind</h4>
                            <p class="mt-0.5 text-[0.75rem] leading-normal text-neutral-500 sm:text-xs">Gated entrance, private locks & CCTV coverage.</p>
                        </div>
                    </div>
                    <div class="value-pillar">
                        <span class="grid h-8 w-8 flex-none place-items-center rounded-xl bg-accent/10 text-accent">
                            <?= \App\Support\Icon::svg('zap', 18) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-xs sm:text-sm font-semibold text-neutral-900">Fast repairs</h4>
                            <p class="mt-0.5 text-[0.75rem] leading-normal text-neutral-500 sm:text-xs">Logged online, fixed within hours by on-site staff.</p>
                        </div>
                    </div>
                    <div class="value-pillar">
                        <span class="grid h-8 w-8 flex-none place-items-center rounded-xl bg-accent/10 text-accent">
                            <?= \App\Support\Icon::svg('lightbulb', 18) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-xs sm:text-sm font-semibold text-neutral-900">Study culture</h4>
                            <p class="mt-0.5 text-[0.75rem] leading-normal text-neutral-500 sm:text-xs">Enforced 10 PM quiet hours & dedicated desks.</p>
                        </div>
                    </div>
                </div>

                <!-- Caretaker Spotlight Card & Action -->
                <div class="rise caretaker-row">
                    <div class="caretaker-card">
                        <div class="relative grid h-11 w-11 sm:h-12 sm:w-12 flex-none place-items-center rounded-2xl bg-ink font-semibold text-white text-xs sm:text-sm shadow-sm">
                            <span>MJ</span>
                            <span class="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-emerald-500"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="text-[0.6875rem] sm:text-xs font-semibold uppercase tracking-wider text-accent">On-Site Caretakers</p>
                                <span class="h-1 w-1 rounded-full bg-neutral-400"></span>
                                <span class="text-[0.6875rem] sm:text-[0.7rem] text-emerald-600 font-medium">On duty</span>
                            </div>
                            <p class="mt-0.5 truncate font-semibold text-sm sm:text-base text-neutral-900" title="<?= $e($c['caretakers'] ?? 'Ate Mary & Kuya Jun') ?>"><?= $e($c['caretakers'] ?? 'Ate Mary & Kuya Jun') ?></p>
                            <p class="text-[0.75rem] text-neutral-500 truncate sm:whitespace-normal">Ground floor · Always reachable for repairs & assistance</p>
                        </div>
                    </div>
                    <a href="#location" class="pill-btn pill-light">Visit us <span class="arrow"><svg class="mark"><use href="#i-arrow"/></svg></span></a>
                </div>
            </div>
        </div>
    </section>

    <!-- Rooms -->
    <section id="rooms" class="bg-white">
        <div class="shell pb-20 pt-10 lg:pb-28">
            <p class="eyebrow rounded-full px-4 py-1.5 shadow-[inset_0_0_0_1px_var(--color-line)]">Rooms</p>
            <h2 class="mt-5 text-4xl font-semibold tracking-tight sm:text-5xl">Choose how you live</h2>
            <ul class="mt-10 grid gap-6 md:grid-cols-3">
                <?php foreach ($rooms as $i => $room): ?>
                <li class="rise room-card-item" style="transition-delay:<?= $i * 90 ?>ms">
                    <div class="room-flipper" id="room-card-<?= $e($room['id']) ?>">
                        <!-- Front Face -->
                        <article class="room-card room-face room-face-front">
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
                            <div class="mt-6 flex flex-wrap items-center gap-2">
                                <button type="button" class="pill-btn pill-light" data-open-inquiry data-room="<?= $e($room['type']) ?>">Ask about this room <span class="arrow"><svg class="mark"><use href="#i-arrow-ur"/></svg></span></button>
                                <button type="button" class="pill-btn pill-ghost" data-flip-trigger aria-controls="room-card-<?= $e($room['id']) ?>" aria-expanded="false" aria-label="View <?= $e($room['type']) ?> photograph">View room <span class="arrow"><svg class="mark"><use href="#i-arrow"/></svg></span></button>
                            </div>
                        </article>

                        <!-- Back Face (Image Preview) -->
                        <div class="room-face room-face-back" aria-hidden="true">
                            <img src="<?= $e($room['image'] ?? '') ?>" alt="<?= $e($room['type']) ?> interior preview" class="room-preview-img" loading="lazy" />
                            <div class="room-preview-glow" aria-hidden="true"></div>
                            <div class="room-preview-scrim" aria-hidden="true"></div>
                            <div class="room-back-content">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="rounded-full bg-black/60 px-3 py-1 text-white/90 backdrop-blur-md"><?= $e($room['capacity']) ?></span>
                                    <button type="button" class="grid h-8 w-8 place-items-center rounded-full bg-black/50 text-white/80 backdrop-blur-md transition-colors hover:bg-black/80 hover:text-white" data-flip-back aria-label="Return to room details">
                                        <svg class="mark text-xs" aria-hidden="true"><use href="#i-x"/></svg>
                                    </button>
                                </div>
                                <div class="mt-auto">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" class="pill-btn pill-light" data-open-inquiry data-room="<?= $e($room['type']) ?>">Ask about this room <span class="arrow"><svg class="mark"><use href="#i-arrow-ur"/></svg></span></button>
                                        <button type="button" class="pill-btn pill-ghost" data-flip-back aria-label="Back to room details">Back to details <span class="arrow"><svg class="mark rotate-180"><use href="#i-arrow"/></svg></span></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <!-- Architecture & Layout (Live-Synced 2D SVG Architectural Blueprint - Style A) -->
    <section id="blueprint" class="bg-white">
        <div class="shell py-20 lg:py-28 border-t border-line rise" id="building-blueprint-section">
            <!-- Section Header & Controls -->
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="eyebrow rounded-full px-4 py-1.5 shadow-[inset_0_0_0_1px_var(--color-line)] inline-block">Architecture & Layout</p>
                    <h2 class="mt-5 text-4xl font-semibold tracking-tight sm:text-5xl">Explore the floor plan</h2>
                    <p class="mt-3 text-sm text-neutral-600 sm:text-base max-w-xl">
                        A site plan of the whole compound, synced with live room records. Tap a room to see its status, where it sits and whether a bed is free.
                    </p>
                </div>

                <!-- Controls Cluster -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Dynamic Floor Switcher Tabs -->
                    <div class="flex items-center rounded-full bg-surface p-1 shadow-[inset_0_0_0_1px_var(--color-line)]" id="blueprint-floor-tabs" role="tablist" aria-label="Floor switcher">
                        <!-- Floor pills dynamically rendered by script -->
                    </div>

                    <!-- Canvas Controls -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" id="bp-btn-zoom-in" class="pill-btn pill-light !px-3 !py-2 text-xs" title="Zoom In" aria-label="Zoom In">
                            <svg class="mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                        </button>
                        <button type="button" id="bp-btn-zoom-out" class="pill-btn pill-light !px-3 !py-2 text-xs" title="Zoom Out" aria-label="Zoom Out">
                            <svg class="mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                        </button>
                        <button type="button" id="bp-btn-reset" class="pill-btn pill-light !px-3.5 !py-2 text-xs font-semibold tabular" title="Reset View">
                            100%
                        </button>
                        <button type="button" id="bp-btn-doors" class="pill-btn pill-light bp-toggle-btn !px-3.5 !py-2 text-xs font-medium is-active" title="Toggle Door Swing Arcs">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 mr-1 bp-toggle-dot"></span> Doors
                        </button>
                        <button type="button" id="bp-btn-dims" class="pill-btn pill-light bp-toggle-btn !px-3.5 !py-2 text-xs font-medium is-active" title="Toggle Metric Dimension Callouts">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 mr-1 bp-toggle-dot"></span> Dims
                        </button>
                        <button type="button" id="bp-btn-theme" class="pill-btn pill-light !px-3.5 !py-2 text-xs font-semibold" title="Toggle Theme (CAD Blueprint / System Dark)">
                            <span id="bp-theme-label">CAD Mode</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Blueprint Canvas Card Wrapper -->
            <div class="mt-8 bp-canvas-card theme-native border border-white/10 shadow-2xl relative transition-colors duration-300" id="blueprint-canvas-card">
                <!-- Technical HUD Header Bar -->
                <div class="flex items-center justify-between px-6 py-3.5 border-b border-white/10 text-xs font-mono text-white/60 bg-black/40 backdrop-blur-md select-none">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center gap-2 text-white font-semibold tracking-wider">
                            <span class="h-2 w-2 rounded-full bg-accent animate-pulse"></span>
                            <span id="bp-hud-title">LEVEL 01 · 1F PLAN</span>
                        </span>
                        <span class="text-white/20">|</span>
                        <span class="hidden sm:inline">SITE PLAN · NOT TO SCALE</span>
                        <span class="hidden md:inline text-white/20">|</span>
                        <span class="hidden md:inline">4 BLOCKS · 18 ROOMS</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <span id="bp-live-indicator" class="flex items-center gap-1.5 text-emerald-400 font-medium">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Live Synced
                        </span>
                        <span class="hidden lg:inline text-white/40">2 STOREYS · NORTH &amp; SOUTH BLOCKS</span>
                    </div>
                </div>

                <!-- SVG Viewport (Draggable Pan & Scroll Zoom) -->
                <div class="relative w-full overflow-hidden cursor-grab active:cursor-grabbing select-none" id="blueprint-viewport" style="min-height: 520px; height: clamp(520px, 62vh, 720px);">
                    <svg id="blueprint-svg" class="w-full h-full" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet" role="group" aria-label="Site plan of the boarding house">
                        <!-- SVG elements dynamically generated -->
                    </svg>

                    <!-- Architectural Compass Rose Badge -->
                    <div class="absolute top-5 right-5 pointer-events-none rounded-xl bg-black/50 backdrop-blur-md p-2.5 border border-white/10 flex flex-col items-center text-white/70 shadow-lg">
                        <svg width="26" height="26" viewBox="0 0 32 32" fill="none">
                            <polygon points="16,2 20,16 16,13 12,16" fill="#cf8047"/>
                            <polygon points="16,30 20,16 16,13 12,16" fill="rgba(255,255,255,0.4)"/>
                            <circle cx="16" cy="16" r="2" fill="#fff"/>
                        </svg>
                        <span class="text-[9px] font-mono font-bold tracking-widest text-accent mt-0.5">N</span>
                    </div>

                    <!-- Blueprint Status Legend Badge -->
                    <div class="absolute bottom-5 left-5 pointer-events-none flex flex-wrap items-center gap-3.5 rounded-xl bg-black/55 backdrop-blur-md px-4 py-2 border border-white/10 text-[11px] font-mono text-white/80 shadow-lg">
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Vacant</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-500"></span> Available Beds</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-neutral-400"></span> Occupied</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm border border-dashed border-white/50"></span> Not listed</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#cf8047]"></span> Selected</span>
                    </div>
                </div>

                <!-- Interactive Spec Bar (Selected Room Details & Inquiry CTA) -->
                <div id="blueprint-spec-bar" aria-live="polite" class="border-t border-white/10 bg-black/75 backdrop-blur-md px-6 py-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between text-white">
                    <!-- Populated dynamically by script -->
                </div>
            </div>
        </div>
    </section>

    <!-- Amenities -->
    <section id="amenities" class="bg-white">
        <div class="shell py-20 lg:py-28">
            <p class="eyebrow">Living here</p>
            <h2 class="mb-12 mt-5 max-w-[16ch] text-4xl font-semibold tracking-tight sm:text-5xl">What every resident gets</h2>
            <ul>
                <?php foreach ($amenities as $i => $item): ?>
                <li class="rise <?= $i > 0 ? 'border-t border-line' : '' ?>" style="transition-delay:<?= $i * 80 ?>ms">
                    <details class="amenity">
                        <summary class="row-link">
                            <h3 class="flex-1 text-2xl font-medium tracking-tight sm:text-3xl md:text-4xl"><?= $e($item['title']) ?></h3>
                            <p class="max-lg:hidden max-w-sm text-sm text-neutral-600 block"><?= $e($item['summary']) ?></p>
                            <span class="badge-arrow" aria-hidden="true"><svg class="mark"><use href="#i-arrow-ur"/></svg></span>
                        </summary>
                        <div class="amenity-body px-6 pb-8 sm:pl-8">
                            <p class="text-sm text-neutral-600 lg:hidden"><?= $e($item['summary']) ?></p>
                            <ul class="mt-4 grid max-w-3xl gap-3 lg:mt-0">
                                <?php foreach ($item['points'] as $point): ?>
                                <li class="flex gap-3 text-[0.9375rem] leading-relaxed text-neutral-700">
                                    <span class="mt-2.5 h-1.5 w-1.5 flex-none rounded-full bg-accent" aria-hidden="true"></span><?= $e($point) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </details>
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
    <section id="location" class="relative overflow-hidden bg-white">
        <div class="shell relative grid gap-12 py-16 sm:py-20 lg:grid-cols-2 lg:gap-16 lg:py-24">
            <!-- Left Column: Location Identity & Proximity Guide -->
            <div class="flex flex-col gap-6 min-w-0 w-full">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="eyebrow">Find us</p>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            Open for Visits & Inquiries
                        </span>
                    </div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl break-words">
                        <?= $e($map['display_name'] ?? 'RJM Boardinghouse') ?>
                    </h2>
                    <p class="mt-3 text-sm leading-relaxed text-neutral-600 sm:text-base">
                        Situated in Zone 3, Purok Sanctuary, our residence offers a quiet, leafy student sanctuary within easy walking distance of SEAIT campus and Tupi town center.
                    </p>
                </div>

                <!-- Interactive Plus Code & Street Address Card -->
                <div class="rise flex flex-col gap-3 rounded-2xl bg-surface/80 p-3.5 sm:p-4 text-neutral-800 shadow-[inset_0_0_0_1px_var(--color-line)] w-full min-w-0">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="grid h-9 w-9 flex-none place-items-center rounded-xl bg-ink text-white">
                            <svg class="mark text-sm" aria-hidden="true"><use href="#i-pin"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="rounded-md bg-accent/15 px-2 py-0.5 text-xs font-semibold text-accent"><?= $e($map['plus_code'] ?? '8WRP+59H') ?></span>
                                <span class="text-xs text-neutral-500">Google Plus Code</span>
                            </div>
                            <p class="mt-1 text-xs sm:text-sm font-medium text-neutral-800 leading-snug break-words" title="<?= $e($map['address'] ?? '') ?>">
                                <?= $e($map['address'] ?? '') ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 pt-1 border-t border-line/60">
                        <button type="button" class="flex-1 rounded-full bg-white px-3 py-1.5 text-xs font-medium shadow-[0_0_0_1px_var(--color-line)] transition-colors hover:bg-neutral-50 text-center" data-copy-location="<?= $e($map['address'] ?? '') ?>">
                            <span>Copy full address</span>
                        </button>
                        <button type="button" class="flex-1 rounded-full bg-white px-3 py-1.5 text-xs font-medium shadow-[0_0_0_1px_var(--color-line)] transition-colors hover:bg-neutral-50 text-center" data-copy-location="<?= $e($map['plus_code'] ?? '8WRP+59H') ?>">
                            <span>Copy Plus Code</span>
                        </button>
                    </div>
                </div>

                <!-- Transit & Walking Proximity Chips -->
                <div class="rise flex flex-col gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Transit & Walking Proximity</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="https://www.google.com/maps/dir/?api=1&travelmode=walking&origin=6.340433,124.935983&destination=6.3461957,124.9358150" target="_blank" rel="noopener" class="transit-badge" title="Walking route to SEAIT on Google Maps">
                            <span class="text-accent font-semibold">0.6 km</span>
                            <span>SEAIT Campus (3 min walk)</span>
                            <svg class="mark text-[0.65rem] text-neutral-400" aria-hidden="true"><use href="#i-arrow-ur"/></svg>
                        </a>
                        <span class="transit-badge">
                            <span class="text-accent font-semibold">0.6 km</span>
                            <span>Crossing Rubber / Highway</span>
                        </span>
                        <span class="transit-badge">
                            <span class="text-accent font-semibold">1.8 km</span>
                            <span>Tupi Public Market</span>
                        </span>
                    </div>
                </div>

                <!-- Map Action Button -->
                <div class="rise pt-1">
                    <a class="pill-btn pill-dark w-full sm:w-auto justify-center" href="<?= $e($mapsUrl) ?>" target="_blank" rel="noopener">
                        <span>Open in Google Maps</span>
                        <span class="arrow"><svg class="mark"><use href="#i-arrow-ur"/></svg></span>
                    </a>
                </div>
            </div>

            <!-- Right Column: 4 Rich Contact & Access Cards -->
            <div class="rise grid gap-4 self-center sm:grid-cols-2 min-w-0 w-full">
                <!-- Card 1: Office Hours -->
                <div class="location-info-card">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-accent/10 text-accent">
                            <?= \App\Support\Icon::svg('calendar', 20) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-accent">Visiting Schedule</p>
                            <h3 class="text-sm font-semibold text-neutral-900">Office Hours</h3>
                        </div>
                    </div>
                    <div class="mt-1 text-xs sm:text-sm font-medium leading-relaxed text-neutral-700">
                        <?= implode('<br>', array_map($e, explode(' | ', $c['office_hours'] ?? ''))) ?>
                    </div>
                    <p class="text-[0.75rem] text-neutral-500 mt-auto pt-2 border-t border-line/60">Visitor registration at the front desk</p>
                </div>

                <!-- Card 2: Security & Access -->
                <div class="location-info-card">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-accent/10 text-accent">
                            <?= \App\Support\Icon::svg('shield-check', 20) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-accent">Premises</p>
                            <h3 class="text-sm font-semibold text-neutral-900">Security & Access</h3>
                        </div>
                    </div>
                    <div class="mt-1 text-xs sm:text-sm font-medium leading-relaxed text-neutral-700">
                        <?= $e($c['security'] ?? 'Live CCTV · no curfew') ?>
                    </div>
                    <p class="text-[0.75rem] text-neutral-500 mt-auto pt-2 border-t border-line/60">Gated perimeter with on-site staff</p>
                </div>

                <!-- Card 3: Mobile Phone / SMS -->
                <div class="location-info-card">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-accent/10 text-accent">
                            <?= \App\Support\Icon::svg('phone', 20) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-accent">Direct Contact</p>
                            <h3 class="text-sm font-semibold text-neutral-900">Call or Text</h3>
                        </div>
                    </div>
                    <div class="mt-1">
                        <a href="<?= !empty($c['primary_tel']) ? $e($c['primary_tel']) : 'javascript:void(0);' ?>" class="text-sm sm:text-base font-semibold text-neutral-900 hover:text-accent transition-colors block truncate">
                            <?= $e($c['primary_phone'] ?? '') ?>
                        </a>
                    </div>
                    <div class="mt-auto pt-2 border-t border-line/60 flex items-center justify-between">
                        <span class="text-[0.75rem] text-neutral-500">Smart / TNT / Dito</span>
                        <a href="<?= !empty($c['primary_tel']) ? $e($c['primary_tel']) : 'javascript:void(0);' ?>" class="text-xs font-semibold text-accent hover:underline">Tap to call →</a>
                    </div>
                </div>

                <!-- Card 4: Landline Office -->
                <div class="location-info-card">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-accent/10 text-accent">
                            <?= \App\Support\Icon::svg('building', 20) ?>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-accent">Telephone</p>
                            <h3 class="text-sm font-semibold text-neutral-900">Landline Office</h3>
                        </div>
                    </div>
                    <div class="mt-1">
                        <a href="<?= !empty($c['landline_tel']) ? $e($c['landline_tel']) : 'javascript:void(0);' ?>" class="text-sm sm:text-base font-semibold text-neutral-900 hover:text-accent transition-colors block truncate">
                            <?= $e($c['landline'] ?? '') ?>
                        </a>
                    </div>
                    <div class="mt-auto pt-2 border-t border-line/60 flex items-center justify-between">
                        <span class="text-[0.75rem] text-neutral-500">South Cotabato line</span>
                        <a href="<?= !empty($c['landline_tel']) ? $e($c['landline_tel']) : 'javascript:void(0);' ?>" class="text-xs font-semibold text-accent hover:underline">Call office →</a>
                    </div>
                </div>
            </div>
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
                    <li><a class="opacity-75 hover:opacity-100" href="<?= !empty($c['primary_tel']) ? $e($c['primary_tel']) : 'javascript:void(0);' ?>"><?= $e($c['primary_phone'] ?? '') ?></a></li>
                    <li><a class="opacity-75 hover:opacity-100" href="mailto:<?= $e($c['inquiry_email'] ?? '') ?>"><?= $e($c['inquiry_email'] ?? '') ?></a></li>
                </ul>
            </div>
            <div>
                <p class="text-xs text-white/50">Follow</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a class="opacity-75 hover:opacity-100" href="<?= !empty($c['facebook_url']) ? $e($c['facebook_url']) : 'javascript:void(0);' ?>" target="_blank" rel="noopener">Facebook</a></li>
                    <li><a class="opacity-75 hover:opacity-100" href="<?= !empty($c['messenger_url']) ? $e($c['messenger_url']) : 'javascript:void(0);' ?>" target="_blank" rel="noopener">Messenger</a></li>
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
    <!-- w-full: in a flex column, the auto-margin .shell would otherwise shrink to its content -->
    <div class="shell flex w-full items-center justify-between py-5 sm:py-6">
        <span class="flex items-center gap-2 text-lg font-semibold"><svg class="mark text-xl text-[#cf8047]"><use href="#i-mark"/></svg> RJM Boardinghouse</span>
        <button type="button" id="menu-close" class="inline-flex items-center gap-2 rounded-[0.875rem] px-4 py-2 text-xs font-medium text-white/70 shadow-[inset_0_0_0_1px_rgb(255_255_255/.15)] transition-colors hover:text-white hover:shadow-[inset_0_0_0_1px_rgb(255_255_255/.4)]"><svg class="mark text-sm"><use href="#i-x"/></svg> Close</button>
    </div>
    <div class="shell grid w-full flex-1 items-center gap-12 py-8 lg:grid-cols-[1fr_20rem]">
        <nav aria-label="Menu">
            <ul class="flex flex-col">
                <?php foreach ([['#home', 'Home'], ['#rooms', 'Rooms'], ['#amenities', 'Amenities'], ['#location', 'Location']] as $i => [$href, $label]): ?>
                <li>
                    <a href="<?= $href ?>" class="menu-item group flex items-center gap-4 py-1.5 text-5xl font-semibold tracking-tight text-white/60 hover:text-white sm:text-7xl" style="transition-delay:<?= $i * 45 + 80 ?>ms">
                        <span><?= $label ?></span>
                        <svg class="mark -translate-x-3 text-3xl text-[#cf8047] opacity-0 transition duration-300 group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:opacity-100 sm:text-5xl" aria-hidden="true"><use href="#i-arrow"/></svg>
                    </a>
                </li>
                <?php endforeach; ?>
                <li>
                    <button type="button" data-open-inquiry class="menu-item group flex items-center gap-4 py-1.5 text-left text-5xl font-semibold tracking-tight text-white/60 hover:text-white sm:text-7xl" style="transition-delay:260ms">
                        <span>Contact</span>
                        <svg class="mark -translate-x-3 text-3xl text-[#cf8047] opacity-0 transition duration-300 group-hover:translate-x-0 group-hover:opacity-100 group-focus-visible:opacity-100 sm:text-5xl" aria-hidden="true"><use href="#i-arrow"/></svg>
                    </button>
                </li>
            </ul>
        </nav>
        <dl class="menu-item max-lg:hidden gap-6 border-l border-white/10 pl-8 text-sm grid" style="transition-delay:320ms">
            <div><dt class="text-white/50">Call or text</dt><dd class="mt-1"><a class="text-white/85 hover:text-white" href="<?= !empty($c['primary_tel']) ? $e($c['primary_tel']) : 'javascript:void(0);' ?>"><?= $e($c['primary_phone'] ?? '') ?></a></dd></div>
            <div><dt class="text-white/50">Email</dt><dd class="mt-1"><a class="text-white/85 hover:text-white" href="mailto:<?= $e($c['inquiry_email'] ?? '') ?>"><?= $e($c['inquiry_email'] ?? '') ?></a></dd></div>
            <div><dt class="text-white/50">Address</dt><dd class="mt-1 text-white/85"><?= $e(($c['address_line1'] ?? '') . ', ' . ($c['address_line2'] ?? '')) ?></dd></div>
            <div><dt class="text-white/50">Office hours</dt><dd class="mt-1 text-white/85"><?= implode('<br>', array_map($e, explode(' | ', $c['office_hours'] ?? ''))) ?></dd></div>
        </dl>
    </div>
    <div class="shell flex w-full flex-col gap-4 border-t border-white/10 py-6 text-sm text-white/55 sm:flex-row sm:items-center sm:justify-between">
        <span>Local time in Tupi <span class="tabular text-white/85" data-clock-time>—</span></span>
        <div class="flex items-center gap-6">
            <a href="<?= $e($dashboardUrl ?? '/login') ?>" class="text-white/75 hover:text-white"><?= $dashboardUrl ? 'My dashboard' : 'Resident log in' ?></a>
            <button type="button" data-open-inquiry class="pill-btn pill-light">Ask about a room <span class="arrow"><svg class="mark"><use href="#i-arrow-ur"/></svg></span></button>
        </div>
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
                    <div class="field"><label for="inq-phone">Mobile number</label><input id="inq-phone" name="phone" type="tel" inputmode="numeric" class="input" required pattern="09[0-9]{9}" maxlength="11" minlength="11" autocomplete="tel" title="11-digit Philippine mobile number starting with 09 (e.g. 09171234567)" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11)"></div>
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
window.__INITIAL_BLUEPRINT_DATA__ = <?= json_encode($blueprintData ?? ['floors' => ['1', '2'], 'rooms' => []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
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

    // ---- Sticky nav: solid once scrolled, highlight the section in view ----
    const topbar = $('#topbar');
    const onScroll = () => topbar.classList.toggle('is-scrolled', scrollY > 24);
    addEventListener('scroll', onScroll, { passive: true }); onScroll();
    const navLinks = $$('[data-nav-link]');
    const sio = new IntersectionObserver(entries => entries.forEach(en => {
        if (!en.isIntersecting) return;
        navLinks.forEach(a => a.setAttribute('aria-current', String(a.getAttribute('href') === '#' + en.target.id)));
    }), { rootMargin: '-45% 0px -50% 0px' });
    ['home', 'rooms', 'blueprint', 'amenities', 'location'].forEach(id => { const el = document.getElementById(id); if (el) sio.observe(el); });

    // Smooth scrolling & scroll offset for in-page anchors
    $$('#topbar a[href^="#"], #menu a[href^="#"], a[data-nav-link]').forEach(a => {
        a.addEventListener('click', (ev) => {
            const hash = a.getAttribute('href');
            if (!hash || hash === '#' || hash === 'javascript:void(0);') {
                ev.preventDefault();
                ev.stopPropagation();
                return;
            }
            if (hash.startsWith('#')) {
                const target = document.querySelector(hash);
                if (target) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    if (menu.classList.contains('is-open')) setMenu(false);
                    const topbarHeight = topbar ? topbar.offsetHeight + 16 : 70;
                    const targetTop = target.getBoundingClientRect().top + window.scrollY - topbarHeight;
                    window.scrollTo({
                        top: Math.max(0, targetTop),
                        behavior: reduce ? 'instant' : 'smooth'
                    });
                    history.pushState(null, '', hash);
                }
            }
        });
    });

    // Prevent any rogue href="#" links from jumping to the top of the page
    document.addEventListener('click', (ev) => {
        const link = ev.target.closest('a[href="#"], a[href=""]');
        if (link) {
            ev.preventDefault();
            ev.stopPropagation();
        }
    });

    // Prevent clicks inside .nav-float from falling through to underlying elements
    const topbarNav = topbar ? topbar.querySelector('.nav-float') : null;
    if (topbarNav) {
        topbarNav.addEventListener('click', (ev) => {
            ev.stopPropagation();
        });
    }

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
        ['Safety', 'Live CCTV across the whole house.'],
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
    let menuScrollY = 0;
    const setMenu = open => {
        if (open) {
            menuScrollY = window.scrollY || document.documentElement.scrollTop;
            menu.hidden = false;
            requestAnimationFrame(() => menu.classList.add('is-open'));
            document.documentElement.style.overflow = 'hidden';
            $('#menu-close')?.focus({ preventScroll: true });
        } else {
            menu.classList.remove('is-open');
            document.documentElement.style.overflow = '';
            setTimeout(() => { menu.hidden = true; }, 280);
            if (typeof menuScrollY === 'number') {
                window.scrollTo({ top: menuScrollY, behavior: 'instant' });
            }
            openBtn?.focus({ preventScroll: true });
        }
        openBtn.setAttribute('aria-expanded', String(open));
    };
    openBtn.addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        setMenu(true);
    });
    $('#menu-close').addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        setMenu(false);
    });
    $$('#menu a').forEach(a => a.addEventListener('click', (ev) => {
        ev.stopPropagation();
        setMenu(false);
    }));
    document.addEventListener('keydown', ev => {
        if (ev.key === 'Escape' && menu.classList.contains('is-open')) setMenu(false);
    });

    // ---- Inquiry dialog: real POST to /inquire (JSON), success state ----
    const dlg = $('#inquiry'), form = $('#inquiry-form'), err = $('#inquiry-error');
    const formWrap = $('[data-inquiry-form-wrap]'), success = $('[data-inquiry-success]');
    const phoneInput = $('#inq-phone');

    if (phoneInput) {
        phoneInput.addEventListener('keypress', ev => {
            if (!/[0-9]/.test(ev.key) && !ev.ctrlKey && !ev.metaKey && ev.key !== 'Enter') {
                ev.preventDefault();
            }
        });
        phoneInput.addEventListener('input', () => {
            let val = phoneInput.value.replace(/\D/g, '');
            if (val.startsWith('639') && val.length >= 12) {
                val = '0' + val.slice(2);
            }
            phoneInput.value = val.slice(0, 11);
        });
    }

    // ---- Room card 3D flip ----
    $$('[data-flip-trigger]').forEach(btn => {
        btn.addEventListener('click', ev => {
            ev.stopPropagation();
            const flipper = btn.closest('.room-flipper');
            if (!flipper) return;
            const isFlipped = flipper.classList.toggle('is-flipped');
            btn.setAttribute('aria-expanded', String(isFlipped));
            const backFace = flipper.querySelector('.room-face-back');
            if (backFace) backFace.setAttribute('aria-hidden', String(!isFlipped));
            if (isFlipped) {
                const backAction = flipper.querySelector('.room-face-back button');
                if (backAction) backAction.focus();
            }
        });
    });

    $$('[data-flip-back]').forEach(btn => {
        btn.addEventListener('click', ev => {
            ev.stopPropagation();
            const flipper = btn.closest('.room-flipper');
            if (!flipper) return;
            flipper.classList.remove('is-flipped');
            const trigger = flipper.querySelector('[data-flip-trigger]');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            }
            const backFace = flipper.querySelector('.room-face-back');
            if (backFace) backFace.setAttribute('aria-hidden', 'true');
        });
    });

    document.addEventListener('keydown', ev => {
        if (ev.key === 'Escape') {
            const activeFlipper = document.querySelector('.room-flipper.is-flipped:focus-within');
            if (activeFlipper) {
                const backBtn = activeFlipper.querySelector('[data-flip-back]');
                if (backBtn) backBtn.click();
            }
        }
    });

    let inquiryScrollY = 0;

    const openInquiryModal = (roomName) => {
        inquiryScrollY = window.scrollY || document.documentElement.scrollTop;
        if (menu.classList.contains('is-open')) setMenu(false);
        if (roomName) {
            const sel = $('#inq-room');
            if (sel) {
                let opt = [...sel.options].find(o => o.value === roomName || o.value.includes(roomName));
                if (!opt) {
                    opt = new Option(roomName, roomName, true, true);
                    sel.add(opt);
                }
                sel.value = opt.value;
            }
        }
        dlg.showModal();
        // Prevent browser auto-scrolling on showModal
        if (Math.abs((window.scrollY || document.documentElement.scrollTop) - inquiryScrollY) > 2) {
            window.scrollTo({ top: inquiryScrollY, behavior: 'instant' });
        }
        // Focus name field without triggering page scrolling
        $('#inq-name')?.focus({ preventScroll: true });
    };

    $$('[data-open-inquiry]').forEach(b => b.addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        openInquiryModal(b.dataset.room);
    }));

    $$('[data-close-inquiry]').forEach(b => b.addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        dlg.close();
    }));

    dlg.addEventListener('click', ev => {
        if (ev.target === dlg) {
            ev.preventDefault();
            ev.stopPropagation();
            dlg.close();
        }
    });

    dlg.addEventListener('close', () => {
        if (typeof inquiryScrollY === 'number') {
            window.scrollTo({ top: inquiryScrollY, behavior: 'instant' });
        }
        setTimeout(() => {
            if (!success.classList.contains('hidden')) {
                form.reset();
                success.classList.add('hidden');
                success.classList.remove('flex');
                formWrap.classList.remove('hidden');
            }
        }, 300);
    });
    form.addEventListener('submit', async ev => {
        ev.preventDefault();
        err.classList.add('hidden');
        $$('.input', form).forEach(i => i.removeAttribute('aria-invalid'));
        const bad = $$('.input', form).find(i => !i.checkValidity());
        if (bad) {
            bad.setAttribute('aria-invalid', 'true'); bad.focus();
            err.textContent = bad.id === 'inq-phone' ? 'Please enter an 11-digit Philippine mobile number starting with 09 (e.g. 09171234567).' : 'Please fill in your name and mobile number.';
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

    // =========================================================================
    // STYLE A: 2D SVG ARCHITECTURAL BLUEPRINT CONTROLLER (LIVE-SYNCED)
    // =========================================================================
    const initBlueprint = () => {
        const bpSection = $('#building-blueprint-section');
        const card = $('#blueprint-canvas-card');
        const svg = $('#blueprint-svg');
        const viewport = $('#blueprint-viewport');
        const tabsContainer = $('#blueprint-floor-tabs');
        const specBar = $('#blueprint-spec-bar');
        const hudTitle = $('#bp-hud-title');
        const liveIndicator = $('#bp-live-indicator');
        const themeBtn = $('#bp-btn-theme');
        const themeLabel = $('#bp-theme-label');
        const doorsBtn = $('#bp-btn-doors');
        const dimsBtn = $('#bp-btn-dims');
        const zoomInBtn = $('#bp-btn-zoom-in');
        const zoomOutBtn = $('#bp-btn-zoom-out');
        const resetBtn = $('#bp-btn-reset');

        if (!card || !svg || !viewport || !tabsContainer || !specBar) return;

        // Blueprint State
        let state = {
            data: window.__INITIAL_BLUEPRINT_DATA__ || { floors: ['1', '2'], rooms: [] },
            activeFloor: '1',
            selectedRoomId: null,
            doorsVisible: true,
            dimsVisible: true,
            theme: 'native',
            scale: 1,
            panX: 0,
            panY: 0,
            isDragging: false,
            startX: 0,
            startY: 0
        };

        const getFloors = () => ['1', '2']; // the compound has two storeys (north and south blocks)

        const renderTabs = () => {
            const floors = getFloors();
            let html = '';
            floors.forEach(f => {
                const isActive = (state.activeFloor === String(f));
                html += `
                    <button type="button" class="pill-btn !px-3.5 !py-1 text-xs font-semibold tabular transition-colors ${isActive ? '!bg-ink !text-white shadow-sm' : '!bg-transparent !text-neutral-600 hover:!text-ink'}" data-bp-floor="${f}">
                        ${f}F
                    </button>
                `;
            });
            const isAllActive = (state.activeFloor === 'all');
            html += `
                <button type="button" class="pill-btn !px-3.5 !py-1 text-xs font-semibold transition-colors ${isAllActive ? '!bg-ink !text-white shadow-sm' : '!bg-transparent !text-neutral-600 hover:!text-ink'}" data-bp-floor="all">
                    All Floors
                </button>
            `;
            tabsContainer.innerHTML = html;

            tabsContainer.querySelectorAll('[data-bp-floor]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const f = btn.dataset.bpFloor;
                    if (state.activeFloor === f) return;
                    state.activeFloor = f;
                    renderTabs();
                    resetTransform();
                    renderBlueprint();
                });
            });
        };

        const applyTransform = () => {
            const rootLayer = $('#bp-root-layer', svg);
            if (rootLayer) {
                rootLayer.setAttribute('transform', `translate(${state.panX.toFixed(2)}, ${state.panY.toFixed(2)}) scale(${state.scale.toFixed(3)})`);
            }
            if (resetBtn) {
                resetBtn.textContent = `${Math.round(state.scale * 100)}%`;
            }
        };

        const resetTransform = () => {
            state.scale = 1;
            state.panX = 0;
            state.panY = 0;
            applyTransform();
        };

        const zoom = (delta, centerX, centerY) => {
            const oldScale = state.scale;
            let newScale = oldScale * delta;
            newScale = Math.max(0.6, Math.min(3.0, newScale));
            if (newScale === oldScale) return;

            if (centerX !== undefined && centerY !== undefined) {
                const rect = viewport.getBoundingClientRect();
                const mouseX = centerX - rect.left;
                const mouseY = centerY - rect.top;
                state.panX = mouseX - (mouseX - state.panX) * (newScale / oldScale);
                state.panY = mouseY - (mouseY - state.panY) * (newScale / oldScale);
            }
            state.scale = newScale;
            applyTransform();
        };

        const updateHud = () => {
            if (!hudTitle) return;
            if (state.activeFloor === 'all') {
                hudTitle.textContent = 'MULTI-TIER ARCHITECTURAL OVERVIEW';
            } else {
                const f = state.activeFloor;
                const ordinal = f === '1' ? '1ST' : (f === '2' ? '2ND' : (f === '3' ? '3RD' : `${f}TH`));
                hudTitle.textContent = `LEVEL 0${f} · ${ordinal} FLOOR PLAN`;
            }
        };

        const renderSpecBar = () => {
            if (!specBar) return;
            const rooms = (state.data && state.data.rooms) ? state.data.rooms : [];
            const selectedRoom = rooms.find(r => String(r.id) === String(state.selectedRoomId));

            if (!selectedRoom) {
                specBar.innerHTML = `
                    <div class="flex items-center gap-3.5">
                        <div class="h-10 w-10 rounded-xl bg-white/10 flex items-center justify-center text-[#cf8047] flex-none">
                            <svg class="mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white tracking-tight">Select any room on the blueprint</p>
                            <p class="text-xs text-neutral-400">Click or tap a room to see its live status, location and availability.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-neutral-400 font-mono tracking-wider">SITE PLAN · NOT TO SCALE</span>
                    </div>
                `;
                return;
            }

            const slot = SITE_LAYOUT.find(s => s.num === String(selectedRoom.room_number).trim());
            const blockName = slot ? SITE_BLOCKS[slot.block].name : 'Compound';

            let statusBadgeClass = 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30';
            let statusText = 'Vacant';
            let subBadge = 'All Free';
            if (selectedRoom.status === 'occupied') {
                statusBadgeClass = 'bg-neutral-500/15 text-neutral-300 border border-neutral-500/30';
                statusText = 'Occupied';
                subBadge = 'Booked';
            } else if (selectedRoom.status === 'partially_occupied') {
                statusBadgeClass = 'bg-amber-500/15 text-amber-300 border border-amber-500/30';
                statusText = `${selectedRoom.vacant_beds} of ${selectedRoom.total_beds} beds free`;
                subBadge = `${selectedRoom.vacant_beds} Free`;
            }

            const floorNumStr = String(selectedRoom.floor_number || selectedRoom.floor || '1');
            const floorLabel = floorNumStr === '1' ? '1F' : (floorNumStr === '2' ? '2F' : `${floorNumStr}F`);
            const ordinalFloor = floorNumStr === '1' ? 'Level 01' : (floorNumStr === '2' ? 'Level 02' : `Level 0${floorNumStr}`);

            specBar.innerHTML = `
                <div class="flex flex-wrap items-center gap-6 lg:gap-8">
                    <!-- Room Identity -->
                    <div class="flex items-center gap-3.5">
                        <span class="bp-room-badge flex-none grid h-10 w-10 sm:h-11 sm:w-11 place-items-center rounded-xl font-mono font-bold text-base shadow-md">
                            ${selectedRoom.room_number}
                        </span>
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h4 class="font-semibold text-white text-base sm:text-lg tracking-tight leading-tight">Room ${selectedRoom.room_number}</h4>
                                <span class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold tracking-wider ${statusBadgeClass}">
                                    ${statusText}
                                </span>
                            </div>
                            <p class="text-xs text-neutral-400 mt-0.5">${selectedRoom.room_type_name || 'Resident Room'} · ${ordinalFloor} · ${floorLabel}</p>
                        </div>
                    </div>

                    <!-- Architectural Telemetry Pods -->
                    <div class="flex items-center gap-6 sm:gap-8 border-t border-white/10 pt-3 sm:border-t-0 sm:pt-0 sm:border-l sm:pl-6 sm:border-white/15">
                        <div>
                            <span class="block text-[10px] font-mono tracking-wider text-neutral-400 uppercase">Location</span>
                            <span class="block text-xs sm:text-sm font-semibold font-mono text-white tabular mt-0.5 whitespace-nowrap">
                                ${blockName}
                            </span>
                        </div>
                        <div class="border-l border-white/10 pl-6 sm:border-l-0 sm:pl-0">
                            <span class="block text-[10px] font-mono tracking-wider text-neutral-400 uppercase">Capacity</span>
                            <span class="block text-xs sm:text-sm font-semibold font-mono text-white tabular mt-0.5 whitespace-nowrap">
                                ${selectedRoom.capacity_label || (selectedRoom.capacity + ' Beds')} <span class="text-emerald-400 font-normal text-xs">(${subBadge})</span>
                            </span>
                        </div>
                        <div class="border-l border-white/10 pl-6 sm:border-l-0 sm:pl-0">
                            <span class="block text-[10px] font-mono tracking-wider text-neutral-400 uppercase">Monthly Rate</span>
                            <span class="block text-xs sm:text-sm font-bold font-mono text-[#cf8047] tabular mt-0.5 whitespace-nowrap">
                                ₱${Number(selectedRoom.base_price || 0).toLocaleString()} <span class="text-neutral-400 text-xs font-normal font-sans">/ mo</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Action CTA -->
                <div class="flex items-center gap-3">
                    <button type="button" class="pill-btn pill-light !py-1.5 !pl-3.5 !pr-1.5 !gap-2 !text-xs font-semibold whitespace-nowrap" data-open-inquiry="" data-room="Room ${selectedRoom.room_number}">
                        Ask about this room <span class="arrow !w-6 !h-6"><svg class="mark !text-xs"><use href="#i-arrow-ur"></use></svg></span>
                    </button>
                </div>
            `;

            const inquiryBtn = specBar.querySelector('[data-open-inquiry]');
            if (inquiryBtn) {
                inquiryBtn.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    const targetVal = `Room ${selectedRoom.room_number}`;
                    if (typeof openInquiryModal === 'function') {
                        openInquiryModal(targetVal);
                    } else {
                        const sel = $('#inq-room');
                        if (sel) {
                            let opt = [...sel.options].find(o => o.value === targetVal || o.value.includes(selectedRoom.room_type_name));
                            if (!opt) {
                                opt = new Option(`Room ${selectedRoom.room_number} (${selectedRoom.room_type_name || ''})`, targetVal, true, true);
                                sel.add(opt);
                            }
                            sel.value = opt.value;
                        }
                        if (dlg) {
                            const curY = window.scrollY || document.documentElement.scrollTop;
                            dlg.showModal();
                            window.scrollTo({ top: curY, behavior: 'instant' });
                            $('#inq-name')?.focus({ preventScroll: true });
                        }
                    }
                });
            }
        };

        // Site plan of the real compound (owner's sketch, Oct 2026), not to scale. Each slot is matched
        // to a DB room by room_number; a slot with no DB room is drawn greyed out as "Not listed".
        const SITE_BLOCKS = {
            A: { name: 'East wing', x: 912, y: 64, w: 196, h: 616, floors: 1 },
            B: { name: 'North block', x: 390, y: 64, w: 500, h: 124, floors: 2 },
            C: { name: 'West block', x: 90, y: 470, w: 276, h: 140, floors: 1 },
            D: { name: 'South block', x: 396, y: 556, w: 192, h: 124, floors: 2 },
        };
        const SITE_LAYOUT = [
            // East wing: room 1 at the south end up to room 8 at the north, doors onto the courtyard
            ...Array.from({ length: 8 }, (_, i) => ({ num: String(i + 1), floor: '1', block: 'A', x: 912, y: 64 + (7 - i) * 77, w: 196, h: 77, door: 'w', type: 'QUAD' })),
            ...[9, 10, 11, 12].map((n, i) => ({ num: String(n), floor: '1', block: 'B', x: 390 + i * 125, y: 64, w: 125, h: 124, door: 's', type: 'QUAD' })),
            { num: '13', floor: '2', block: 'B', x: 390, y: 64, w: 220, h: 124, door: 's', type: 'SOLO' },
            { num: '14', floor: '2', block: 'B', x: 610, y: 64, w: 280, h: 124, door: 's', type: 'TWIN' },
            { num: '15', floor: '1', block: 'C', x: 90, y: 470, w: 138, h: 140, door: 'n', type: 'QUAD' },
            { num: '16', floor: '1', block: 'C', x: 228, y: 470, w: 138, h: 140, door: 'n', type: 'QUAD' },
            { num: '17', floor: '1', block: 'D', x: 396, y: 556, w: 192, h: 124, door: 'e', type: 'QUAD' },
            { num: '18', floor: '2', block: 'D', x: 396, y: 556, w: 192, h: 124, door: 'e', type: 'TWIN' },
        ];

        const generateFloorSvg = (floorNum, originY = 0, showLevelHeader = false) => {
            const floor = String(floorNum);
            const isUpper = floor !== '1';
            const rooms = (state.data && state.data.rooms) ? state.data.rooms : [];
            const byNum = new Map(rooms.map(r => [String(r.room_number).trim(), r]));
            const Y = (y) => y + originY;
            const mono = 'font-family="monospace"';
            const elements = [];

            if (showLevelHeader) {
                elements.push(`
                    <g class="bp-level-header select-none">
                        <rect x="90" y="${originY + 12}" width="260" height="30" rx="6" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.15)" stroke-width="1"/>
                        <text x="104" y="${originY + 32}" ${mono} font-size="13" font-weight="bold" fill="#cf8047" letter-spacing="1.5">
                            LEVEL 0${floor} · ${floor === '1' ? '1ST' : (floor === '2' ? '2ND' : `${floor}TH`)} FLOOR
                        </text>
                    </g>
                `);
            }

            // Grounds (ghosted on upper floors, where only the north and south blocks have rooms)
            const zoneLabel = (x, y, text) => `<text x="${x}" y="${Y(y)}" ${mono} font-size="11" font-weight="600" fill="var(--bp-text-muted)" text-anchor="middle" letter-spacing="2">${text}</text>`;
            elements.push(`
                <g class="select-none pointer-events-none" aria-hidden="true" opacity="${isUpper ? 0.25 : 1}">
                    <rect x="70" y="${Y(52)}" width="1058" height="640" fill="none" stroke="var(--bp-dim)" stroke-width="1.2" stroke-dasharray="10,6" opacity="0.5"/>
                    <rect x="90" y="${Y(236)}" width="276" height="196" rx="14" fill="var(--bp-corridor-bg)"/>
                    ${zoneLabel(228, 338, 'RESTING AREA')}
                    ${zoneLabel(486, 338, 'OPEN SPACE')}
                    <circle cx="690" cy="${Y(330)}" r="66" fill="rgba(16,185,129,0.12)" stroke="rgba(16,185,129,0.45)" stroke-width="1.5" stroke-dasharray="4,4"/>
                    <circle cx="690" cy="${Y(330)}" r="7" fill="rgba(16,185,129,0.55)"/>
                    ${zoneLabel(690, 418, 'LARGE TREE')}
                    <rect x="396" y="${Y(470)}" width="192" height="86" fill="var(--bp-stair-bg)"/>
                    ${[444, 492, 540].map(x => `<line x1="${x}" y1="${Y(474)}" x2="${x}" y2="${Y(530)}" stroke="var(--bp-dim)" stroke-width="1" opacity="0.5"/>`).join('')}
                    ${zoneLabel(492, 548, 'PARKING')}
                    <rect x="602" y="${Y(470)}" width="276" height="210" rx="14" fill="var(--bp-corridor-bg)"/>
                    ${zoneLabel(740, 580, 'OPEN SPACE')}
                    <rect x="884" y="${Y(64)}" width="28" height="616" fill="var(--bp-stair-bg)"/>
                    <text x="898" y="${Y(372)}" ${mono} font-size="9" fill="var(--bp-dim)" text-anchor="middle" letter-spacing="2" transform="rotate(-90 898 ${Y(372)})">COVERED WALKWAY</text>
                </g>
            `);

            // North block's external stair (concrete stair up to the 2F balcony)
            elements.push(`
                <g class="select-none pointer-events-none" aria-hidden="true">
                    <rect x="587" y="${Y(188)}" width="46" height="62" fill="var(--bp-stair-bg)" stroke="var(--bp-wall-int)" stroke-width="1.5"/>
                    ${[0, 1, 2, 3, 4].map(i => `<line x1="589" y1="${Y(198 + i * 10)}" x2="631" y2="${Y(198 + i * 10)}" stroke="var(--bp-wall-int)" stroke-width="1" opacity="0.5"/>`).join('')}
                    <text x="610" y="${Y(264)}" ${mono} font-size="9" font-weight="bold" fill="#cf8047" text-anchor="middle">${isUpper ? 'DN 1F' : 'UP 2F'}</text>
                </g>
            `);

            const doorAndWindow = (s, x, y, w, h) => {
                const d = Math.min(28, Math.min(w, h) * 0.35);
                const win = Math.min(w, h) * 0.4;
                const leaf = (x1, y1, x2, y2) => `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="var(--bp-door)" stroke-width="2.5"/>`;
                const arc = (path) => `<path d="${path}" fill="none" stroke="var(--bp-door-arc)" stroke-dasharray="3,3" stroke-width="1.5"/>`;
                const pane = (x1, y1, x2, y2) => `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="var(--bp-window)" stroke-width="3.5"/>`;
                let door;
                let windowLine;
                if (s.door === 's') {
                    const px = x + 10, py = y + h;
                    door = leaf(px, py, px, py - d) + arc(`M ${px + d} ${py} A ${d} ${d} 0 0 0 ${px} ${py - d}`);
                    windowLine = pane(x + (w - win) / 2, y, x + (w + win) / 2, y);
                } else if (s.door === 'n') {
                    const px = x + 10, py = y;
                    door = leaf(px, py, px, py + d) + arc(`M ${px + d} ${py} A ${d} ${d} 0 0 1 ${px} ${py + d}`);
                    windowLine = pane(x + (w - win) / 2, y + h, x + (w + win) / 2, y + h);
                } else if (s.door === 'w') {
                    const px = x, py = y + 10;
                    door = leaf(px, py, px + d, py) + arc(`M ${px} ${py + d} A ${d} ${d} 0 0 0 ${px + d} ${py}`);
                    windowLine = pane(x + w, y + (h - win) / 2, x + w, y + (h + win) / 2);
                } else {
                    const px = x + w, py = y + 10;
                    door = leaf(px, py, px - d, py) + arc(`M ${px} ${py + d} A ${d} ${d} 0 0 1 ${px - d} ${py}`);
                    windowLine = pane(x, y + (h - win) / 2, x, y + (h + win) / 2);
                }
                return `<g class="bp-window-group" aria-hidden="true">${windowLine}</g><g class="bp-door-group" aria-hidden="true">${door}</g>`;
            };

            const renderSlot = (s, ghost) => {
                const x = s.x, y = Y(s.y), w = s.w, h = s.h;
                const cx = x + w / 2, cy = y + h / 2;
                const room = ghost ? null : byNum.get(s.num);
                const fs = Math.min(w, h) < 90 ? 13 : 16;
                if (!room) {
                    return `
                        <g class="bp-room-unlisted select-none pointer-events-none" ${ghost ? 'aria-hidden="true"' : `role="img" aria-label="Room ${s.num}, not listed yet"`}>
                            <rect x="${x}" y="${y}" width="${w}" height="${h}" fill="var(--bp-corridor-bg)" stroke="var(--bp-dim)" stroke-width="1.5" stroke-dasharray="5,4" rx="3"/>
                            <text x="${cx}" y="${cy}" ${mono} font-size="${fs}" font-weight="bold" text-anchor="middle" fill="var(--bp-text-muted)">Room ${s.num}</text>
                            ${ghost ? '' : `<text x="${cx}" y="${cy + 15}" ${mono} font-size="9" text-anchor="middle" fill="var(--bp-dim)" letter-spacing="1">${s.type} · NOT LISTED</text>`}
                        </g>
                    `;
                }
                const isSelected = String(room.id) === String(state.selectedRoomId);
                const statusFill = room.status === 'occupied' ? '#94a3b8' : (room.status === 'partially_occupied' ? '#f59e0b' : '#10b981');
                const beds = Number(room.capacity) || 0;
                const free = Number(room.vacant_beds) || 0;
                const total = Number(room.total_beds) || beds;
                const availability = free === 0 ? 'fully occupied' : `${free} of ${total} ${total === 1 ? 'bed' : 'beds'} free`;
                const label = `Room ${room.room_number}, ${SITE_BLOCKS[s.block].name}, ${room.room_type_name || s.type}, ${availability}`
                    .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                return `
                    <g class="bp-room-group" data-room-id="${room.id}">
                        <rect class="bp-room-poly ${isSelected ? 'is-selected' : ''}" data-room-id="${room.id}" data-room-num="${room.room_number}" tabindex="0" role="button" aria-pressed="${isSelected}" aria-label="${label}" x="${x}" y="${y}" width="${w}" height="${h}" fill="var(--bp-room-bg)" stroke="var(--bp-room-stroke)" stroke-width="2" rx="3"/>
                        <circle cx="${x + 14}" cy="${y + 14}" r="3.5" fill="${statusFill}" class="pointer-events-none" aria-hidden="true"/>
                        <text class="bp-room-num font-mono font-bold select-none pointer-events-none" aria-hidden="true" x="${cx}" y="${cy}" text-anchor="middle" font-size="${fs}" fill="var(--bp-text-primary)">Room ${room.room_number}</text>
                        <g class="bp-dimension-group" aria-hidden="true">
                            <text class="bp-room-dim font-mono select-none pointer-events-none" x="${cx}" y="${cy + 15}" text-anchor="middle" font-size="9" font-weight="500" fill="var(--bp-text-muted)">${s.type} · ${beds} ${beds === 1 ? 'BED' : 'BEDS'}</text>
                        </g>
                        ${doorAndWindow(s, x, y, w, h)}
                    </g>
                `;
            };

            // Blocks: rooms on this floor, then the outer wall. One-storey blocks are ghosted on upper floors.
            Object.entries(SITE_BLOCKS).forEach(([key, b]) => {
                const ghost = isUpper && b.floors < 2;
                const slotFloor = ghost ? '1' : floor;
                const slots = SITE_LAYOUT.filter(s => s.block === key && s.floor === slotFloor);
                elements.push(`
                    <g ${ghost ? 'opacity="0.25"' : ''}>
                        ${slots.map(s => renderSlot(s, ghost)).join('')}
                        <rect x="${b.x}" y="${Y(b.y)}" width="${b.w}" height="${b.h}" fill="none" stroke="var(--bp-wall-ext)" stroke-width="6" class="pointer-events-none" aria-hidden="true"/>
                    </g>
                `);
            });

            return elements.join('\n');
        };

        const renderBlueprint = () => {
            updateHud();
            const floors = getFloors();
            let totalHeight = 700;
            let innerSvg = '';

            if (state.activeFloor === 'all') {
                const floorCount = floors.length;
                const plateSpacing = 680;
                totalHeight = Math.max(700, 60 + floorCount * plateSpacing);
                svg.setAttribute('viewBox', `0 0 1160 ${totalHeight}`);

                let plates = [];
                floors.forEach((f, idx) => {
                    const originY = idx * plateSpacing;
                    plates.push(generateFloorSvg(f, originY, true));
                });
                innerSvg = plates.join('\n');
            } else {
                totalHeight = 700;
                svg.setAttribute('viewBox', `0 0 1160 700`);
                innerSvg = generateFloorSvg(state.activeFloor, 0, false);
            }

            svg.setAttribute('aria-label', state.activeFloor === 'all'
                ? 'Site plan of the boarding house, all floors'
                : `Site plan of the boarding house, ${state.activeFloor === '1' ? '1st' : '2nd'} floor`);
            const focusedRoomId = document.activeElement && document.activeElement.classList && document.activeElement.classList.contains('bp-room-poly')
                ? document.activeElement.dataset.roomId : null;

            svg.innerHTML = `
                <defs>
                    <pattern id="bp-grid-pattern" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="var(--bp-bg-grid)" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#bp-grid-pattern)" aria-hidden="true" />
                <g id="bp-root-layer" transform="translate(${state.panX.toFixed(2)}, ${state.panY.toFixed(2)}) scale(${state.scale.toFixed(3)})">
                    ${innerSvg}
                </g>
            `;

            const selectRoom = (poly) => {
                const roomId = poly.dataset.roomId;
                state.selectedRoomId = roomId;

                svg.querySelectorAll('.bp-room-poly').forEach(p => {
                    const isMatch = (p.dataset.roomId === roomId);
                    p.classList.toggle('is-selected', isMatch);
                    p.setAttribute('aria-pressed', String(isMatch));
                });

                renderSpecBar();
            };

            svg.querySelectorAll('.bp-room-poly').forEach(poly => {
                poly.addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    selectRoom(poly);
                });
                poly.addEventListener('keydown', (ev) => {
                    if (ev.key === 'Enter' || ev.key === ' ') {
                        ev.preventDefault();
                        selectRoom(poly);
                    }
                });
            });

            // Live re-renders replace the SVG; keep keyboard focus on the room that had it.
            if (focusedRoomId) {
                const again = svg.querySelector(`.bp-room-poly[data-room-id="${focusedRoomId}"]`);
                if (again) again.focus({ preventScroll: true });
            }

            renderSpecBar();
        };

        // Viewport Pan/Zoom interactions
        viewport.addEventListener('pointerdown', (e) => {
            if (e.target.closest('.bp-room-poly')) return;
            state.isDragging = true;
            state.startX = e.clientX - state.panX;
            state.startY = e.clientY - state.panY;
            try { viewport.setPointerCapture(e.pointerId); } catch (_) {}
        });

        viewport.addEventListener('pointermove', (e) => {
            if (!state.isDragging) return;
            state.panX = e.clientX - state.startX;
            state.panY = e.clientY - state.startY;
            applyTransform();
        });

        const endDrag = (e) => {
            if (state.isDragging) {
                state.isDragging = false;
                try { viewport.releasePointerCapture(e.pointerId); } catch (_) {}
            }
        };

        viewport.addEventListener('pointerup', endDrag);
        viewport.addEventListener('pointercancel', endDrag);

        viewport.addEventListener('wheel', (e) => {
            e.preventDefault();
            const factor = e.deltaY < 0 ? 1.12 : 0.88;
            zoom(factor, e.clientX, e.clientY);
        }, { passive: false });

        viewport.addEventListener('dblclick', (e) => {
            if (e.target.closest('.bp-room-poly')) return;
            resetTransform();
        });

        if (zoomInBtn) zoomInBtn.addEventListener('click', () => zoom(1.25));
        if (zoomOutBtn) zoomOutBtn.addEventListener('click', () => zoom(0.8));
        if (resetBtn) resetBtn.addEventListener('click', resetTransform);

        if (doorsBtn) {
            doorsBtn.addEventListener('click', () => {
                state.doorsVisible = !state.doorsVisible;
                card.classList.toggle('bp-hide-doors', !state.doorsVisible);
                doorsBtn.classList.toggle('is-active', state.doorsVisible);
            });
        }

        if (dimsBtn) {
            dimsBtn.addEventListener('click', () => {
                state.dimsVisible = !state.dimsVisible;
                card.classList.toggle('bp-hide-dims', !state.dimsVisible);
                dimsBtn.classList.toggle('is-active', state.dimsVisible);
            });
        }

        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                state.theme = (state.theme === 'native') ? 'cad' : 'native';
                const isCad = (state.theme === 'cad');
                card.classList.toggle('theme-cad', isCad);
                card.classList.toggle('theme-native', !isCad);
                if (themeLabel) themeLabel.textContent = isCad ? 'Native Slate' : 'CAD Mode';
            });
        }

        // Live Background Polling (every 20 seconds)
        const fetchLiveRooms = async () => {
            try {
                const res = await fetch('/api/blueprint/rooms', { cache: 'no-store' });
                if (!res.ok) return;
                const fresh = await res.json();
                if (!fresh || !fresh.rooms) return;

                const currentStr = JSON.stringify(state.data.rooms);
                const freshStr = JSON.stringify(fresh.rooms);
                if (currentStr !== freshStr) {
                    state.data = fresh;
                    renderTabs();
                    renderBlueprint();

                    if (liveIndicator) {
                        liveIndicator.classList.add('scale-110');
                        setTimeout(() => liveIndicator.classList.remove('scale-110'), 500);
                    }
                }
            } catch (_) {}
        };

        setInterval(fetchLiveRooms, 20000);

        // Initial execution
        renderTabs();
        renderBlueprint();
    };

    // Initialize blueprint
    initBlueprint();


    // ---- House section media view switcher (Photo / Live Map) ----
    const viewPhoto = $('#house-view-photo');
    const viewMap = $('#house-view-map');
    const viewBtns = $$('[data-house-view]');

    viewBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.houseView;
            if (target === 'photo') {
                if (viewPhoto) viewPhoto.hidden = false;
                if (viewMap) viewMap.hidden = true;
            } else {
                if (viewPhoto) viewPhoto.hidden = true;
                if (viewMap) viewMap.hidden = false;
            }
            viewBtns.forEach(b => {
                const isActive = b === btn;
                b.classList.toggle('bg-white', isActive);
                b.classList.toggle('text-ink', isActive);
                b.classList.toggle('shadow-sm', isActive);
                b.classList.toggle('font-semibold', isActive);
                b.classList.toggle('opacity-70', !isActive);
            });
        });
    });

    // ---- Copy Address & Location Handler ----
    const copyTriggers = $$('[data-copy-address], [data-copy-location]');
    copyTriggers.forEach(btn => {
        btn.addEventListener('click', async () => {
            const val = btn.dataset.copyLocation || btn.dataset.copyAddress;
            const span = btn.querySelector('span');
            try {
                await navigator.clipboard.writeText(val);
                if (span) {
                    const orig = span.textContent;
                    span.textContent = 'Copied!';
                    setTimeout(() => { span.textContent = orig; }, 2000);
                }
            } catch (err) {
                const ta = document.createElement('textarea');
                ta.value = val;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                if (span) {
                    const orig = span.textContent;
                    span.textContent = 'Copied!';
                    setTimeout(() => { span.textContent = orig; }, 2000);
                }
            }
        });
    });

    // ---- Liquid reveal: the cursor paints a night duotone of the hero photo ----
    const canvas = $('#reveal'), hero = $('#home'), img = $('#home .base');
    if (!canvas || !img || reduce || !matchMedia('(pointer: fine)').matches) { if (canvas) canvas.remove(); return; }
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
