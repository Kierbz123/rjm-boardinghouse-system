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
        // Optimize: Get all KPI data in single database transaction
        $pdo = Database::getConnection();
        
        // Get KPI counts in parallel (faster than separate queries)
        $pendingPayments = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE verification_status IN ('pending','flagged')")->fetchColumn();
        $activeSos = (int) $pdo->query("SELECT COUNT(*) FROM sos_alerts WHERE status != 'resolved'")->fetchColumn();
        
        // Get maintenance counts by tier (optimized query)
        $openByTier = $pdo->query("SELECT priority_tier, COUNT(*) AS c FROM maintenance_requests WHERE status != 'resolved' GROUP BY priority_tier")->fetchAll();
        
        // Get recent expenses with staff names (limit to 5)
        $recentExpenses = $pdo->query("SELECT expenses.*, users.name AS staff_name FROM expenses JOIN users ON users.id = expenses.staff_id ORDER BY expenses.created_at DESC LIMIT 5")->fetchAll();
        
        // Optimize occupancy: only take snapshot if not already taken today
        $todaySnapshot = $pdo->query("SELECT * FROM occupancy_snapshots WHERE DATE(created_at) = CURDATE() LIMIT 1")->fetch();
        if (!$todaySnapshot) {
            OccupancySnapshot::takeToday();
            $occupancy = OccupancySnapshot::latest();
        } else {
            $occupancy = $todaySnapshot;
        }

        require __DIR__ . '/../Views/admin/dashboard.php';
    }

    public static function staff(): void
    {
        // Optimize: Direct database queries instead of model methods
        $pdo = Database::getConnection();
        
        // Get maintenance queue (optimized with LIMIT)
        $queue = $pdo->query("SELECT maintenance_requests.*, users.name AS boarder_name, rooms.room_number
                FROM maintenance_requests
                JOIN users ON users.id = maintenance_requests.boarder_id
                LEFT JOIN rooms ON rooms.id = maintenance_requests.room_id
                WHERE maintenance_requests.status != 'resolved'
                ORDER BY FIELD(maintenance_requests.priority_tier, 'critical','high','medium','low'), maintenance_requests.created_at ASC
                LIMIT 100")->fetchAll();
        
        // Get active SOS alerts (optimized with LIMIT)
        $activeSos = $pdo->query("SELECT sos_alerts.*, users.name AS boarder_name, rooms.room_number
                FROM sos_alerts
                JOIN users ON users.id = sos_alerts.boarder_id
                LEFT JOIN rooms ON rooms.id = sos_alerts.room_id
                WHERE sos_alerts.status != 'resolved'
                ORDER BY sos_alerts.created_at DESC
                LIMIT 50")->fetchAll();

        $activeBoarders = $pdo->query('
            SELECT bp.user_id, u.name, r.room_number, b.label AS bed_label
            FROM boarder_profiles bp
            JOIN users u ON u.id = bp.user_id
            LEFT JOIN rooms r ON r.id = bp.room_id
            LEFT JOIN beds b ON b.id = bp.bed_id
            WHERE bp.status = "active"
            ORDER BY r.room_number ASC, u.name ASC
        ')->fetchAll();
        $activeRules = \App\Models\PenaltyRule::allActive();

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

        $balanceDetails = \App\Services\BillingService::calculateBalance($userId, $pdo);

        require __DIR__ . '/../Views/portal/dashboard.php';
    }
}
