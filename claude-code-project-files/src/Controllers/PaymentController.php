<?php

namespace App\Controllers;

use App\Models\Payment;
use App\Models\Room;
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
        $billingPeriod = (string) ($_POST['billing_period'] ?? '');
        $claimed = round((float) ($_POST['claimed_amount'] ?? 0), 2);

        // A real month, no further than one month ahead (paying next month's rent early is fine).
        $nextMonth = date('Y-m', strtotime('first day of next month'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $billingPeriod) || $billingPeriod > $nextMonth || $billingPeriod < '2000-01') {
            self::backToForm('Please choose a valid billing month.');
        }
        if ($claimed <= 0 || $claimed > 1000000) {
            self::backToForm('Enter the amount shown on your receipt.');
        }
        if (empty($_FILES['proof']['tmp_name'])) {
            self::backToForm('Please attach a photo or screenshot of your receipt.');
        }
        if (Payment::hasOpenSubmission($boarderId, $billingPeriod)) {
            self::backToForm("You already have a payment for {$billingPeriod} waiting for review.");
        }

        try {
            $proofPath = Uploads::store($_FILES['proof'], 'receipts');
        } catch (\RuntimeException $e) {
            self::backToForm($e->getMessage());
        }

        // The amount due is the server's figure, never the form's. Every submission waits
        // for an admin; a mismatch is only flagged so the admin looks closer.
        $expected = BillingService::calculateBalance($boarderId)['total_outstanding'];
        $status = abs($expected - $claimed) < 0.01 ? 'pending' : 'flagged';
        $paymentId = Payment::create($boarderId, $billingPeriod, $expected, $claimed, $proofPath, $status);

        // Notify admins about new payment submission (Staff is NOT notified - financial isolation)
        NotificationDispatcher::paymentSubmitted($paymentId, $boarderId, $billingPeriod, $claimed);

        $_SESSION['flash_success'] = 'Payment submitted. An administrator will review your receipt.';
        header('Location: /portal/dashboard');
        exit;
    }

    private static function backToForm(string $error): never
    {
        $_SESSION['flash_error'] = $error;
        header('Location: /portal/payments/new');
        exit;
    }

    public static function index(): void
    {
        $payments = Payment::all();
        // Attach payment allocation breakdown to each payment
        $allocations = PaymentAllocationService::allGroupedByPayment();
        foreach ($payments as &$p) {
            $p['allocations'] = $allocations[(int) $p['id']] ?? [];
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

        if (!Payment::setVerification($paymentId, 'admin-approved', (int) $_SESSION['user_id'])) {
            $_SESSION['flash_error'] = "Payment #{$paymentId} is already approved.";
            header('Location: /admin/payments');
            exit;
        }

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
        if (!$payment || !Payment::setVerification((int) $id, 'rejected', (int) $_SESSION['user_id'])) {
            $_SESSION['flash_error'] = 'Payment not found or already rejected.';
            header('Location: /admin/payments');
            exit;
        }

        NotificationDispatcher::paymentRejected((int) $payment['boarder_id'], $payment['billing_period']);

        $wasApproved = in_array($payment['verification_status'], BillingService::APPROVED, true);
        $_SESSION['flash_success'] = $wasApproved
            ? 'Approved payment reversed. The boarder\'s balance has been recalculated.'
            : 'Payment rejected.';
        header('Location: /admin/payments');
        exit;
    }
}
