<?php
/**
 * Shared layout shell. Tailwind + GSAP are vendored locally under
 * public/assets/{css,js} — compiled/downloaded once ahead of time, not
 * fetched at runtime, so the running app makes no outbound calls (see
 * CLAUDE.md). No Node/build step is needed to *run* the app; regenerating
 * app.css after template changes is documented in UI-LIBRARY-EVALUATION.md.
 * Every role-specific view inherits this <head> — don't duplicate it per role.
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($pageTitle ?? 'RJM Boardinghouse') ?></title>

    <!-- Tailwind CSS v4 — compiled once via the standalone CLI -->
    <link rel="stylesheet" href="/assets/css/app.css" />

    <!-- GSAP — vendored locally -->
    <script src="/assets/js/vendor/gsap.min.js"></script>
    <script src="/assets/js/vendor/ScrollTrigger.min.js"></script>
    <script>if (window.gsap && window.ScrollTrigger) { gsap.registerPlugin(ScrollTrigger); }</script>

    <!-- QRCode.js — vendored locally -->
    <script src="/assets/js/vendor/qrcode.min.js"></script>

    <style>
        /* ===== UI SYSTEM HELPERS ===== */
        /* Danger button */
        .btn-danger {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            cursor: pointer;
            transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
            white-space: nowrap;
        }
        .btn-danger:hover {
            background-color: #fee2e2;
            border-color: #f87171;
            color: #991b1b;
        }

        /* Metric card left-border accents */
        .metric-accent-primary { border-left: 3px solid #2563eb; }
        .metric-accent-success { border-left: 3px solid #16a34a; }
        .metric-accent-warning { border-left: 3px solid #d97706; }
        .metric-accent-error   { border-left: 3px solid #dc2626; }
        .metric-accent-info    { border-left: 3px solid #0284c7; }

        /* Responsive grid helpers */
        .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        @media (min-width: 768px) {
            .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .md\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .md\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
        }

        /* Container & Spacing Helpers */
        .max-w-5xl { max-width: 64rem; }
        .mx-auto   { margin-left: auto; margin-right: auto; }
        .px-5      { padding-left: 1.25rem; padding-right: 1.25rem; }
        .py-3      { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        .py-5      { padding-top: 1.25rem; padding-bottom: 1.25rem; }
        .py-6      { padding-top: 1.5rem; padding-bottom: 1.5rem; }
        .gap-3     { gap: 0.75rem; }
        .gap-4     { gap: 1rem; }
        .gap-5     { gap: 1.25rem; }
        .gap-6     { gap: 1.5rem; }
        :where(.space-y-5 > :not(:last-child)) { margin-bottom: 1.25rem; }
        :where(.space-y-6 > :not(:last-child)) { margin-bottom: 1.5rem; }
        .space-y-5 > * + * { margin-top: 1.25rem; }
        .space-y-6 > * + * { margin-top: 1.5rem; }
        .sticky    { position: sticky; }
        .top-0     { top: 0; }
        .z-50      { z-index: 50; }
        .shadow-sm { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
        .hidden    { display: none !important; }

        /* Table row hover */
        tbody tr:hover { background-color: #f8fafc; transition: background 120ms; }
        tbody tr { transition: background 120ms; }

        /* Section header divider */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 0.5rem;
            border-bottom: 1.5px solid #e2e8f0;
            margin-bottom: 0.875rem;
        }
        .section-header h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        /* Page header banner */
        .page-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 0.75rem;
            padding: 1.25rem 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .page-banner h1 { color: white; margin: 0; line-height: 1.2; }
        .page-banner p  { color: #94a3b8; margin: 0.2rem 0 0; font-size: 0.8125rem; }

        /* Form card visual cue */
        .form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #2563eb;
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.08);
            padding: 1.125rem 1.25rem 1.375rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .form-card.accent-success { border-top-color: #16a34a; }
        .form-card.accent-neutral  { border-top-color: #64748b; }

        /* KPI metric card */
        .metric-card-inner { padding: 1rem 1.125rem; }
        .metric-card-inner .metric-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: #64748b; }
        .metric-card-inner .metric-value { font-size: 1.875rem; font-weight: 800; line-height: 1; margin-top: 0.5rem; color: #0f172a; }
        .metric-card-inner .metric-sub   { font-size: 0.7rem; margin-top: 0.375rem; color: #94a3b8; }

        /* Table cells */
        table th { padding: 0.625rem 0.875rem !important; }
        table td { padding: 0.625rem 0.875rem !important; vertical-align: middle; }

        /* Mono badge for IDs */
        .id-tag {
            font-family: ui-monospace, monospace;
            font-size: 0.7rem;
            background: #f1f5f9;
            color: #475569;
            border-radius: 0.375rem;
            padding: 0.1rem 0.45rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        /* Inline action cluster */
        .action-cluster { display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; }

        /* Page content outer padding */
        main { padding-bottom: 2.5rem; }

        /* Safety net: guarantee reveal-card/form-card are visible even if GSAP stalls */
        @keyframes safeReveal { to { opacity: 1; transform: none; } }
        .reveal-card, .form-card { animation: safeReveal 0s 0.6s forwards; }
    </style>
</head>
<body class="bg-neutral-50 text-neutral-900 min-h-screen">
    <?php if (session_status() !== PHP_SESSION_NONE): require __DIR__ . '/nav.php'; endif; ?>
    <main>
        <?= $content ?? '' ?>
    </main>
    <script src="/assets/js/app.js"></script>
</body>
</html>
