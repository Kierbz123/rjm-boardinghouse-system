<?php

namespace App\Controllers;

use App\Models\PenaltyRule;
use App\Models\Payment;
use App\Services\PenaltyEngine;
use App\Services\NotificationDispatcher;
use App\Support\Csrf;

class PenaltyController
{
    public static function index(): void
    {
        $rules = PenaltyRule::all();
        require __DIR__ . '/../Views/admin/penalty_rules.php';
    }

    public static function createRule(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $amount = (float) $_POST['amount'];
        $name = trim((string) $_POST['name']);
        $conditionType = trim((string) $_POST['condition_type']);
        if ($name === '' || $conditionType === '' || $amount <= 0) {
            $_SESSION['flash_error'] = 'Name and type are required, and amount must be greater than zero.';
            header('Location: /admin/penalty-rules');
            exit;
        }
        if (mb_strlen($name) > 150 || mb_strlen($conditionType) > 100) {
            $_SESSION['flash_error'] = 'Name must be 150 characters or fewer, type 100 or fewer.';
            header('Location: /admin/penalty-rules');
            exit;
        }
        PenaltyRule::create($name, $conditionType, $amount);
        header('Location: /admin/penalty-rules');
        exit;
    }

    /** Closes the CRUD gap from QA-VALIDATION-REPORT.md — edit a rule's amount
     *  without deleting and recreating it (which would lose its history). */
    public static function updateAmount(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $amount = (float) $_POST['amount'];
        if ($amount <= 0) {
            $_SESSION['flash_error'] = 'Amount must be greater than zero.';
            header('Location: /admin/penalty-rules');
            exit;
        }
        PenaltyRule::updateAmount((int) $id, $amount);
        header('Location: /admin/penalty-rules');
        exit;
    }

    /** Toggle, not delete — see PenaltyRule::setActive() for why. */
    public static function toggleActive(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $active = ($_POST['active'] ?? '') === '1';
        PenaltyRule::setActive((int) $id, $active);
        header('Location: /admin/penalty-rules');
        exit;
    }

    /** Manual trigger — no cron guarantee on localhost, per ARCHITECTURE.md §4.5. */
    public static function runCheck(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $applied = PenaltyEngine::runCheck();
        $_SESSION['flash_info'] = count($applied) . ' penalty(ies) applied.';
        header('Location: /admin/penalty-rules');
        exit;
    }

    /** Feature 11 — rent-due reminders. Manual trigger for the same reason as runCheck() above. */
    public static function sendRentDueReminders(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $billingPeriod = date('Y-m');
        $pdo = \App\Database::getConnection();
        $active = $pdo->query("SELECT user_id FROM boarder_profiles WHERE status = 'active'")->fetchAll();
        $verifiedIds = Payment::verifiedBoarderIdsForPeriod($billingPeriod);
        $sent = 0;
        foreach ($active as $b) {
            $boarderId = (int) $b['user_id'];
            if (!in_array($boarderId, $verifiedIds, true)) {
                NotificationDispatcher::rentDue($boarderId, $billingPeriod);
                $sent++;
            }
        }
        $_SESSION['flash_info'] = "Rent-due reminder sent to {$sent} boarder(s).";
        header('Location: /admin/penalty-rules');
        exit;
    }
}
