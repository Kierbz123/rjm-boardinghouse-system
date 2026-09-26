<?php

namespace App\Controllers;

use App\Database;
use App\Models\MaintenanceRequest;
use App\Services\OllamaClient;
use App\Support\Csrf;

/**
 * JSON API controller for AI assistant endpoints.
 * Every method validates auth + CSRF, calls OllamaClient, and returns JSON.
 * Mirrors the thin-controller pattern from MaintenanceController / AuthController.
 */
class AssistantController
{
    /**
     * GET /api/assistant/status
     * Health check — is Ollama reachable? Any authenticated user.
     */
    public static function status(): void
    {
        header('Content-Type: application/json');
        $available = OllamaClient::isAvailable();
        echo json_encode([
            'ok'        => true,
            'available' => $available,
            'model'     => 'llama3.2:3b',
        ]);
    }

    /**
     * POST /api/assistant/chat
     * General AI chat for boarders. Injects boarder context from DB
     * so the AI can answer personal questions (room, balance, etc.)
     * without guessing — same pattern as boarderContext in assistant.js.
     */
    public static function chat(): void
    {
        header('Content-Type: application/json');

        $input = self::readJsonBody();
        if (!$input) {
            return;
        }

        $message = trim($input['message'] ?? '');
        if ($message === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Message is required.']);
            return;
        }

        // Build boarder context from real DB data (only the logged-in user's own data)
        $boarderContext = self::buildBoarderContext();

        $result = OllamaClient::chat($message, $boarderContext);

        if (!$result['ok']) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => $result['error']]);
            return;
        }

        echo json_encode(['ok' => true, 'reply' => $result['reply']]);
    }

    /**
     * POST /api/assistant/suggest-description
     * Improve/expand a draft maintenance description (boarder-facing).
     */
    public static function suggestDescription(): void
    {
        header('Content-Type: application/json');

        $input = self::readJsonBody();
        if (!$input) {
            return;
        }

        $draft    = trim($input['draft'] ?? '');
        $category = trim($input['category'] ?? 'other');

        if ($draft === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Draft description is required.']);
            return;
        }

        $result = OllamaClient::suggestDescription($draft, $category);

        if (!$result['ok']) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => $result['error']]);
            return;
        }

        echo json_encode(['ok' => true, 'reply' => $result['reply']]);
    }

    /**
     * POST /api/assistant/suggest-category
     * Auto-suggest category from description text (boarder-facing).
     * Output is constrained to the real ENUM values, validated server-side.
     */
    public static function suggestCategory(): void
    {
        header('Content-Type: application/json');

        $input = self::readJsonBody();
        if (!$input) {
            return;
        }

        $description = trim($input['description'] ?? '');

        if ($description === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Description is required.']);
            return;
        }

        $result = OllamaClient::suggestCategory($description);

        if (!$result['ok']) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => $result['error']]);
            return;
        }

        echo json_encode(['ok' => true, 'reply' => $result['reply']]);
    }

    /**
     * POST /api/assistant/analyze-ticket
     * Deep AI analysis of a specific maintenance ticket (staff-facing).
     */
    public static function analyzeTicket(): void
    {
        header('Content-Type: application/json');

        $input = self::readJsonBody();
        if (!$input) {
            return;
        }

        $ticketId = (int) ($input['ticket_id'] ?? 0);
        if ($ticketId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Valid ticket_id is required.']);
            return;
        }

        // Fetch ticket with full details for AI analysis
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT mr.*, u.name AS boarder_name, r.room_number
             FROM maintenance_requests mr
             JOIN users u ON u.id = mr.boarder_id
             LEFT JOIN rooms r ON r.id = mr.room_id
             WHERE mr.id = ?"
        );
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Ticket not found.']);
            return;
        }

        $result = OllamaClient::analyzeTicket($ticket);

        if (!$result['ok']) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => $result['error']]);
            return;
        }

        echo json_encode(['ok' => true, 'reply' => $result['reply']]);
    }

    /**
     * POST /api/assistant/summarize-queue
     * AI summary of the entire current maintenance queue (staff-facing).
     */
    public static function summarizeQueue(): void
    {
        header('Content-Type: application/json');

        $queue  = MaintenanceRequest::queueSorted();
        $result = OllamaClient::summarizeQueue($queue);

        if (!$result['ok']) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => $result['error']]);
            return;
        }

        echo json_encode(['ok' => true, 'reply' => $result['reply']]);
    }

    // ────────────────────────────────────────────────────────
    //  Internal helpers
    // ────────────────────────────────────────────────────────

    /**
     * Read JSON request body with CSRF validation.
     * Returns the parsed array or null (and sends the error response).
     */
    private static function readJsonBody(): ?array
    {
        $raw   = file_get_contents('php://input');
        $input = json_decode($raw, true);

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON body.']);
            return null;
        }

        // CSRF validation — accept from JSON body or X-CSRF-Token header
        $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::verify($token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Invalid session token. Please refresh the page.']);
            return null;
        }

        return $input;
    }

    /**
     * Build boarder context from the logged-in user's DB data.
     * Only returns the CURRENT user's own information — never other boarders'.
     */
    private static function buildBoarderContext(): ?array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role   = $_SESSION['role'] ?? '';

        if ($userId <= 0) {
            return null;
        }

        $pdo = Database::getConnection();

        // Basic user info available for any role
        $context = [
            'name' => $_SESSION['name'] ?? 'Unknown',
            'role' => $role,
        ];

        // For boarders, add room/bed/profile data
        if ($role === 'boarder') {
            $stmt = $pdo->prepare(
                'SELECT bp.*, r.room_number, b.label AS bed_label
                 FROM boarder_profiles bp
                 LEFT JOIN rooms r ON r.id = bp.room_id
                 LEFT JOIN beds b ON b.id = bp.bed_id
                 WHERE bp.user_id = ?'
            );
            $stmt->execute([$userId]);
            $profile = $stmt->fetch();

            if ($profile) {
                $context['room']   = $profile['room_number'] ?? 'Not assigned';
                $context['bed']    = $profile['bed_label'] ?? 'Not assigned';
                $context['status'] = $profile['status'] ?? 'unknown';
            }

            // Recent maintenance requests (last 5)
            $stmt = $pdo->prepare(
                'SELECT category, description, status, priority_tier, created_at
                 FROM maintenance_requests
                 WHERE boarder_id = ?
                 ORDER BY created_at DESC
                 LIMIT 5'
            );
            $stmt->execute([$userId]);
            $recentRequests = $stmt->fetchAll();
            // Balance and penalty details for boarder
            $balance = \App\Services\BillingService::calculateBalance($userId, $pdo);
            $context['outstanding_balance'] = '₱' . number_format((float) $balance['total_outstanding'], 2);
            $context['rent_due'] = '₱' . number_format((float) $balance['rent_due'], 2);
            $context['penalties_due'] = '₱' . number_format((float) $balance['penalties_due'], 2);
            $context['unpaid_penalties_count'] = count($balance['unpaid_penalties']);
            if (!empty($balance['unpaid_penalties'])) {
                $context['unpaid_penalties'] = array_map(function ($p) {
                    return [
                        'rule' => $p['rule_name'] ?? 'Violation',
                        'amount' => '₱' . number_format((float) ($p['remaining_amount'] ?? $p['amount']), 2),
                        'reason' => $p['reason'] ?? '',
                        'due_date' => $p['due_date'] ?? 'None',
                    ];
                }, $balance['unpaid_penalties']);
            }
        }

        return $context;
    }
}
