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
    /** Feature 3 — Cross-Module Command Center. Every number here is a direct query, not a cache. */
    public static function admin(): void
    {
        $pendingPayments = Payment::pendingOrFlaggedCount();
        $openByTier = MaintenanceRequest::countsByTier();
        $activeSos = SosAlert::activeCount();
        $recentExpenses = Expense::recent(5);
        OccupancySnapshot::takeToday();
        $occupancy = OccupancySnapshot::latest();

        require __DIR__ . '/../Views/admin/dashboard.php';
    }

    public static function staff(): void
    {
        $queue = MaintenanceRequest::queueSorted();
        $activeSos = SosAlert::activeAlerts();
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

        require __DIR__ . '/../Views/portal/dashboard.php';
    }
}
