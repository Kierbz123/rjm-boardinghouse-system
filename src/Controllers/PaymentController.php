<?php

namespace App\Controllers;

use App\Models\Payment;
use App\Models\Room;
use App\Services\VerificationClient;
use App\Support\Csrf;
use App\Support\Uploads;
use App\Database;

class PaymentController
{
    public static function newForm(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT bp.*, r.room_number, r.base_price, b.label AS bed_label
            FROM boarder_profiles bp
            LEFT JOIN rooms r ON r.id = bp.room_id
            LEFT JOIN beds b ON b.id = bp.bed_id
            WHERE bp.user_id = ?
        ');
        $stmt->execute([$userId]);
        $boarder = $stmt->fetch() ?: null;

        $stmt = $pdo->prepare('SELECT * FROM payments WHERE boarder_id = ? ORDER BY created_at DESC LIMIT 5');
        $stmt->execute([$userId]);
        $recentPayments = $stmt->fetchAll();

        require __DIR__ . '/../Views/portal/payment_new.php';
    }

    /** Feature 7 — Proof of Payment Verifier. Fail-closed if the Python service is down. */
    public static function create(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }

        $boarderId = (int) $_SESSION['user_id'];
        $billingPeriod = (string) $_POST['billing_period'];
        $expected = (float) $_POST['expected_amount'];
        $claimed = (float) $_POST['claimed_amount'];

        if ($expected <= 0 || $claimed <= 0) {
            $_SESSION['flash_error'] = 'Amounts must be greater than zero.';
            header('Location: /portal/payments/new');
            exit;
        }

        $proofPath = null;
        if (!empty($_FILES['proof']['tmp_name'])) {
            try {
                $proofPath = Uploads::store($_FILES['proof'], 'receipts');
            } catch (\RuntimeException $e) {
                $_SESSION['flash_error'] = $e->getMessage();
                header('Location: /portal/payments/new');
                exit;
            }
        }

        $paymentId = Payment::create($boarderId, $billingPeriod, $expected, $claimed, $proofPath);

        $result = VerificationClient::verify($expected, $claimed, $proofPath ?? '');
        Payment::setVerification($paymentId, $result['status']);

        header('Location: /portal/dashboard');
        exit;
    }

    public static function index(): void
    {
        $payments = Payment::all();
        require __DIR__ . '/../Views/admin/payments.php';
    }

    public static function approve(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        Payment::setVerification((int) $id, 'admin-approved', (int) $_SESSION['user_id']);
        header('Location: /admin/payments');
        exit;
    }

    public static function reject(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        Payment::setVerification((int) $id, 'rejected', (int) $_SESSION['user_id']);
        header('Location: /admin/payments');
        exit;
    }
}
