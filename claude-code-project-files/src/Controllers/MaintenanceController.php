<?php

namespace App\Controllers;

use App\Models\MaintenanceRequest;
use App\Models\Notification;
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
        // A video over post_max_size empties the whole form, CSRF token included:
        // say "too large" rather than "invalid session".
        if (Uploads::requestTooLarge()) {
            $_SESSION['flash_error'] = Uploads::tooLargeMessage();
            header('Location: /portal/maintenance/new');
            exit;
        }
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }

        $description = trim((string) ($_POST['description'] ?? ''));
        $category = (string) ($_POST['category'] ?? '');

        // Bug found during Phase 8 QA pass: the form's HTML `required` attribute
        // was the only validation — trivially bypassed by posting directly.
        if ($description === '' || mb_strlen($description) > 5000) {
            $_SESSION['flash_error'] = 'Description is required (5,000 characters max).';
            header('Location: /portal/maintenance/new');
            exit;
        }
        if (!in_array($category, MaintenanceRequest::CATEGORIES, true)) {
            $_SESSION['flash_error'] = 'Please choose a valid category.';
            header('Location: /portal/maintenance/new');
            exit;
        }

        $mediaPath = null;
        if (isset($_FILES['media'])) { // optional; Uploads::store() returns null when none was chosen
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

        // The room comes from the boarder's own profile, never from the form.
        $boarderId = (int) $_SESSION['user_id'];
        $profile = \App\Models\BoarderProfile::find($boarderId);

        $requestId = MaintenanceRequest::create([
            'boarder_id' => $boarderId,
            'room_id' => !empty($profile['room_id']) ? (int) $profile['room_id'] : null,
            'category' => $category,
            'description' => $description,
            'media_path' => $mediaPath,
            'severity_score' => $result['score'],
            'priority_tier' => $result['tier'],
            'scoring_pending' => $result['scoring_pending'],
        ]);

        // Notify staff and admin about new maintenance request with deep link
        NotificationDispatcher::maintenanceSubmitted($requestId, $category, $result['tier'], $description);

        $_SESSION['flash_success'] = "Maintenance request #{$requestId} submitted.";
        header('Location: /portal/dashboard');
        exit;
    }

    /** POST /api/maintenance/score-preview — the form's live preview uses the same scoring as submission. */
    public static function scorePreview(): void
    {
        header('Content-Type: application/json');
        if (!Csrf::verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false]);
            return;
        }
        $description = mb_substr((string) ($_POST['description'] ?? ''), 0, 5000);
        $category = (string) ($_POST['category'] ?? 'other');
        echo json_encode(['ok' => true] + ScoringClient::score($description, $category, !empty($_POST['has_media'])));
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
        $status = (string) ($_POST['status'] ?? '');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $request = MaintenanceRequest::find((int) $id);
        if (!$request || !in_array($status, MaintenanceRequest::STATUSES, true)) {
            $_SESSION['flash_error'] = 'Request not found or invalid status.';
            header('Location: /staff/maintenance');
            exit;
        }
        MaintenanceRequest::updateStatus((int) $id, $status, $userId);

        if ($request['status'] !== $status) {
            if ($status === 'resolved') {
                NotificationDispatcher::maintenanceResolved((int) $request['boarder_id'], (int) $id);
            } elseif ($status === 'in_progress') {
                NotificationDispatcher::maintenanceInProgress((int) $request['boarder_id'], (int) $id);
            }
        }

        header('Location: /staff/maintenance');
        exit;
    }

    public static function history(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 50; // Show 50 records per page
        $offset = ($page - 1) * $limit;

        $history = MaintenanceRequest::allWithDetails($limit, $offset);
        $totalCount = MaintenanceRequest::countAll();
        $totalPages = ceil($totalCount / $limit);

        require __DIR__ . '/../Views/staff/maintenance_history.php';
    }
}
