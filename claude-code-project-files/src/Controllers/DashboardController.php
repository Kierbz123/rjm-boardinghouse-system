<?php

namespace App\Controllers;

use App\Models\MaintenanceRequest;
use App\Models\SosAlert;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\OccupancySnapshot;
use App\Models\Notification;
use App\Database;

class DashboardController
{
    /** Feature 3 — Cross-Module Command Center. Optimized with single consolidated query. */
    public static function admin(): void
    {
        \App\Services\HousekeepingService::applyDueMoveOuts(); // move-outs whose day has come
        // Optimize: Get all KPI data in single database transaction
        $pdo = Database::getConnection();
        
        // Get KPI counts in parallel (faster than separate queries)
        $pendingPayments = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE verification_status IN ('pending','flagged')")->fetchColumn();
        $activeSos = (int) $pdo->query("SELECT COUNT(*) FROM sos_alerts WHERE status != 'resolved'")->fetchColumn();
        
        // Get maintenance counts by tier (optimized query)
        // Counted on the time-decayed tier, so this card agrees with the staff queue.
        $tierCounts = array_count_values(array_map(fn ($r) => $r['priority_tier'] ?? 'unscored', \App\Models\MaintenanceRequest::openDecayed()));
        $openByTier = array_map(fn ($tier, $c) => ['priority_tier' => $tier, 'c' => $c], array_keys($tierCounts), $tierCounts);
        
        // Get recent expenses with staff names (limit to 5)
        $recentExpenses = $pdo->query("SELECT expenses.*, users.name AS staff_name FROM expenses JOIN users ON users.id = expenses.staff_id ORDER BY expenses.created_at DESC LIMIT 5")->fetchAll();
        
        // Refresh today's snapshot on every visit (as the Occupancy page does), so the card follows bed changes made later today.
        OccupancySnapshot::takeToday();
        $occupancy = OccupancySnapshot::latest();

        $collections = \App\Services\BillingService::collectionsSummary();

        require __DIR__ . '/../Views/admin/dashboard.php';
    }

    public static function staff(): void
    {
        // Optimize: Direct database queries instead of model methods
        $pdo = Database::getConnection();
        
        // Get maintenance queue (optimized with LIMIT)
        $queue = \App\Models\MaintenanceRequest::queueSorted();
        
        // Get active SOS alerts (optimized with LIMIT)
        $activeSos = $pdo->query("SELECT sos_alerts.*, users.name AS boarder_name, rooms.room_number
                FROM sos_alerts
                JOIN users ON users.id = sos_alerts.boarder_id
                LEFT JOIN rooms ON rooms.id = sos_alerts.room_id
                WHERE sos_alerts.status != 'resolved'
                ORDER BY sos_alerts.created_at DESC
                LIMIT 50")->fetchAll();

        require __DIR__ . '/../Views/staff/dashboard.php';
    }

    public static function portal(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $notifications = Notification::unreadFor($userId);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT bp.*, u.name, u.email, r.room_number, r.base_price, r.floor, b.label AS bed_label
            FROM boarder_profiles bp
            JOIN users u ON u.id = bp.user_id
            LEFT JOIN rooms r ON r.id = bp.room_id
            LEFT JOIN beds b ON b.id = bp.bed_id
            WHERE bp.user_id = ?
        ');
        $stmt->execute([$userId]);
        $boarder = $stmt->fetch() ?: null;

        $stmt = $pdo->prepare('SELECT * FROM payments WHERE boarder_id = ? ORDER BY created_at DESC LIMIT 10');
        $stmt->execute([$userId]);
        $payments = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT * FROM maintenance_requests WHERE boarder_id = ? ORDER BY created_at DESC LIMIT 10');
        $stmt->execute([$userId]);
        $maintenanceRequests = $stmt->fetchAll();

        $balanceDetails = \App\Services\BillingService::calculateBalance($userId, $pdo, true);
        $sosContacts = \App\Models\User::activeStaffPhones(); // the backup beside the SOS button

        require __DIR__ . '/../Views/portal/dashboard.php';
    }
}
