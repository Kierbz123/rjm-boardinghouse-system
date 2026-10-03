<?php

namespace App\Controllers;

use App\Models\SosAlert;
use App\Models\BoarderProfile;
use App\Models\Notification;
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

        // Repeated presses while help is already on the way don't page staff again.
        $open = SosAlert::recentOpenFor($boarderId);
        if ($open) {
            echo json_encode(['ok' => true, 'alert_id' => (int) $open['id']]);
            return;
        }

        $profile = BoarderProfile::findWithFullDetails($boarderId);
        $roomId = !empty($profile['room_id']) ? (int) $profile['room_id'] : null;

        $id = SosAlert::create($boarderId, $roomId);

        // Notify staff & admin about SOS alert with deep link
        $roomInfo = $profile && $profile['room_number'] !== null
            ? $profile['room_number'] . ($profile['bed_label'] ? ' / ' . $profile['bed_label'] : '')
            : null;
        NotificationDispatcher::emergencySosTriggered($id, $boarderId, $roomInfo);

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
        if (SosAlert::acknowledge((int) $id, (int) $_SESSION['user_id'])) {
            $alert = SosAlert::find((int) $id);
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
        if (SosAlert::resolve((int) $id)) {
            $alert = SosAlert::find((int) $id);
            NotificationDispatcher::sosResolved((int) $alert['boarder_id'], (int) $id);
        }
        header('Location: /staff/dashboard');
        exit;
    }
}
