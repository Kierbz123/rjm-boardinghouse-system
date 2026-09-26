<?php
$pageTitle = 'Profile & Account Settings';
ob_start();

$error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$user = $user ?? [];
$role = $user['role'] ?? ($_SESSION['role'] ?? 'boarder');
$boarderProfile = $boarderProfile ?? [];
$stats = $stats ?? [];
?>
<div class="max-w-5xl mx-auto px-5 py-5 space-y-6">

    <!-- Page Banner -->
    <div class="page-banner" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 1rem; padding: 1.5rem 1.75rem; box-shadow: 0 4px 16px rgba(15,23,42,0.15);">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400 bg-emerald-950/60 border border-emerald-700/50 px-2 py-0.5 rounded">
                    Account Overview
                </span>
                <span class="text-xs text-neutral-400">&middot; ID #<?= (int) ($user['id'] ?? 0) ?></span>
            </div>
            <h1 class="text-heading-lg font-bold text-white tracking-tight">
                <?= htmlspecialchars($user['name'] ?? 'User') ?>
            </h1>
            <p class="text-body-sm text-neutral-300" style="margin-top: 0.35rem;">
                Manage your credentials, personal profile details and system access permissions
            </p>
        </div>
        <div class="hidden sm:flex flex-col items-end gap-2">
            <span class="badge badge-success" style="background: rgba(255,255,255,0.12); color: #86efac; border: 1px solid rgba(255,255,255,0.18); padding: 0.375rem 0.875rem; font-size: 0.75rem;">
                <span style="width: 0.45rem; height: 0.45rem; border-radius: 50%; background: #34d399; display: inline-block; margin-right: 0.4rem;"></span>
                <?= htmlspecialchars(strtoupper($role)) ?> &middot; <?= htmlspecialchars(ucfirst($user['status'] ?? 'Active')) ?>
            </span>
            <span class="text-[11px] text-neutral-400">
                Joined <?= !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : 'Recently' ?>
            </span>
        </div>
    </div>

    <!-- Flash Notifications -->
    <?php if ($error): ?>
        <div class="text-body-sm flex items-center gap-2.5" role="alert" style="background: #fef2f2; color: #b91c1c; border-radius: 0.875rem; padding: 1.125rem 1.375rem; border: 1px solid #fca5a5; box-shadow: 0 1px 3px rgba(220,38,38,0.06);">
            <span style="font-size: 1.2rem; line-height: 1;">⚠</span>
            <span class="font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="text-body-sm flex items-center gap-2.5" role="alert" style="background: #f0fdf4; color: #166534; border-radius: 0.875rem; padding: 1.125rem 1.375rem; border: 1px solid #86efac; box-shadow: 0 1px 3px rgba(22,101,52,0.06);">
            <span style="font-size: 1.2rem; line-height: 1;">✓</span>
            <span class="font-medium"><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Summary KPI Metric Cards (Generous padding & corner clearance) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Metric 1: Role -->
        <div class="reveal-card card metric-accent-primary" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.05);">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Account Role</p>
            <p class="text-2xl font-bold text-neutral-900 mt-1.5 capitalize"><?= htmlspecialchars($role) ?></p>
            <p class="text-caption text-neutral-400 mt-1">
                <?= $role === 'admin' ? 'Superadmin clearance' : ($role === 'boarder' ? 'Resident status' : 'Operations staff') ?>
            </p>
        </div>

        <!-- Metric 2: Account Status -->
        <div class="reveal-card card metric-accent-success" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.05);">
            <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Status</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1.5 capitalize"><?= htmlspecialchars($user['status'] ?? 'Active') ?></p>
            <p class="text-caption text-neutral-400 mt-1">Account in good standing</p>
        </div>

        <!-- Metric 3: Role Specific Indicator -->
        <?php if ($role === 'boarder'): ?>
            <div class="reveal-card card metric-accent-warning" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.05);">
                <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Assigned Unit</p>
                <p class="text-2xl font-bold text-neutral-900 mt-1.5">
                    <?= !empty($stats['room_number']) ? 'Room ' . htmlspecialchars($stats['room_number']) : 'Pending' ?>
                </p>
                <p class="text-caption text-neutral-400 mt-1">
                    <?= !empty($stats['bed_label']) ? htmlspecialchars($stats['bed_label']) : 'Bed not allocated' ?>
                </p>
            </div>
            <div class="reveal-card card metric-accent-info" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.05);">
                <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Monthly Rent</p>
                <p class="text-2xl font-bold text-neutral-900 mt-1.5">
                    ₱<?= number_format((float) ($stats['base_price'] ?? 0), 2) ?>
                </p>
                <p class="text-caption text-neutral-400 mt-1">
                    <?= (int) ($stats['payments_count'] ?? 0) ?> payment<?= ($stats['payments_count'] ?? 0) !== 1 ? 's' : '' ?> recorded
                </p>
            </div>
        <?php elseif ($role === 'admin'): ?>
            <div class="reveal-card card metric-accent-warning" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.05);">
                <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Total Residents</p>
                <p class="text-2xl font-bold text-neutral-900 mt-1.5"><?= (int) ($stats['total_boarders'] ?? 0) ?></p>
                <p class="text-caption text-neutral-400 mt-1">Registered in boardinghouse</p>
            </div>
            <div class="reveal-card card metric-accent-info" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.05);">
                <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Beds Occupancy</p>
                <p class="text-2xl font-bold text-neutral-900 mt-1.5"><?= (int) ($stats['occupied_beds'] ?? 0) ?></p>
                <p class="text-caption text-neutral-400 mt-1"><?= (int) ($stats['vacant_beds'] ?? 0) ?> vacant beds available</p>
            </div>
        <?php else: ?>
            <div class="reveal-card card metric-accent-warning" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.05);">
                <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Maintenance Queue</p>
                <p class="text-2xl font-bold text-neutral-900 mt-1.5"><?= (int) ($stats['active_queue'] ?? 0) ?></p>
                <p class="text-caption text-neutral-400 mt-1">Active repair requests</p>
            </div>
            <div class="reveal-card card metric-accent-info" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.05);">
                <p class="text-caption font-semibold text-neutral-500 uppercase tracking-wider">Open Incidents</p>
                <p class="text-2xl font-bold text-neutral-900 mt-1.5"><?= (int) ($stats['open_incidents'] ?? 0) ?></p>
                <p class="text-caption text-neutral-400 mt-1">Unresolved reports</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section 1: Identity & Credentials Summary Card (Generous Padding & Corner Clearance) -->
    <div class="reveal-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.125rem; padding: 1.75rem 2rem; box-shadow: 0 2px 8px rgba(15,23,42,0.05);">
        <!-- Card Header: Clean flex row with NO straight line divider -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2 class="text-heading-sm font-bold text-neutral-900" style="margin: 0; font-size: 1.125rem;">
                    Identity &amp; Credentials
                </h2>
                <p class="text-body-sm text-neutral-500" style="margin: 0.25rem 0 0; font-size: 0.8125rem; line-height: 1.5;">
                    Official system account representation &amp; security identity
                </p>
            </div>
            <!-- Exact Navbar Pill: Inline-flex with fit-content, never stretches into a full-width bar -->
            <div style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.9rem; border-radius: 0.625rem; background: #0f172a; border: 1px solid #334155; font-size: 0.75rem; font-weight: 500; color: #f1f5f9; white-space: nowrap; width: fit-content; flex-shrink: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
                <span style="display: inline-block; width: 0.45rem; height: 0.45rem; border-radius: 50%; background: #34d399; box-shadow: 0 0 6px #34d399;"></span>
                <span style="font-weight: 600; color: #ffffff;"><?= htmlspecialchars($user['name'] ?? '') ?></span>
                <span style="color: #94a3b8; text-transform: capitalize; font-weight: normal;">&middot; <?= htmlspecialchars($role) ?></span>
            </div>
        </div>

        <!-- Clean 6-Tile Card Grid with Generous Padding & Corner Clearance -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Full Legal Name</span>
                <p class="text-body font-bold text-neutral-900 leading-snug"><?= htmlspecialchars($user['name'] ?? '') ?></p>
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Email Address</span>
                <p class="text-body font-bold text-neutral-900 leading-snug break-all"><?= htmlspecialchars($user['email'] ?? '') ?></p>
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">System Role</span>
                <div class="mt-1">
                    <span class="badge <?= $role === 'admin' ? 'badge-primary' : ($role === 'boarder' ? 'badge-success' : 'badge-warning') ?> uppercase" style="font-size: 0.7rem; font-weight: 700; padding: 0.25rem 0.65rem; border-radius: 0.5rem;">
                        <?= htmlspecialchars($role) ?>
                    </span>
                </div>
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Account ID</span>
                <p class="text-body font-mono font-bold text-neutral-900 leading-snug">#USR-<?= str_pad((string)($user['id'] ?? 0), 4, '0', STR_PAD_LEFT) ?></p>
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Account Status</span>
                <div class="mt-1 flex items-center gap-2">
                    <span style="width: 0.55rem; height: 0.55rem; border-radius: 50%; background: #16a34a; display: inline-block;"></span>
                    <span class="text-body-sm font-bold text-emerald-800 capitalize"><?= htmlspecialchars($user['status'] ?? 'Active') ?></span>
                </div>
            </div>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Registration Date</span>
                <p class="text-body-sm font-bold text-neutral-900 leading-snug">
                    <?= !empty($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : 'Standard Registration' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Section 2: Role Specific Context Card (Generous Padding & Corner Clearance) -->
    <?php if ($role === 'boarder'): ?>
        <!-- Accommodation & Resident Information Card -->
        <div class="reveal-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.125rem; padding: 1.75rem 2rem; box-shadow: 0 2px 8px rgba(15,23,42,0.05);">
            <!-- Card Header: Clean flex row with NO straight line divider -->
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <h2 class="text-heading-sm font-bold text-neutral-900" style="margin: 0; font-size: 1.125rem;">
                        Resident Accommodation &amp; Unit Details
                    </h2>
                    <p class="text-body-sm text-neutral-500" style="margin: 0.25rem 0 0; font-size: 0.8125rem; line-height: 1.5;">
                        Allocated boarding space, monthly pricing &amp; emergency records
                    </p>
                </div>
                <span style="display: inline-block; padding: 0.35rem 0.85rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.75rem; white-space: nowrap; width: fit-content; flex-shrink: 0; background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0;">
                    Floor <?= htmlspecialchars($boarderProfile['floor'] ?? '1') ?>
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                    <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Room Assignment</span>
                    <p class="text-heading-sm font-bold text-neutral-900 leading-snug">
                        <?= !empty($boarderProfile['room_number']) ? 'Room ' . htmlspecialchars($boarderProfile['room_number']) : '<span class="text-neutral-400 font-normal">Unassigned</span>' ?>
                    </p>
                    <p class="text-caption text-neutral-500 mt-1"><?= !empty($boarderProfile['floor']) ? 'Floor ' . htmlspecialchars($boarderProfile['floor']) : 'Ground Wing' ?></p>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                    <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Bed Allocation</span>
                    <p class="text-heading-sm font-bold text-neutral-900 leading-snug">
                        <?= !empty($boarderProfile['bed_label']) ? htmlspecialchars($boarderProfile['bed_label']) : '<span class="text-neutral-400 font-normal">No Bed Assigned</span>' ?>
                    </p>
                    <p class="text-caption text-neutral-500 mt-1">Assigned Resident Bed</p>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                    <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Monthly Rent Rate</span>
                    <p class="text-heading-sm font-bold text-emerald-700 leading-snug">
                        ₱<?= number_format((float) ($boarderProfile['base_price'] ?? 0), 2) ?>
                    </p>
                    <p class="text-caption text-neutral-500 mt-1">Billed every cycle</p>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                    <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Contact Telephone</span>
                    <p class="text-body font-bold text-neutral-900 leading-snug">
                        <?= !empty($boarderProfile['contact_number']) ? htmlspecialchars($boarderProfile['contact_number']) : '<span class="text-neutral-400 font-normal text-body-sm">Not recorded</span>' ?>
                    </p>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                    <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Emergency Contact</span>
                    <p class="text-body font-bold text-neutral-900 leading-snug">
                        <?= !empty($boarderProfile['emergency_contact_number']) ? htmlspecialchars($boarderProfile['emergency_contact_number']) : '<span class="text-neutral-400 font-normal text-body-sm">Not recorded</span>' ?>
                    </p>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
                    <span class="text-caption uppercase tracking-wider font-bold text-neutral-500 block mb-1.5">Move-in Date</span>
                    <p class="text-body font-bold text-neutral-900 leading-snug">
                        <?= !empty($boarderProfile['move_in_date']) ? date('F j, Y', strtotime($boarderProfile['move_in_date'])) : '<span class="text-neutral-400 font-normal text-body-sm">Pending move-in</span>' ?>
                    </p>
                </div>
            </div>

            <!-- Quick Action Buttons with Generous Spacing and NO straight divider line -->
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-top: 1.75rem;">
                <a href="/portal/payments/new" class="btn btn-primary !py-2.5 !px-4.5 !text-xs !rounded-xl inline-flex items-center gap-2 shadow-xs transition-all">
                    <span>₱</span>
                    <span class="font-semibold">Submit Rent Payment</span>
                </a>
                <a href="/portal/maintenance/new" class="btn btn-secondary !py-2.5 !px-4.5 !text-xs !rounded-xl inline-flex items-center gap-2 shadow-xs hover:border-neutral-400 transition-all">
                    <span>🔧</span>
                    <span class="font-semibold">Report a Maintenance Issue</span>
                </a>
                <a href="/portal/dashboard" class="btn btn-secondary !py-2.5 !px-4.5 !text-xs !rounded-xl inline-flex items-center gap-2 shadow-xs hover:border-neutral-400 transition-all">
                    <span>🏠</span>
                    <span class="font-semibold">Go to Resident Portal</span>
                </a>
            </div>
        </div>
    <?php elseif ($role === 'admin'): ?>
        <!-- Administrative Privileges & Scope Card -->
        <div class="reveal-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.125rem; padding: 1.75rem 2rem; box-shadow: 0 2px 8px rgba(15,23,42,0.05);">
            <!-- Card Header: Clean flex row with NO straight line divider -->
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <h2 class="text-heading-sm font-bold text-neutral-900" style="margin: 0; font-size: 1.125rem;">
                        Administrative Authority &amp; System Scope
                    </h2>
                    <p class="text-body-sm text-neutral-500" style="margin: 0.25rem 0 0; font-size: 0.8125rem; line-height: 1.5;">
                        System control access, financial governance &amp; directory privileges
                    </p>
                </div>
                <span style="display: inline-block; padding: 0.35rem 0.85rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.75rem; white-space: nowrap; width: fit-content; flex-shrink: 0; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                    Full Superadmin Access
                </span>
            </div>

            <!-- Mini Card Tiles with Ample Corner Clearance & Padding -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04); display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #eff6ff; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 0.25rem;">
                        👥
                    </div>
                    <h3 class="text-body-sm font-bold text-neutral-900">Resident Lifecycle</h3>
                    <p class="text-caption text-neutral-500 leading-relaxed">Full CRUD over boarders, beds, and statuses</p>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04); display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #f0fdf4; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 0.25rem;">
                        💳
                    </div>
                    <h3 class="text-body-sm font-bold text-neutral-900">Financial Ledger</h3>
                    <p class="text-caption text-neutral-500 leading-relaxed">Rent payments, expense vouchers, penalty rules</p>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04); display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #fffbeb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 0.25rem;">
                        🛏️
                    </div>
                    <h3 class="text-body-sm font-bold text-neutral-900">Room Management</h3>
                    <p class="text-caption text-neutral-500 leading-relaxed">Bed allocations, floor assignments, pricing</p>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04); display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #faf5ff; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 0.25rem;">
                        🛡️
                    </div>
                    <h3 class="text-body-sm font-bold text-neutral-900">System Security</h3>
                    <p class="text-caption text-neutral-500 leading-relaxed">Strict CSRF tokens, secure session headers</p>
                </div>
            </div>

            <!-- Quick Action Links Cluster with Generous Spacing and NO straight divider line -->
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-top: 1.75rem;">
                <a href="/admin/boarders" class="btn btn-secondary !py-2.5 !px-4.5 !text-xs !rounded-xl inline-flex items-center gap-2 shadow-xs hover:border-neutral-400 transition-all">
                    <span>👥</span>
                    <span class="font-semibold">Boarders Directory</span>
                </a>
                <a href="/admin/rooms" class="btn btn-secondary !py-2.5 !px-4.5 !text-xs !rounded-xl inline-flex items-center gap-2 shadow-xs hover:border-neutral-400 transition-all">
                    <span>🛏️</span>
                    <span class="font-semibold">Rooms &amp; Beds</span>
                </a>
                <a href="/admin/payments" class="btn btn-secondary !py-2.5 !px-4.5 !text-xs !rounded-xl inline-flex items-center gap-2 shadow-xs hover:border-neutral-400 transition-all">
                    <span>💰</span>
                    <span class="font-semibold">Payments Queue</span>
                </a>
                <a href="/admin/dashboard" class="btn btn-primary !py-2.5 !px-4.5 !text-xs !rounded-xl inline-flex items-center gap-2 shadow-xs transition-all">
                    <span>📊</span>
                    <span class="font-semibold">Admin Dashboard</span>
                </a>
            </div>
        </div>
    <?php elseif ($role === 'staff'): ?>
        <!-- House Staff Duty & Operational Scope Card -->
        <div class="reveal-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.125rem; padding: 1.75rem 2rem; box-shadow: 0 2px 8px rgba(15,23,42,0.05);">
            <!-- Card Header: Clean flex row with NO straight line divider -->
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <h2 class="text-heading-sm font-bold text-neutral-900" style="margin: 0; font-size: 1.125rem;">
                        Staff Operations &amp; Facility Scope
                    </h2>
                    <p class="text-body-sm text-neutral-500" style="margin: 0.25rem 0 0; font-size: 0.8125rem; line-height: 1.5;">
                        Maintenance execution, emergency dispatch &amp; incident management
                    </p>
                </div>
                <span style="display: inline-block; padding: 0.35rem 0.85rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.75rem; white-space: nowrap; width: fit-content; flex-shrink: 0; background: #fffbeb; color: #b45309; border: 1px solid #fde68a;">
                    Operations Duty Active
                </span>
            </div>

            <!-- Mini Card Tiles with Ample Corner Clearance & Padding -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04); display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #eff6ff; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 0.25rem;">
                        🔧
                    </div>
                    <h3 class="text-body-sm font-bold text-neutral-900">Maintenance Queue</h3>
                    <p class="text-caption text-neutral-500 leading-relaxed"><?= (int) ($stats['active_queue'] ?? 0) ?> active repair task<?= ($stats['active_queue'] ?? 0) !== 1 ? 's' : '' ?> awaiting resolution</p>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04); display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #fef2f2; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 0.25rem;">
                        🚨
                    </div>
                    <h3 class="text-body-sm font-bold text-neutral-900">Emergency SOS</h3>
                    <p class="text-caption text-neutral-500 leading-relaxed"><?= (int) ($stats['active_sos'] ?? 0) ?> alert broadcast<?= ($stats['active_sos'] ?? 0) !== 1 ? 's' : '' ?> on file</p>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04); display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #fffbeb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 0.25rem;">
                        📋
                    </div>
                    <h3 class="text-body-sm font-bold text-neutral-900">Incident Logging</h3>
                    <p class="text-caption text-neutral-500 leading-relaxed"><?= (int) ($stats['open_incidents'] ?? 0) ?> unresolved incident report<?= ($stats['open_incidents'] ?? 0) !== 1 ? 's' : '' ?></p>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.375rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04); display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #faf5ff; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 0.25rem;">
                        🛡️
                    </div>
                    <h3 class="text-body-sm font-bold text-neutral-900">Facility Checks</h3>
                    <p class="text-caption text-neutral-500 leading-relaxed">Daily security, curfew &amp; boardinghouse inspection</p>
                </div>
            </div>

            <!-- Quick Action Links Cluster with Generous Spacing and NO straight divider line -->
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-top: 1.75rem;">
                <a href="/staff/maintenance" class="btn btn-secondary !py-2.5 !px-4.5 !text-xs !rounded-xl inline-flex items-center gap-2 shadow-xs hover:border-neutral-400 transition-all">
                    <span>🔧</span>
                    <span class="font-semibold">Maintenance Queue</span>
                </a>
                <a href="/staff/incidents" class="btn btn-secondary !py-2.5 !px-4.5 !text-xs !rounded-xl inline-flex items-center gap-2 shadow-xs hover:border-neutral-400 transition-all">
                    <span>📋</span>
                    <span class="font-semibold">Incidents Log</span>
                </a>
                <a href="/staff/dashboard" class="btn btn-primary !py-2.5 !px-4.5 !text-xs !rounded-xl inline-flex items-center gap-2 shadow-xs transition-all">
                    <span>📊</span>
                    <span class="font-semibold">Staff Dashboard</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Section 3: Edit Profile Information Form Card (Generous Padding & Corner Clearance) -->
    <div class="reveal-card form-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.125rem; padding: 1.75rem 2rem; box-shadow: 0 2px 8px rgba(15,23,42,0.05);">
        <div style="margin-bottom: 1.5rem;">
            <h2 class="text-heading-sm font-bold text-neutral-900" style="margin: 0; font-size: 1.125rem;">
                Update Profile Information
            </h2>
            <p class="text-body-sm text-neutral-500" style="margin: 0.25rem 0 0; font-size: 0.8125rem; line-height: 1.5;">
                Keep your display name, contact information and communication records current
            </p>
        </div>

        <form method="post" action="/profile/update" class="space-y-5">
            <?= \App\Support\Csrf::field() ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="profile-name" class="block text-caption font-semibold text-neutral-700 uppercase tracking-wider mb-1.5">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           id="profile-name"
                           name="name"
                           class="input w-full"
                           style="border-radius: 0.625rem; padding: 0.65rem 0.875rem; font-size: 0.875rem;"
                           value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                           required
                           maxlength="150" />
                </div>

                <div>
                    <label for="profile-email" class="block text-caption font-semibold text-neutral-700 uppercase tracking-wider mb-1.5">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input type="email"
                           id="profile-email"
                           name="email"
                           class="input w-full"
                           style="border-radius: 0.625rem; padding: 0.65rem 0.875rem; font-size: 0.875rem;"
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                           required
                           maxlength="150" />
                </div>

                <?php if ($role === 'boarder'): ?>
                    <div>
                        <label for="profile-contact" class="block text-caption font-semibold text-neutral-700 uppercase tracking-wider mb-1.5">
                            Personal Phone Number
                        </label>
                        <input type="text"
                                id="profile-contact"
                                name="contact_number"
                                class="input w-full"
                                style="border-radius: 0.625rem; padding: 0.65rem 0.875rem; font-size: 0.875rem;"
                                placeholder="e.g. 0917-123-4567"
                                value="<?= htmlspecialchars($boarderProfile['contact_number'] ?? '') ?>"
                                maxlength="50" />
                    </div>

                    <div>
                        <label for="profile-emergency" class="block text-caption font-semibold text-neutral-700 uppercase tracking-wider mb-1.5">
                            Emergency Contact Number
                        </label>
                        <input type="text"
                                id="profile-emergency"
                                name="emergency_contact_number"
                                class="input w-full"
                                style="border-radius: 0.625rem; padding: 0.65rem 0.875rem; font-size: 0.875rem;"
                                placeholder="e.g. 0918-987-6543 (Parent / Guardian)"
                                value="<?= htmlspecialchars($boarderProfile['emergency_contact_number'] ?? '') ?>"
                                maxlength="50" />
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex items-center justify-end pt-3">
                <button type="submit" class="btn btn-primary !py-2.5 !px-5 !text-xs font-semibold shadow-xs cursor-pointer inline-flex items-center gap-1.5">
                    <span>✓</span>
                    <span>Save Profile Changes</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Section 4: Security & Password Management Form Card (Generous Padding & Corner Clearance) -->
    <div class="reveal-card form-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.125rem; padding: 1.75rem 2rem; box-shadow: 0 2px 8px rgba(15,23,42,0.05);">
        <div style="margin-bottom: 1.5rem;">
            <h2 class="text-heading-sm font-bold text-neutral-900" style="margin: 0; font-size: 1.125rem;">
                Security &amp; Password
            </h2>
            <p class="text-body-sm text-neutral-500" style="margin: 0.25rem 0 0; font-size: 0.8125rem; line-height: 1.5;">
                Protect your account by regularly changing your password to a strong phrase
            </p>
        </div>

        <form method="post" action="/profile/password" class="space-y-5">
            <?= \App\Support\Csrf::field() ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label for="current-password" class="block text-caption font-semibold text-neutral-700 uppercase tracking-wider mb-1.5">
                        Current Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password"
                           id="current-password"
                           name="current_password"
                           class="input w-full"
                           style="border-radius: 0.625rem; padding: 0.65rem 0.875rem; font-size: 0.875rem;"
                           placeholder="••••••••••••"
                           required />
                </div>

                <div>
                    <label for="new-password" class="block text-caption font-semibold text-neutral-700 uppercase tracking-wider mb-1.5">
                        New Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password"
                           id="new-password"
                           name="new_password"
                           class="input w-full"
                           style="border-radius: 0.625rem; padding: 0.65rem 0.875rem; font-size: 0.875rem;"
                           placeholder="Min. 8 characters"
                           minlength="8"
                           required />
                </div>

                <div>
                    <label for="confirm-password" class="block text-caption font-semibold text-neutral-700 uppercase tracking-wider mb-1.5">
                        Confirm New Password <span class="text-red-500">*</span>
                    </label>
                    <input type="password"
                           id="confirm-password"
                           name="confirm_password"
                           class="input w-full"
                           style="border-radius: 0.625rem; padding: 0.65rem 0.875rem; font-size: 0.875rem;"
                           placeholder="Re-type new password"
                           minlength="8"
                           required />
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.125rem 1.375rem; box-shadow: 0 1px 2px rgba(15,23,42,0.03);" class="text-caption text-neutral-600 flex items-start gap-3">
                <div style="width: 1.75rem; height: 1.75rem; border-radius: 50%; background: #e2e8f0; color: #475569; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; flex-shrink: 0;">
                    ℹ
                </div>
                <div class="leading-relaxed pt-0.5">
                    <strong class="text-neutral-800 font-semibold">Password Requirements:</strong> Passwords must contain at least 8 characters. We recommend combining letters, numbers, and special symbols for maximum security.
                </div>
            </div>

            <div class="flex items-center justify-end pt-2">
                <button type="submit" class="btn btn-secondary !py-2.5 !px-5 !text-xs font-semibold shadow-xs cursor-pointer inline-flex items-center gap-1.5">
                    <span>🔒</span>
                    <span>Update Password</span>
                </button>
            </div>
        </form>
    </div>

</div>

<!-- Microinteraction & Smooth Entrance Animations -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (window.gsap) {
        gsap.fromTo(
            ".reveal-card",
            { opacity: 0, y: 15 },
            {
                opacity: 1,
                y: 0,
                duration: 0.45,
                stagger: 0.08,
                ease: "power2.out"
            }
        );
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
