<?php

namespace App\Services;

use App\Models\Notification;

/**
 * Feature 11 (All-Around Notification System) — Role-aware, cross-cutting notification engine.
 * Dispatches notifications with strict permission boundaries and deep-link action URLs:
 * - Admin: Full oversight (financial, operations, emergencies, penalties).
 * - Staff: Operational oversight (SOS, maintenance, incidents) — strictly NO financial leaks.
 * - Boarder: Strict personal isolation (only their own repairs, billing, SOS updates, room assignments).
 */
class NotificationDispatcher
{
    /** SOS Emergency Events */
    public static function emergencySosTriggered(int $alertId, int $boarderId, ?string $roomBed = null): void
    {
        $location = $roomBed ? "Room {$roomBed}" : "Unknown location";
        Notification::broadcastToStaff(
            'sos',
            "🚨 EMERGENCY SOS TRIGGERED: Boarder #{$boarderId} ({$location})",
            "Alert ID #{$alertId} - Immediate dispatch required",
            '/staff/dashboard',
            'sos_alert',
            $alertId
        );
    }

    public static function sosAcknowledged(int $boarderId, int $alertId): void
    {
        Notification::create(
            $boarderId,
            'sos_acknowledged',
            "Your SOS alert #{$alertId} has been acknowledged by staff. Help is responding.",
            '/portal/dashboard'
        );
        // Update the original SOS notification for staff
        Notification::updateEntityActionUrl('sos_alert', $alertId, '/staff/dashboard');
        Notification::updateEntityMessage('sos_alert', $alertId, "🚨 SOS Alert #{$alertId} - Acknowledged by staff");
    }

    public static function sosResolved(int $boarderId, int $alertId): void
    {
        Notification::create(
            $boarderId,
            'sos_resolved',
            "Your SOS alert #{$alertId} has been marked as resolved.",
            '/portal/dashboard'
        );
        // Update the original SOS notification for staff
        Notification::updateEntityActionUrl('sos_alert', $alertId, '/staff/incidents/history');
        Notification::updateEntityMessage('sos_alert', $alertId, "✅ SOS Alert #{$alertId} - Resolved");
    }

    /** Maintenance Events */
    public static function maintenanceSubmitted(int $requestId, string $category, string $tier, string $desc): void
    {
        Notification::broadcastToStaff(
            'maintenance',
            "New maintenance request #{$requestId} ({$tier}): {$category}",
            substr($desc, 0, 60),
            '/staff/maintenance'
        );
    }

    public static function maintenanceInProgress(int $boarderId, int $requestId): void
    {
        Notification::create(
            $boarderId,
            'maintenance',
            "Your maintenance request #{$requestId} is now in progress.",
            '/portal/dashboard'
        );
    }

    public static function maintenanceResolved(int $boarderId, int $requestId): void
    {
        Notification::create(
            $boarderId,
            'maintenance_resolved',
            "Your maintenance request #{$requestId} has been resolved.",
            '/portal/dashboard'
        );
    }

    /** Incident Reports (Staff & Admin) */
    public static function incidentReported(string $type, string $desc): void
    {
        Notification::broadcastToStaff(
            'incident',
            "Incident logged: {$type}",
            substr($desc, 0, 60),
            '/staff/incidents'
        );
    }

    public static function incidentResolved(string $type, int $incidentId, int $reporterId = 0): void
    {
        Notification::broadcastToStaff(
            'incident_resolved',
            "Incident #{$incidentId} ({$type}) marked resolved.",
            '',
            '/staff/incidents/history'
        );

        // If reported by a user, dispatch notification to the reporter
        if ($reporterId > 0) {
            Notification::create(
                $reporterId,
                'incident_resolved',
                "Your reported incident #{$incidentId} ({$type}) was marked resolved by staff.",
                '/staff/incidents'
            );
        }
    }

    /** Payments & Billing — Strictly Admin & Target Boarder */
    public static function paymentSubmitted(int $paymentId, int $boarderId, string $billingPeriod, float $claimed): void
    {
        // Sent ONLY to admins — Staff has no access to payments
        Notification::broadcastToAdmins(
            'payment',
            "New payment proof submitted by Boarder #{$boarderId} for {$billingPeriod} (₱" . number_format($claimed, 2) . ")",
            "Payment ID #{$paymentId} awaiting review",
            '/admin/payments'
        );
    }

    public static function paymentApproved(int $boarderId, string $billingPeriod): void
    {
        // Sent ONLY to the boarder
        Notification::create(
            $boarderId,
            'payment_approved',
            "Your rent payment proof for {$billingPeriod} was APPROVED.",
            '/portal/dashboard'
        );
    }

    public static function paymentRejected(int $boarderId, string $billingPeriod): void
    {
        // Sent ONLY to the boarder
        Notification::create(
            $boarderId,
            'payment_rejected',
            "Your rent payment proof for {$billingPeriod} was REJECTED. Please check your submission and retry.",
            '/portal/payments/new'
        );
    }

    public static function rentDue(int $boarderId, string $billingPeriod): void
    {
        // Sent ONLY to the boarder
        Notification::create(
            $boarderId,
            'rent_due',
            "Rent for {$billingPeriod} is due soon. Please submit payment proof.",
            '/portal/payments/new'
        );
    }

    public static function penaltyApplied(int $boarderId, float $amount, string $billingPeriod): void
    {
        // Sent ONLY to the boarder
        Notification::create(
            $boarderId,
            'penalty',
            "A late penalty of ₱" . number_format($amount, 2) . " was applied for {$billingPeriod}.",
            '/portal/payments/new'
        );
    }

    /** Bed & Room Assignments */
    public static function bedAssigned(int $boarderId, string $roomNumber, string $bedLabel): void
    {
        Notification::create(
            $boarderId,
            'general',
            "Room assignment updated: Room {$roomNumber} - {$bedLabel}.",
            '/portal/dashboard'
        );
    }

    /** Manual Penalty & Combined Payment Events */
    public static function manualPenaltyIssued(int $boarderId, string $ruleName, float $amount, string $reason, ?string $dueDate, float $newBalance): void
    {
        $dueText = $dueDate ? " Due date: {$dueDate}." : "";
        $reasonText = $reason !== '' ? " Reason: {$reason}." : "";
        Notification::create(
            $boarderId,
            'penalty',
            "Penalty Issued: {$ruleName} (₱" . number_format($amount, 2) . ").{$reasonText}{$dueText} Current outstanding balance: ₱" . number_format($newBalance, 2),
            '/portal/dashboard'
        );
    }

    public static function combinedPaymentApproved(int $boarderId, float $totalPaid, float $rentAllocated, array $settledPenalties, float $remainingBalance): void
    {
        $penCount = count($settledPenalties);
        $penText = $penCount > 0 ? " and {$penCount} penalty obligation(s) fully settled" : "";
        Notification::create(
            $boarderId,
            'payment_approved',
            "Payment Approved: ₱" . number_format($totalPaid, 2) . " applied to Monthly Rent (₱" . number_format($rentAllocated, 2) . "){$penText}. Remaining balance: ₱" . number_format($remainingBalance, 2),
            '/portal/dashboard'
        );
    }

    public static function manualPenaltyOverrideSettled(int $boarderId, string $ruleName, float $amount, float $remainingBalance): void
    {
        Notification::create(
            $boarderId,
            'payment_approved',
            "Administrative Settlement: Penalty '{$ruleName}' (₱" . number_format($amount, 2) . ") has been marked as paid. Remaining balance: ₱" . number_format($remainingBalance, 2),
            '/portal/dashboard'
        );
    }
}
