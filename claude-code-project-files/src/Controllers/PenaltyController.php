<?php

namespace App\Controllers;

use App\Models\PenaltyRule;
use App\Models\Penalty;
use App\Models\Payment;
use App\Services\PenaltyEngine;
use App\Services\BillingService;
use App\Services\NotificationDispatcher;
use App\Support\Csrf;
use App\Database;

class PenaltyController
{
    public static function index(): void
    {
        $rules = PenaltyRule::all();
        $activeRules = PenaltyRule::allActive();
        $penalties = Penalty::allWithDetails();

        $pdo = Database::getConnection();
        // Fetch active boarders with current room info and cached balance
        $activeBoarders = $pdo->query('
            SELECT bp.user_id, bp.outstanding_balance, u.name, u.email, r.room_number, b.label AS bed_label
            FROM boarder_profiles bp
            JOIN users u ON u.id = bp.user_id
            LEFT JOIN rooms r ON r.id = bp.room_id
            LEFT JOIN beds b ON b.id = bp.bed_id
            WHERE bp.status = "active"
            ORDER BY r.room_number ASC, u.name ASC
        ')->fetchAll();

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
        $_SESSION['flash_info'] = "Penalty rule '{$name}' created.";
        header('Location: /admin/penalty-rules');
        exit;
    }

    /**
     * Issues a manual penalty to a specific boarder.
     * Authorized for Admin and Staff.
     * Enforces immediate transactional balance addition.
     */
    public static function issuePenalty(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }

        $currentRole = $_SESSION['role'] ?? '';
        $redirectUrl = ($currentRole === 'staff') ? '/staff/dashboard' : '/admin/penalty-rules';
        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo !== '' && preg_match('#^/admin/boarders/\d+$#', $returnTo)) {
            $redirectUrl = $returnTo;
        }

        $boarderId = (int) ($_POST['boarder_id'] ?? 0);
        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));

        if ($dueDate === '') {
            // Default due date to 30 days from now
            $dueDate = (new \DateTimeImmutable('+30 days'))->format('Y-m-d');
        } elseif (!($dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate)) || $dt->format('Y-m-d') !== $dueDate) {
            $_SESSION['flash_error'] = 'Invalid due date. Please pick a real calendar date.';
            header("Location: {$redirectUrl}");
            exit;
        }
        if (mb_strlen($reason) > 255) {
            $_SESSION['flash_error'] = 'Reason must be 255 characters or fewer.';
            header("Location: {$redirectUrl}");
            exit;
        }

        if ($boarderId <= 0 || $ruleId <= 0 || $amount <= 0) {
            $_SESSION['flash_error'] = 'Please select a valid boarder, rule, and specify an amount greater than zero.';
            header("Location: {$redirectUrl}");
            exit;
        }

        $pdo = Database::getConnection();

        // Validate boarder existence
        $stmt = $pdo->prepare('SELECT u.id, u.name FROM users u JOIN boarder_profiles bp ON bp.user_id = u.id WHERE u.id = ?');
        $stmt->execute([$boarderId]);
        $boarder = $stmt->fetch();
        if (!$boarder) {
            $_SESSION['flash_error'] = 'Selected boarder profile not found.';
            header("Location: {$redirectUrl}");
            exit;
        }

        // Validate penalty rule existence
        $stmt = $pdo->prepare('SELECT * FROM penalty_rules WHERE id = ?');
        $stmt->execute([$ruleId]);
        $rule = $stmt->fetch();
        if (!$rule) {
            $_SESSION['flash_error'] = 'Selected penalty rule not found.';
            header("Location: {$redirectUrl}");
            exit;
        }

        // Staff may issue a rule's penalty but not set its amount; only admins can.
        if ($currentRole !== 'admin') {
            $amount = (float) $rule['amount'];
        }

        $issuedBy = (int) ($_SESSION['user_id'] ?? 0);

        try {
            Penalty::createManual($boarderId, $ruleId, $amount, $reason, $dueDate, $issuedBy);

            // Fetch newly recalculated balance
            $balance = BillingService::calculateBalance($boarderId, $pdo);
            $newBalance = (float) $balance['total_outstanding'];

            // Dispatch notification to boarder
            NotificationDispatcher::manualPenaltyIssued($boarderId, $rule['name'], $amount, $reason, $dueDate, $newBalance);

            $_SESSION['flash_info'] = "Penalty of ₱" . number_format($amount, 2) . " ('{$rule['name']}') successfully issued to {$boarder['name']}. Current balance updated to ₱" . number_format($newBalance, 2) . ".";
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Failed to issue penalty: ' . $e->getMessage();
        }

        header("Location: {$redirectUrl}");
        exit;
    }

    /**
     * Manual administrative settlement / override for a penalty.
     * Authorized for Admin only.
     */
    public static function markPaid(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }

        $penaltyId = (int) $id;
        $redirectUrl = '/admin/penalty-rules';
        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo !== '' && preg_match('#^/admin/boarders/\d+$#', $returnTo)) {
            $redirectUrl = $returnTo;
        }

        $result = Penalty::markPaid($penaltyId, (int) ($_SESSION['user_id'] ?? 0));

        if (!$result['ok']) {
            $_SESSION['flash_error'] = $result['error'] ?? 'Could not mark penalty as paid.';
            header('Location: ' . $redirectUrl);
            exit;
        }

        $boarderId = (int) $result['boarder_id'];
        $newBalance = (float) (BillingService::calculateBalance($boarderId)['total_outstanding'] ?? 0.0);

        NotificationDispatcher::manualPenaltyOverrideSettled($boarderId, $result['rule_name'], (float) $result['amount'], $newBalance);

        $_SESSION['flash_info'] = "Penalty #{$penaltyId} ('{$result['rule_name']}') marked as paid via administrative override. Boarder balance updated to ₱" . number_format($newBalance, 2) . ".";
        header('Location: ' . $redirectUrl);
        exit;
    }

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
        $_SESSION['flash_info'] = 'Penalty rule amount updated.';
        header('Location: /admin/penalty-rules');
        exit;
    }

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

    public static function runCheck(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $applied = PenaltyEngine::runCheck();
        $new = count(array_filter($applied, fn ($a) => $a['new']));
        $_SESSION['flash_info'] = $applied
            ? "{$new} new late fee(s); " . (count($applied) - $new) . ' existing fee(s) updated to today\'s days late.'
            : 'No late fees due: no unpaid month is past its due date (the 5th, or 30 days after move-in for new residents).';
        header('Location: /admin/penalty-rules');
        exit;
    }

    public static function sendRentDueReminders(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $pdo = Database::getConnection();
        $active = $pdo->query("SELECT user_id FROM boarder_profiles WHERE status IN ('active', 'on_notice')")->fetchAll();
        $sent = 0;
        foreach ($active as $b) {
            $boarderId = (int) $b['user_id'];
            $balance = BillingService::calculateBalance($boarderId, $pdo);
            // Remind about the oldest unpaid month, with its own due date (new residents get 30 days).
            $period = array_key_first($balance['unpaid_rent']);
            if ($period !== null) {
                NotificationDispatcher::rentDue($boarderId, $period, $balance['unpaid_rent'][$period], $balance['rent_due_dates'][$period]);
                $sent++;
            }
        }
        $_SESSION['flash_info'] = "Rent-due reminder sent to {$sent} boarder(s).";
        header('Location: /admin/penalty-rules');
        exit;
    }
}
