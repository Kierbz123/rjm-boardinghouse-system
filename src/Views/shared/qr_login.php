<?php
$pageTitle = 'QR log in — RJM Boardinghouse';
$error = $error ?? null;
$status = $status ?? 'invalid';
$token = $token ?? '';
$loggedInUser = $loggedInUser ?? null;
$lastEmail = $lastEmail ?? '';
$canApprove = $status === 'pending';
ob_start();
?>
<style>
.landing-bg-container { position: fixed; inset: 0; overflow: hidden; z-index: 0; }
.landing-bg-image {
    position: absolute; inset: -5%;
    background-image: url('/assets/images/landing-bg.jpg');
    background-size: cover; background-position: center;
}
.landing-bg-overlay {
    position: absolute; inset: 0;
    background: radial-gradient(circle at center, rgba(15, 23, 42, 0.55) 0%, rgba(15, 23, 42, 0.85) 100%);
}
.modal-halo-container {
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    padding: 10px;
    border-radius: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.35);
    box-shadow: 0 35px 70px -15px rgba(0, 0, 0, 0.5);
    width: 100%;
    max-width: 28rem;
}
.modal-inner-card { background: #ffffff; border-radius: 1.625rem; padding: 2rem 1.75rem; }
.ref-input {
    width: 100%; height: 2.625rem; border: 1px solid #d1d5db; border-radius: 0.5rem;
    padding: 0 0.875rem; font-size: 0.875rem; color: #111827; background: #ffffff;
}
.ref-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }
.btn-ref-primary {
    width: 100%; height: 2.75rem; background: #1d4ed8; color: #ffffff; font-size: 0.875rem;
    font-weight: 600; border-radius: 0.5rem; border: none; cursor: pointer;
}
.btn-ref-primary:hover { background: #1e40af; }
.qr-status-card {
    border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem; background: #f8fafc;
    text-align: center; font-size: 0.875rem; color: #475569;
}
</style>

<div class="landing-bg-container" aria-hidden="true">
    <div class="landing-bg-image"></div>
    <div class="landing-bg-overlay"></div>
</div>

<div class="relative z-10 min-h-screen flex items-center justify-center p-4">
    <div class="modal-halo-container">
        <div class="modal-inner-card">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-6 h-6 rounded-md bg-amber-100 text-amber-700 flex items-center justify-center text-xs font-bold">▲</span>
                <span class="text-xs font-bold tracking-tight text-neutral-800 uppercase">RJM Boardinghouse</span>
            </div>
            <h1 class="text-2xl font-extrabold text-neutral-900 tracking-tight">Log in with QR code</h1>
            <p class="text-neutral-500 text-sm mt-1.5 mb-5">Confirm this sign-in on the computer that showed the code.</p>

            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg px-3.5 py-2.5 mb-4" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($status === 'invalid'): ?>
                <div class="qr-status-card">This QR code is not valid. Open the login page on the computer and scan a new one.</div>
            <?php elseif ($status === 'expired'): ?>
                <div class="qr-status-card">This QR code expired. Generate a new one on the computer, then scan again.</div>
            <?php elseif ($status === 'consumed'): ?>
                <div class="qr-status-card">This computer is signed in. You can close this page.</div>
                <?php if (!empty($loggedInUser)): ?>
                    <a href="<?= htmlspecialchars(match ($loggedInUser['role'] ?? '') {
                        'admin' => '/admin/dashboard',
                        'staff' => '/staff/dashboard',
                        'boarder' => '/portal/dashboard',
                        default => '/login',
                    }) ?>" class="btn-ref-primary mt-4 inline-flex items-center justify-center no-underline">Continue</a>
                <?php endif; ?>
            <?php elseif ($status === 'approved'): ?>
                <div class="qr-status-card">Approved. The computer is signing in now — you can close this page.</div>
                <?php if (!empty($loggedInUser)): ?>
                    <a href="<?= htmlspecialchars(match ($loggedInUser['role'] ?? '') {
                        'admin' => '/admin/dashboard',
                        'staff' => '/staff/dashboard',
                        'boarder' => '/portal/dashboard',
                        default => '/login',
                    }) ?>" class="btn-ref-primary mt-4 inline-flex items-center justify-center no-underline">Open this device</a>
                <?php endif; ?>
            <?php elseif ($canApprove && $loggedInUser): ?>
                <form method="post" action="/qr/<?= htmlspecialchars($token) ?>" class="space-y-4">
                    <?= \App\Support\Csrf::field() ?>
                    <div class="qr-status-card" style="text-align:left;">
                        Continue as <strong><?= htmlspecialchars($loggedInUser['name']) ?></strong>
                        <div class="text-neutral-400 text-xs mt-1"><?= htmlspecialchars($loggedInUser['email']) ?> · <?= htmlspecialchars($loggedInUser['role']) ?></div>
                    </div>
                    <button type="submit" class="btn-ref-primary">Log in this computer</button>
                </form>
            <?php elseif ($canApprove): ?>
                <form method="post" action="/qr/<?= htmlspecialchars($token) ?>" class="space-y-4" autocomplete="on">
                    <?= \App\Support\Csrf::field() ?>
                    <div>
                        <label for="qr-email" class="sr-only">Email</label>
                        <input id="qr-email" type="email" name="email" required autofocus autocomplete="email"
                               value="<?= htmlspecialchars($lastEmail) ?>" placeholder="Email" class="ref-input">
                    </div>
                    <div>
                        <label for="qr-password" class="sr-only">Password</label>
                        <input id="qr-password" type="password" name="password" required autocomplete="current-password"
                               placeholder="Password" class="ref-input">
                    </div>
                    <button type="submit" class="btn-ref-primary">Log in this computer</button>
                </form>
            <?php endif; ?>

            <p class="text-center text-xs text-neutral-400 mt-5">
                <a href="/login" class="text-blue-600 font-medium">Back to email log in</a>
            </p>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
