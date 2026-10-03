<?php

namespace App\Services;

/**
 * Feature 1 — maintenance priority scoring. Local, rule-based (no external service):
 * category base weight + keyword weights + a bonus for attached photo/video.
 *
 * This is the only keyword list; the boarder form's live preview asks the server
 * (MaintenanceController::scorePreview) so what they see is what staff will get.
 */
class ScoringClient
{
    /** Matched as whole words, plus plural/past/-ing forms ("leak" → leaks, leaked, leaking). */
    private const KEYWORD_WEIGHTS = [
        // Life-safety
        'gas leak' => 45, 'electrocution' => 45, 'electrocuted' => 45, 'explosion' => 45, 'ceiling collapse' => 45,
        'exposed wire' => 40, 'live wire' => 40, 'fire' => 40, 'spark' => 40,
        'smoke' => 35, 'shock' => 35, 'collapse' => 35, 'structural damage' => 35, 'flood' => 32,

        // Security & medical
        'robbery' => 45, 'assault' => 45, 'weapon' => 45, 'injured' => 45, 'injury' => 45, 'bleeding' => 45,
        'unconscious' => 45, 'ambulance' => 45, 'emergency' => 45, 'stolen' => 45, 'theft' => 45, 'thief' => 45,
        'break-in' => 40, 'burglary' => 40, 'intruder' => 40, 'forced entry' => 40, 'hospital' => 40, 'fainted' => 40,
        'medical' => 35, 'threat' => 35, 'hurt' => 35, 'security' => 25, 'cctv' => 20, 'camera' => 15,

        // Building damage
        'door destroyed' => 40, 'window broken' => 35, 'smashed' => 30, 'crash' => 30,

        // Service outages
        'no power' => 20, 'blackout' => 20, 'no water' => 20, 'burst' => 20, 'leak' => 20, 'overflow' => 20,
        'clog' => 20, 'clogged' => 20, 'broken lock' => 20, 'broken door' => 20, 'cannot lock' => 20, 'no lights' => 12,

        // Everyday wear
        'broken' => 10, 'cracked' => 10, 'stuck' => 10, 'smell' => 10, 'odor' => 10, 'drip' => 10, 'damaged' => 10,

        // Cosmetic
        'loose handle' => 4, 'loose screw' => 4, 'flicker' => 4, 'bulb' => 3, 'paint' => 3, 'stain' => 3,
        'squeak' => 2, 'squeaky' => 2, 'scratch' => 2, 'cosmetic' => 2,
    ];

    private const CATEGORY_BASE_WEIGHT = [
        'electrical' => 20,
        'plumbing' => 15,
        'structural' => 25,
        'appliance' => 8,
        'other' => 5,
    ];

    private const MEDIA_BONUS = 10;

    /** @return array{score: float, tier: string, matched_keywords: string[], matches: list<array{keyword:string, weight:int}>, scoring_pending: bool} */
    public static function score(string $description, string $category, bool $hasMedia): array
    {
        $matches = [];
        $keywordScore = 0;
        foreach (self::KEYWORD_WEIGHTS as $keyword => $weight) {
            $pattern = '/\b' . str_replace(' ', '\s+', preg_quote($keyword, '/')) . '(?:s|es|ed|ing)?\b/i';
            if (preg_match($pattern, $description)) {
                $matches[] = ['keyword' => $keyword, 'weight' => $weight];
                $keywordScore += $weight;
            }
        }

        $base = self::CATEGORY_BASE_WEIGHT[$category] ?? 5;
        $score = (float) min($base + $keywordScore + ($hasMedia ? self::MEDIA_BONUS : 0), 100);

        return [
            'tier' => self::getTierForScore($score),
            'score' => $score,
            'matched_keywords' => array_column($matches, 'keyword'),
            'matches' => $matches,
            'scoring_pending' => false,
        ];
    }

    private static function getTierForScore(float $score): string
    {
        return match (true) {
            $score >= 70 => 'critical',
            $score >= 45 => 'high',
            $score >= 20 => 'medium',
            default => 'low',
        };
    }
}
