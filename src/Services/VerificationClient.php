<?php

namespace App\Services;

/**
 * Wraps calls to the Python /verify-payment endpoint.
 * Fail-closed per CLAUDE.md: an unreachable verifier must never auto-approve
 * a payment — it's left "pending" for manual admin review instead.
 */
class VerificationClient
{
    public static function verify(float $expectedAmount, float $claimedAmount, string $proofFilename): array
    {
        $baseUrl = getenv('SCORING_SERVICE_URL') ?: 'http://127.0.0.1:5000';
        $payload = json_encode([
            'expected_amount' => $expectedAmount,
            'claimed_amount' => $claimedAmount,
            'proof_filename' => $proofFilename,
        ]);

        $ch = curl_init(rtrim($baseUrl, '/') . '/verify-payment');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $response = curl_exec($ch);
        $failed = curl_errno($ch) !== 0;
        curl_close($ch);

        if ($failed || $response === false) {
            return ['status' => 'pending', 'reason' => 'Verification service unreachable — held for manual review.'];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['status'])) {
            return ['status' => 'pending', 'reason' => 'Verification service returned an unexpected response.'];
        }

        return ['status' => $data['status'], 'reason' => $data['reason'] ?? ''];
    }
}
