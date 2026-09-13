<?php

namespace App\Controllers;

use App\Models\SosAlert;
use App\Models\BoarderProfile;
use App\Services\NotificationDispatcher;
use App\Support\Csrf;

class SosController
{
    /** Feature 2 — the AJAX SOS endpoint from ARCHITECTURE.md §3.1/§4.3. */
    public static function trigger(): void
    {
        header('Content-Type: application/json');
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid session.']);
            return;
        }

        $boarderId = (int) $_SESSION['user_id'];
        $profile = BoarderProfile::find($boarderId);
        $roomId = $profile['room_id'] ?? null;

        $id = SosAlert::create($boarderId, $roomId);
        echo json_encode(['ok' => true, 'alert_id' => $id]);
    }

    public static function activeJson(): void
    {
        header('Content-Type: application/json');
        echo json_encode(SosAlert::activeAlerts());
    }

    public static function acknowledge(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        SosAlert::acknowledge((int) $id, (int) $_SESSION['user_id']);
        $alert = SosAlert::find((int) $id);
        if ($alert) {
            NotificationDispatcher::sosAcknowledged((int) $alert['boarder_id'], (int) $id);
        }
        header('Location: /staff/dashboard');
        exit;
    }

    public static function resolve(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        SosAlert::resolve((int) $id);
        header('Location: /staff/dashboard');
        exit;
    }
}
