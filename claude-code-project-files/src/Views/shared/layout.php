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
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Support\Csrf::token()) ?>" />
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
        .grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .grid-cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }

        @media (min-width: 640px) {
            .sm\:grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)) !important; }
            .sm\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .sm\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .sm\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
            .sm\:col-span-1  { grid-column: span 1 / span 1 !important; }
            .sm\:col-span-2  { grid-column: span 2 / span 2 !important; }
        }

        @media (min-width: 768px) {
            .md\:grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)) !important; }
            .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .md\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .md\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
            .md\:grid-cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)) !important; }
            .md\:col-span-1  { grid-column: span 1 / span 1 !important; }
            .md\:col-span-2  { grid-column: span 2 / span 2 !important; }
            .md\:col-span-3  { grid-column: span 3 / span 3 !important; }
        }

        @media (min-width: 1024px) {
            .lg\:grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)) !important; }
            .lg\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .lg\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .lg\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
            .lg\:grid-cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)) !important; }
            .lg\:col-span-1  { grid-column: span 1 / span 1 !important; }
            .lg\:col-span-2  { grid-column: span 2 / span 2 !important; }
            .lg\:col-span-3  { grid-column: span 3 / span 3 !important; }
        }

        .col-span-1 { grid-column: span 1 / span 1; }
        .col-span-2 { grid-column: span 2 / span 2; }
        .col-span-3 { grid-column: span 3 / span 3; }
        .col-span-4 { grid-column: span 4 / span 4; }

        /* Container & Spacing Helpers — upgraded to match 82rem navbar across all views */
        .max-w-5xl { max-width: 82rem; }
        .max-w-6xl { max-width: 82rem; }
        .max-w-7xl { max-width: 82rem; }
        .page-container {
            max-width: 82rem;
            margin-left: auto;
            margin-right: auto;
            padding: 1.5rem 1rem 3.5rem;
        }
        @media (min-width: 640px) {
            .page-container { padding: 1.75rem 1.5rem 3.5rem; }
        }
        @media (min-width: 1024px) {
            .page-container { padding: 2rem 2rem 4rem; }
        }

        .mx-auto   { margin-left: auto; margin-right: auto; }
        .px-4      { padding-left: 1rem; padding-right: 1rem; }
        .px-5      { padding-left: 1.25rem; padding-right: 1.25rem; }
        .px-6      { padding-left: 1.5rem; padding-right: 1.5rem; }
        .py-3      { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        .py-5      { padding-top: 1.25rem; padding-bottom: 1.25rem; }
        .py-6      { padding-top: 1.5rem; padding-bottom: 1.5rem; }
        .gap-3     { gap: 0.75rem; }
        .gap-4     { gap: 1rem; }
        .gap-5     { gap: 1.25rem; }
        .gap-6     { gap: 1.5rem; }
        :where(.space-y-4 > :not(:last-child)) { margin-bottom: 1rem; }
        :where(.space-y-5 > :not(:last-child)) { margin-bottom: 1.25rem; }
        :where(.space-y-6 > :not(:last-child)) { margin-bottom: 1.5rem; }
        .space-y-4 > * + * { margin-top: 1rem; }
        .space-y-5 > * + * { margin-top: 1.25rem; }
        .space-y-6 > * + * { margin-top: 1.5rem; }
        .sticky    { position: sticky; }
        .top-0     { top: 0; }
        .z-50      { z-index: 50; }
        .shadow-sm { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); }
        .shadow-xs { box-shadow: 0 1px 2px 0 rgb(15 23 42 / 0.05); }
        .hidden    { display: none !important; }

        /* Search input padding utilities for icon clearance */
        .pl-8 { padding-left: 2rem !important; }
        .pr-8 { padding-right: 2rem !important; }
        .pl-9 { padding-left: 2.25rem !important; }
        .pr-9 { padding-right: 2.25rem !important; }
        .pl-10 { padding-left: 2.5rem !important; }
        .pr-10 { padding-right: 2.5rem !important; }

        /* Table styling — clean, readable, scroll-safe */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            padding: 0.75rem 1rem !important;
            font-size: 0.6875rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            color: #64748b !important;
            background: #f8fafc;
            vertical-align: middle;
            text-align: left;
        }
        table td {
            padding: 0.75rem 1rem !important;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        tbody tr:hover { background-color: #f8fafc; transition: background 120ms; }
        tbody tr { transition: background 120ms; }

        /* Section header divider */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 0.65rem;
            border-bottom: 1.5px solid #e2e8f0;
            margin-bottom: 1rem;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .section-header h2 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        /* Page header banner */
        .page-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 0.875rem;
            padding: 1.35rem 1.75rem;
            color: white;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 1.5rem;
        }
        @media (min-width: 640px) {
            .page-banner {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }
        .page-banner h1 {
            color: white !important;
            margin: 0;
            line-height: 1.25;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .page-banner p {
            color: #94a3b8;
            margin: 0.25rem 0 0;
            font-size: 0.8125rem;
        }

        /* Form card visual cue */
        .form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #2563eb;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgb(15 23 42 / 0.05);
            padding: 1.25rem 1.25rem 1.35rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            height: 100%;
            box-sizing: border-box;
        }
        .form-card button[type="submit"],
        .form-card .btn:last-child {
            margin-top: auto;
        }
        .form-card.accent-success { border-top-color: #16a34a; }
        .form-card.accent-neutral  { border-top-color: #64748b; }

        /* KPI metric card */
        .metric-card-inner { padding: 1rem 1.125rem; }
        .metric-card-inner .metric-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: #64748b; }
        .metric-card-inner .metric-value { font-size: 1.875rem; font-weight: 800; line-height: 1.1; margin-top: 0.35rem; color: #0f172a; font-variant-numeric: tabular-nums; }
        .metric-card-inner .metric-sub   { font-size: 0.75rem; margin-top: 0.35rem; color: #94a3b8; }

        /* Fix table container overflow */
        .overflow-x-auto {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
        }

        /* Fix button container overflow */
        .flex.justify-end {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.5rem;
        }
        .flex.justify-end > * {
            flex-shrink: 0;
        }

        /* Mono badge for IDs */
        .id-tag {
            font-family: ui-monospace, monospace;
            font-size: 0.7rem;
            background: #f1f5f9;
            color: #475569;
            border-radius: 0.375rem;
            padding: 0.15rem 0.5rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            display: inline-block;
        }

        /* Incident quick suggestion chips */
        .incident-chip {
            font-size: 0.65rem !important;
            line-height: 1 !important;
            padding: 0.2rem 0.55rem !important;
            border-radius: 9999px !important;
            font-weight: 500 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.25rem !important;
            cursor: pointer !important;
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            border: 1px solid #cbd5e1 !important;
            transition: all 120ms ease !important;
            white-space: nowrap !important;
        }
        .incident-chip:hover {
            background-color: #eff6ff !important;
            color: #1d4ed8 !important;
            border-color: #93c5fd !important;
        }

        /* Compact table utility */
        .table-compact th {
            font-size: 0.65rem !important;
            letter-spacing: 0.04em !important;
            padding: 0.45rem 0.75rem !important;
            text-transform: uppercase !important;
        }
        .table-compact td {
            font-size: 0.75rem !important;
            padding: 0.5rem 0.75rem !important;
            line-height: 1.4 !important;
        }

        /* Inline action cluster — clean, non-wrapping horizontal row */
        .action-cluster {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        /* Page content outer padding */
        main { padding-bottom: 3.5rem; }

        /* Fix input overflow issues */
        input, select, textarea {
            box-sizing: border-box;
            max-width: 100%;
        }
        .input {
            box-sizing: border-box;
            max-width: 100%;
            width: 100%;
        }

        /* Cards and hover effects */
        .card {
            border-radius: 0.75rem;
            transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .card:hover {
            box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.08);
        }

        /* Headings: wrap cleanly, never truncate unintentionally */
        h1, h2, h3, h4, h5, h6 {
            overflow-wrap: break-word;
            text-overflow: clip;
            white-space: normal;
        }

        /* Fix button interactions */
        .btn {
            transition: all 0.15s ease;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        .btn:hover {
            transform: translateY(-1px);
        }
        .btn:active {
            transform: translateY(0);
        }

        /* Fix badge overflow */
        .badge {
            flex-shrink: 0;
            white-space: nowrap;
        }

        /* Safety net: guarantee reveal-card/form-card are visible even if GSAP stalls */
        @keyframes safeReveal { to { opacity: 1; transform: none; } }
        .reveal-card, .form-card { animation: safeReveal 0s 0.6s forwards; }

        /* ===== AI ASSISTANT SYSTEM STYLES ===== */
        .ai-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            transition: all 0.2s ease;
        }
        .ai-status-online {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .ai-status-offline {
            background: rgba(148, 163, 184, 0.15);
            color: #94a3b8;
            border: 1px solid rgba(148, 163, 184, 0.25);
        }
        .ai-status-checking {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .ai-status-dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 50%;
            display: inline-block;
        }
        .ai-status-online .ai-status-dot { background: #10b981; box-shadow: 0 0 6px #10b981; }
        .ai-status-offline .ai-status-dot { background: #94a3b8; }
        .ai-status-checking .ai-status-dot { background: #f59e0b; animation: pulseDot 1s infinite; }
        @keyframes pulseDot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(0.8); } }

        /* AI Action Buttons */
        .ai-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.725rem;
            font-weight: 600;
            padding: 0.28rem 0.65rem;
            border-radius: 0.5rem;
            background: #f5f3ff;
            color: #6d28d9;
            border: 1px solid #ddd6fe;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(109, 40, 217, 0.05);
        }
        .ai-action-btn:hover:not(:disabled) {
            background: #ede9fe;
            border-color: #c4b5fd;
            color: #5b21b6;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(109, 40, 217, 0.12);
        }
        .ai-action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        .ai-action-btn.ai-loading {
            position: relative;
            pointer-events: none;
            color: #7c3aed;
        }

        /* Priority Live Preview Card */
        .priority-preview-card {
            background: linear-gradient(145deg, #ffffff 0%, #fbfbfe 100%);
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.875rem 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .priority-preview-card.tier-critical { border-left: 4px solid #dc2626; }
        .priority-preview-card.tier-high     { border-left: 4px solid #f59e0b; }
        .priority-preview-card.tier-medium   { border-left: 4px solid #0ea5e9; }
        .priority-preview-card.tier-low      { border-left: 4px solid #64748b; }

        /* Floating AI Chat Launcher FAB */
        #ai-chat-fab {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 990;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.65rem 1.1rem;
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(49, 46, 129, 0.35), 0 2px 4px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        #ai-chat-fab:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 6px 20px rgba(49, 46, 129, 0.45);
        }

        /* Floating AI Chat Window */
        #ai-chat-window {
            position: fixed;
            bottom: 4.75rem;
            right: 1.5rem;
            width: 380px;
            max-width: calc(100vw - 2rem);
            height: 520px;
            max-height: calc(100vh - 6rem);
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: 0 12px 36px -4px rgba(15, 23, 42, 0.2), 0 4px 12px rgba(15, 23, 42, 0.08);
            z-index: 995;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform-origin: bottom right;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        #ai-chat-window.hidden {
            display: none !important;
        }

        .ai-chat-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            color: #ffffff;
            padding: 0.875rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ai-chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            background: #f8fafc;
        }
        .ai-bubble {
            max-width: 84%;
            padding: 0.65rem 0.85rem;
            border-radius: 0.875rem;
            font-size: 0.8125rem;
            line-height: 1.45;
            word-break: break-word;
        }
        .ai-bubble-user {
            align-self: flex-end;
            background: #312e81;
            color: #ffffff;
            border-bottom-right-radius: 0.2rem;
        }
        .ai-bubble-assistant {
            align-self: flex-start;
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 0.2rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }
        .ai-chat-input-area {
            padding: 0.75rem;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
        }
        .ai-quick-chips {
            display: flex;
            gap: 0.35rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            scrollbar-width: none;
        }
        .ai-quick-chip {
            white-space: nowrap;
            font-size: 0.7rem;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .ai-quick-chip:hover {
            background: #ede9fe;
            color: #6d28d9;
            border-color: #ddd6fe;
        }

        /* AI Analysis Card in Queue */
        .ai-analysis-box {
            background: linear-gradient(145deg, #fbfbfe 0%, #f5f3ff 100%);
            border: 1px solid #e0e7ff;
            border-left: 4px solid #6366f1;
            border-radius: 0.625rem;
            padding: 0.875rem 1rem;
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="bg-neutral-50 text-neutral-900 min-h-screen" data-user-role="<?= htmlspecialchars($_SESSION['role'] ?? '') ?>">
    <?php if (session_status() !== PHP_SESSION_NONE): require __DIR__ . '/nav.php'; endif; ?>
    <main id="app-main-content" class="min-w-0 transition-all duration-200">
        <?= $content ?? '' ?>
    </main>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/ai-assistant.js"></script>
</body>
</html>
