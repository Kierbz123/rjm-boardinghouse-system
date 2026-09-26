<?php
/**
 * Creates 5 sample payment records for testing Approve/Reject functionality
 * Run: php database/seed_payments.php
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Models\Payment;
use App\Database;

// Get the boarder user ID (assuming user_id = 3 based on seed.php)
$pdo = Database::getConnection();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute(['boarder@rjm.test']);
$boarder = $stmt->fetch();

if (!$boarder) {
    echo "Error: Boarder user not found. Please run seed.php first.\n";
    exit(1);
}

$boarderId = $boarder['id'];
echo "Creating test payments for boarder ID: {$boarderId}\n";

// Create 5 sample payments with different statuses
$payments = [
    [
        'billing_period' => '2024-01',
        'expected_amount' => 3500.00,
        'claimed_amount' => 3500.00,
        'proof_path' => 'receipt_jan_2024.jpg',
        'status' => 'pending'
    ],
    [
        'billing_period' => '2024-02',
        'expected_amount' => 3500.00,
        'claimed_amount' => 3400.00,
        'proof_path' => 'receipt_feb_partial.jpg',
        'status' => 'flagged'
    ],
    [
        'billing_period' => '2024-03',
        'expected_amount' => 3500.00,
        'claimed_amount' => 3500.00,
        'proof_path' => 'receipt_mar_2024.jpg',
        'status' => 'pending'
    ],
    [
        'billing_period' => '2024-04',
        'expected_amount' => 3500.00,
        'claimed_amount' => 3500.00,
        'proof_path' => 'receipt_apr_2024.jpg',
        'status' => 'admin-approved'
    ],
    [
        'billing_period' => '2024-05',
        'expected_amount' => 3500.00,
        'claimed_amount' => 3500.00,
        'proof_path' => 'receipt_may_2024.jpg',
        'status' => 'rejected'
    ]
];

foreach ($payments as $payment) {
    $paymentId = Payment::create(
        $boarderId,
        $payment['billing_period'],
        $payment['expected_amount'],
        $payment['claimed_amount'],
        $payment['proof_path']
    );
    
    // Set the verification status if not pending
    if ($payment['status'] !== 'pending') {
        Payment::setVerification($paymentId, $payment['status'], 1); // verified by admin (user_id = 1)
    }
    
    echo "Created payment #{$paymentId}: {$payment['billing_period']} - {$payment['status']}\n";
}

echo "\n5 sample payments created successfully!\n";
echo "Ready for testing Approve/Reject functionality.\n";
