<?php

namespace App\Controllers;

use App\Models\MaintenanceRequest;
use App\Services\ScoringClient;
use App\Services\NotificationDispatcher;
use App\Support\Csrf;
use App\Support\Uploads;
use App\Database;

class MaintenanceController
{
    public static function newForm(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT bp.*, r.room_number, b.label AS bed_label
            FROM boarder_profiles bp
            LEFT JOIN rooms r ON r.id = bp.room_id
            LEFT JOIN beds b ON b.id = bp.bed_id
            WHERE bp.user_id = ?
        ');
        $stmt->execute([$userId]);
        $boarder = $stmt->fetch() ?: null;

        $stmt = $pdo->prepare('SELECT * FROM maintenance_requests WHERE boarder_id = ? ORDER BY created_at DESC LIMIT 5');
        $stmt->execute([$userId]);
        $recentRequests = $stmt->fetchAll();

        require __DIR__ . '/../Views/portal/maintenance_new.php';
    }

    /** Feature 1 — submit + score, with the fail-closed fallback from ARCHITECTURE.md §4.2. */
    public static function create(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }

        $description = trim((string) $_POST['description']);
        $category = (string) $_POST['category'];

        // Bug found during Phase 8 QA pass: the form's HTML `required` attribute
        // was the only validation — trivially bypassed by posting directly.
        if ($description === '') {
            $_SESSION['flash_error'] = 'Description is required.';
            header('Location: /portal/maintenance/new');
            exit;
        }

        $mediaPath = null;
        if (!empty($_FILES['media']['tmp_name'])) {
            try {
                $mediaPath = Uploads::store($_FILES['media'], 'maintenance');
            } catch (\RuntimeException $e) {
                // Previously uncaught: an unsupported/oversized file bubbled up
                // to the generic 500 handler instead of a normal validation
                // error, even though Uploads::store() already throws a
                // safe, user-facing message for exactly this case.
                $_SESSION['flash_error'] = $e->getMessage();
                header('Location: /portal/maintenance/new');
                exit;
            }
        }

        $result = ScoringClient::score($description, $category, $mediaPath !== null);

        MaintenanceRequest::create([
            'boarder_id' => (int) $_SESSION['user_id'],
            'room_id' => !empty($_POST['room_id']) ? (int) $_POST['room_id'] : null,
            'category' => $category,
            'description' => $description,
            'media_path' => $mediaPath,
            'severity_score' => $result['score'],
            'priority_tier' => $result['tier'],
            'scoring_pending' => $result['scoring_pending'],
        ]);

        header('Location: /portal/dashboard');
        exit;
    }

    public static function queue(): void
    {
        $queue = MaintenanceRequest::queueSorted();
        require __DIR__ . '/../Views/staff/maintenance_queue.php';
    }

    public static function updateStatus(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $status = (string) $_POST['status'];
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        MaintenanceRequest::updateStatus((int) $id, $status, $userId);

        if ($status === 'resolved') {
            $request = MaintenanceRequest::find((int) $id);
            if ($request) {
                NotificationDispatcher::maintenanceResolved((int) $request['boarder_id'], (int) $id);
            }
        }

        header('Location: /staff/maintenance');
        exit;
    }

    public static function history(): void
    {
        $history = MaintenanceRequest::allWithDetails();
        require __DIR__ . '/../Views/staff/maintenance_history.php';
    }
}
