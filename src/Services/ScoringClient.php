<?php

namespace App\Services;

/**
 * Wraps calls to the Python /score-repair endpoint.
 * Fail-closed per CLAUDE.md: if the service is unreachable, never guess a
 * priority — return medium + scoring_pending=true so staff see it wasn't
 * AI-scored yet (ARCHITECTURE.md §3.2, PHASES.md Phase 4 verification).
 */
class ScoringClient
{
    public static function score(string $description, string $category, bool $hasMedia, float $hoursSinceSubmission = 0): array
    {
        $baseUrl = getenv('SCORING_SERVICE_URL') ?: 'http://127.0.0.1:5000';
        $payload = json_encode([
            'description' => $description,
            'category' => $category,
            'has_media' => $hasMedia,
            'hours_since_submission' => $hoursSinceSubmission,
        ]);

        $ch = curl_init(rtrim($baseUrl, '/') . '/score-repair');
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
            return ['tier' => 'medium', 'score' => null, 'matched_keywords' => [], 'scoring_pending' => true];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['tier'])) {
            return ['tier' => 'medium', 'score' => null, 'matched_keywords' => [], 'scoring_pending' => true];
        }

        return [
            'tier' => $data['tier'],
            'score' => $data['score'] ?? null,
            'matched_keywords' => $data['matched_keywords'] ?? [],
            'scoring_pending' => false,
        ];
    }
}
