<?php

namespace App\Services;

/**
 * PHP-only payment verification system - no Python dependency needed.
 * Implements basic payment verification logic based on amount matching and file validation.
 */
class VerificationClient
{
    public static function verify(float $expectedAmount, float $claimedAmount, string $proofFilename): array
    {
        // Check if amounts match (allow small rounding differences)
        $amountDifference = abs($expectedAmount - $claimedAmount);
        $amountTolerance = $expectedAmount * 0.01; // 1% tolerance
        
        $amountsMatch = $amountDifference <= $amountTolerance;
        
        // Check if proof file exists and is valid
        $fileExists = !empty($proofFilename) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $proofFilename);
        $fileValid = $fileExists && self::isValidProofFile($proofFilename);
        
        // Verification logic
        if ($amountsMatch && $fileValid) {
            return [
                'status' => 'auto-matched',
                'reason' => 'Amount matches and proof file is valid'
            ];
        }
        
        if (!$amountsMatch) {
            return [
                'status' => 'flagged',
                'reason' => sprintf('Amount mismatch: expected %.2f, claimed %.2f', $expectedAmount, $claimedAmount)
            ];
        }
        
        if (!$fileValid) {
            return [
                'status' => 'flagged',
                'reason' => 'Invalid or missing proof file'
            ];
        }
        
        return [
            'status' => 'pending',
            'reason' => 'Manual review required'
        ];
    }
    
    private static function isValidProofFile(string $filename): bool
    {
        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $filename;
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        // Check file size (should be between 1KB and 10MB)
        $fileSize = filesize($filePath);
        if ($fileSize < 1024 || $fileSize > 10 * 1024 * 1024) {
            return false;
        }
        
        // Check file extension (only allow image and PDF files)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'png', 'webp'];
        $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            return false;
        }
        
        // Check if file is actually an image or PDF (basic check)
        $fileType = mime_content_type($filePath);
        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf'
        ];
        
        return in_array($fileType, $allowedMimeTypes);
    }
}
