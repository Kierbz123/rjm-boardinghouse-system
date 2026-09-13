<?php

namespace App\Services;

use App\Models\Notification;

/** Feature 11 (All-Around Notification System) — the 3 cross-cutting events from PHASES.md Phase 7. */
class NotificationDispatcher
{
    public static function maintenanceResolved(int $boarderId, int $requestId): void
    {
        Notification::create($boarderId, 'maintenance_resolved', "Your maintenance request #{$requestId} has been resolved.");
    }

    public static function sosAcknowledged(int $boarderId, int $alertId): void
    {
        Notification::create($boarderId, 'sos_acknowledged', "Your SOS alert #{$alertId} has been acknowledged by staff.");
    }

    public static function rentDue(int $boarderId, string $billingPeriod): void
    {
        Notification::create($boarderId, 'rent_due', "Rent for {$billingPeriod} is due soon.");
    }

    public static function penaltyApplied(int $boarderId, float $amount, string $billingPeriod): void
    {
        Notification::create($boarderId, 'penalty', "A late penalty of {$amount} was applied for {$billingPeriod}.");
    }
}
