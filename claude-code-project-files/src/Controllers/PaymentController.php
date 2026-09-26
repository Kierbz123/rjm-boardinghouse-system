<?php

namespace App\Controllers;

use App\Models\Payment;
use App\Models\Room;
use App\Services\VerificationClient;
use App\Services\BillingService;
use App\Services\PaymentAllocationService;
use App\Services\NotificationDispatcher;
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

        // Canonical balance details for the boarder
        $balanceDetails = BillingService::calculateBalance($userId, $pdo);

        require __DIR__ . '/../Views/portal/payment_new.php';
    }

    /** Feature 7 — Proof of Payment Verifier. Fail-closed if the verification service fails. */
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

        // Notify admins about new payment submission (Staff is NOT notified - financial isolation)
        NotificationDispatcher::paymentSubmitted($paymentId, $boarderId, $billingPeriod, $claimed);

        // If auto-matched, dispatch immediate combined payment notification to boarder
        if ($result['status'] === 'auto-matched') {
            $allocations = PaymentAllocationService::getAllocationsForPayment($paymentId);
            $rentAllocated = 0.0;
            $settledPenalties = [];
            foreach ($allocations as $al) {
                if ($al['allocation_type'] === 'rent') {
                    $rentAllocated += (float) $al['amount'];
                } elseif ($al['allocation_type'] === 'penalty') {
                    $settledPenalties[] = $al;
                }
            }
            $balance = BillingService::calculateBalance($boarderId);
            NotificationDispatcher::combinedPaymentApproved($boarderId, $claimed, $rentAllocated, $settledPenalties, (float) $balance['total_outstanding']);
            $_SESSION['flash_success'] = 'Payment auto-matched and verified successfully!';
        } else {
            $_SESSION['flash_info'] = 'Payment proof submitted and awaiting admin verification.';
        }

        header('Location: /portal/dashboard');
        exit;
    }

    public static function index(): void
    {
        $payments = Payment::all();
        // Attach payment allocation breakdown to each payment
        $pdo = Database::getConnection();
        foreach ($payments as &$p) {
            $p['allocations'] = PaymentAllocationService::getAllocationsForPayment((int) $p['id'], $pdo);
        }
        unset($p);

        require __DIR__ . '/../Views/admin/payments.php';
    }

    public static function approve(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }

        $paymentId = (int) $id;
        $payment = Payment::find($paymentId);
        if (!$payment) {
            $_SESSION['flash_error'] = 'Payment record not found.';
            header('Location: /admin/payments');
            exit;
        }

        Payment::setVerification($paymentId, 'admin-approved', (int) $_SESSION['user_id']);

        $boarderId = (int) $payment['boarder_id'];
        $allocations = PaymentAllocationService::getAllocationsForPayment($paymentId);
        $rentAllocated = 0.0;
        $settledPenalties = [];
        foreach ($allocations as $al) {
            if ($al['allocation_type'] === 'rent') {
                $rentAllocated += (float) $al['amount'];
            } elseif ($al['allocation_type'] === 'penalty') {
                $settledPenalties[] = $al;
            }
        }
        $balance = BillingService::calculateBalance($boarderId);
        $remainingBalance = (float) $balance['total_outstanding'];

        NotificationDispatcher::combinedPaymentApproved(
            $boarderId,
            (float) $payment['claimed_amount'],
            $rentAllocated,
            $settledPenalties,
            $remainingBalance
        );

        $_SESSION['flash_success'] = 'Payment approved and allocated successfully. Boarder balance updated to ₱' . number_format($remainingBalance, 2) . '.';
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
        $payment = Payment::find((int) $id);
        Payment::setVerification((int) $id, 'rejected', (int) $_SESSION['user_id']);

        if ($payment) {
            NotificationDispatcher::paymentRejected((int) $payment['boarder_id'], $payment['billing_period']);
        }

        $_SESSION['flash_info'] = 'Payment rejected.';
        header('Location: /admin/payments');
        exit;
    }
}
