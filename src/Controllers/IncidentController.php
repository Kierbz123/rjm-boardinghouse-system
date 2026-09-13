<?php

namespace App\Controllers;

use App\Models\Incident;
use App\Support\Csrf;

class IncidentController
{
    public static function index(): void
    {
        $incidents = Incident::all();
        require __DIR__ . '/../Views/staff/incidents.php';
    }

    /** Feature 2 — logged independently of any SOS alert, per FEATURES.md §2. */
    public static function create(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $type = trim((string) $_POST['type']);
        $description = trim((string) $_POST['description']);
        if ($type === '' || $description === '') {
            $_SESSION['flash_error'] = 'Type and description are both required.';
            header('Location: /staff/incidents');
            exit;
        }
        if (mb_strlen($type) > 100) {
            $_SESSION['flash_error'] = 'Type must be 100 characters or fewer.';
            header('Location: /staff/incidents');
            exit;
        }
        Incident::create((int) $_SESSION['user_id'], $type, $description);
        header('Location: /staff/incidents');
        exit;
    }

    /** Feature 2 — closes the gap found in QA-VALIDATION-REPORT.md Section H: the
     *  resolved/resolution_notes columns and Incident::resolve() existed but were
     *  never wired to a route. */
    public static function resolve(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $notes = trim((string) ($_POST['resolution_notes'] ?? ''));
        if ($notes === '') {
            $_SESSION['flash_error'] = 'Resolution notes are required.';
            header('Location: /staff/incidents');
            exit;
        }
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        Incident::resolve((int) $id, $notes, $userId);
        header('Location: /staff/incidents');
        exit;
    }

    public static function history(): void
    {
        $history = Incident::allWithDetails();
        require __DIR__ . '/../Views/staff/incident_history.php';
    }
}
