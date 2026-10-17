<?php
/**
 * Shared layout shell. Tailwind + GSAP are vendored locally under
 * public/assets/{css,js} — compiled/downloaded once ahead of time, not
 * fetched at runtime, so the running app makes no outbound calls (see
 * CLAUDE.md). No Node/build step is needed to *run* the app; regenerating
 * app.css after template changes is documented in UI-LIBRARY-EVALUATION.md.
 * Every role-specific view inherits this <head> — don't duplicate it per role.
 * Asset URLs carry ?v=<file time>: the router lets the browser keep versioned files, and a rebuilt
 * app.css gets a new URL, so pages switch fast and never show stale styles.
 */
$asset = fn (string $path): string => $path . '?v=' . (int) @filemtime(__DIR__ . '/../../../public' . $path);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Support\Csrf::token()) ?>" />
    <title><?= htmlspecialchars($pageTitle ?? 'RJM Boardinghouse') ?></title>

    <!-- Tailwind CSS v4 — compiled once via the standalone CLI -->
    <link rel="stylesheet" href="<?= $asset('/assets/css/app.css') ?>" />
    <link rel="preload" href="/assets/fonts/onest-latin.woff2" as="font" type="font/woff2" crossorigin />

    <!-- GSAP — vendored locally -->
    <script src="<?= $asset('/assets/js/vendor/gsap.min.js') ?>"></script>
    <script>
    // Cards rise in once per page load (.page-enter in input.css). The class comes off afterwards
    // so cards shown again later (filters, toggled forms) do not replay the entrance.
    document.documentElement.classList.add('page-enter');
    document.addEventListener('DOMContentLoaded', () => setTimeout(() => document.documentElement.classList.remove('page-enter'), 1000));
    </script>

    <?php if (!empty($needsQrCode)): ?><script src="/assets/js/vendor/qrcode.min.js"></script><?php endif; ?>

</head>
<body class="bg-neutral-50 text-neutral-900 min-h-screen" data-user-role="<?= htmlspecialchars($_SESSION['role'] ?? '') ?>">
    <?php if (session_status() !== PHP_SESSION_NONE): require __DIR__ . '/nav.php'; endif; ?>
    <main id="app-main-content" class="min-w-0 transition-all duration-200">
        <?php
        // Views that show their own messages have already consumed them; anything left
        // (e.g. a redirect to a page without its own banner) is shown here once, as a toast.
        $toasts = [];
        foreach (['flash_error' => 'toast-error', 'flash_success' => 'toast-success', 'flash_info' => 'toast-info'] as $flashKey => $toastClass) {
            if (!empty($_SESSION[$flashKey])) {
                $toasts[] = [$toastClass, (string) $_SESSION[$flashKey]];
            }
            unset($_SESSION[$flashKey]);
        }
        if ($toasts): ?>
        <div class="toast-stack" aria-live="polite">
            <?php foreach ($toasts as [$toastClass, $toastText]): ?>
            <div class="toast <?= $toastClass ?>" role="<?= $toastClass === 'toast-error' ? 'alert' : 'status' ?>">
                <span><?= htmlspecialchars($toastText) ?></span>
                <button type="button" aria-label="Dismiss" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endforeach; ?>
        </div>
        <script>
        document.querySelectorAll('.toast:not(.toast-error)').forEach(t => setTimeout(() => {
            t.classList.add('is-leaving');
            t.addEventListener('animationend', () => t.remove(), { once: true });
        }, 6000));
        </script>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>
    <?php if (!empty($_SESSION['role'])): // the assistant's type-ahead: only pages this role may open ?>
    <script type="application/json" id="assistant-pages"><?= json_encode(array_map(
        fn ($page) => ['href' => $page['href'], 'label' => $page['label'], 'words' => $page['words'], 'about' => $page['about']],
        \App\Support\NavRegistry::forRole($_SESSION['role'])
    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <script src="<?= $asset('/assets/js/app.js') ?>"></script>
    <script src="<?= $asset('/assets/js/ai-assistant.js') ?>"></script>
</body>
</html>
