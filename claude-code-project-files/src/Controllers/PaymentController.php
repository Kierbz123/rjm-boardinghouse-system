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

    /**
     * Feature 7 — the resident uploads a GCash, Maya or bank-transfer receipt. It is
     * always saved as "pending": an administrator checks every receipt, and only an
     * approved payment touches the balance (oldest unpaid month first, extra = credit).
     */
    public static function create(): void
    {
        if (Uploads::requestTooLarge()) {
            self::backToForm(Uploads::tooLargeMessage());
        }
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }

        $boarderId = (int) $_SESSION['user_id'];
        $method = (string) ($_POST['payment_method'] ?? '');
        $reference = trim((string) ($_POST['reference_number'] ?? ''));
        $claimed = round((float) ($_POST['claimed_amount'] ?? 0), 2);

        if (!isset(Payment::METHODS[$method])) {
            self::backToForm('Choose how you paid: GCash, Maya or bank transfer.');
        }
        if (mb_strlen($reference) > 50 || !preg_match('/^[A-Za-z0-9 \-]*$/', $reference)) {
            self::backToForm('The reference number can only have letters, numbers, spaces and dashes (up to 50).');
        }
        if ($claimed <= 0 || $claimed > 1000000) {
            self::backToForm('Enter the amount shown on your receipt.');
        }
        if (Payment::hasOpenSubmission($boarderId)) {
            self::backToForm('You already have a receipt waiting for review. You will be notified once an administrator checks it.');
        }

        try {
            $proofPath = Uploads::store($_FILES['proof'] ?? [], 'receipts');
        } catch (\RuntimeException $e) {
            self::backToForm($e->getMessage());
        }
        if ($proofPath === null) {
            self::backToForm('Please attach a photo or screenshot of your receipt.');
        }

        // The amount owed is the server's figure, never the form's. The payment is filed
        // under the oldest unpaid month, which is the month an approval settles first.
        $balance = BillingService::calculateBalance($boarderId);
        $period = array_key_first($balance['unpaid_rent']) ?? date('Y-m');
        $paymentId = Payment::create($boarderId, $period, $balance['total_outstanding'], $claimed, $proofPath,
            $method, $reference !== '' ? $reference : null);

        // Admins only — staff have no access to payments.
        NotificationDispatcher::paymentSubmitted($paymentId, $boarderId, $period, $claimed);

        // Shown once as a pop-up on the next page (see portal/payment_new.php).
        $_SESSION['payment_submitted'] = [
            'id' => $paymentId,
            'amount' => $claimed,
            'method' => Payment::methodLabel($method),
        ];
        header('Location: /portal/payments/new');
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
        $balance = BillingService::calculateBalance($boarderId);
        NotificationDispatcher::paymentApproved(
            $boarderId,
            $payment,
            PaymentAllocationService::getAllocationsForPayment($paymentId),
            $balance
        );
        $remainingBalance = (float) $balance['total_outstanding'];

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
        // The resident is told why, so they can fix the receipt and upload it again.
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            $_SESSION['flash_error'] = 'Give a reason (3 to 255 characters) so the resident knows what to fix.';
            header('Location: /admin/payments');
            exit;
        }
        $payment = Payment::find((int) $id);
        if (!$payment || !Payment::setVerification((int) $id, 'rejected', (int) $_SESSION['user_id'], $reason)) {
            $_SESSION['flash_error'] = 'Payment not found or already rejected.';
            header('Location: /admin/payments');
            exit;
        }

        $wasApproved = in_array($payment['verification_status'], BillingService::APPROVED, true);
        NotificationDispatcher::paymentRejected((int) $payment['boarder_id'], $payment, $reason, $wasApproved);
        $_SESSION['flash_success'] = $wasApproved
            ? 'Approved payment reversed. The boarder\'s balance has been recalculated.'
            : 'Payment rejected.';
        header('Location: /admin/payments');
        exit;
    }
}
