<?php

namespace App\Services;

/**
 * PHP-only scoring system - no Python dependency needed.
 * Implements the same keyword-based priority scoring logic as the Python service.
 */
class ScoringClient
{
    private const KEYWORD_WEIGHTS = [
        // Safety emergencies (highest priority)
        'gas leak' => 45,
        'exposed wire' => 40,
        'electrocut' => 40,
        'fire' => 40,
        'smoke' => 32,
        'gas' => 35,
        'sparking' => 32,
        'ceiling collapse' => 45,
        'flooding' => 32,
        'flood' => 30,
        
        // Security and theft (critical)
        'stole' => 45,
        'stolen' => 45,
        'theft' => 45,
        'thief' => 45,
        'robbery' => 45,
        'break-in' => 40,
        'burglary' => 40,
        'intruder' => 40,
        'forced entry' => 40,
        'assault' => 45,
        'attack' => 40,
        'threat' => 35,
        'weapon' => 45,
        'cctv' => 20,
        'camera' => 15,
        'security' => 25,
        
        // Injury and medical emergencies (critical)
        'injured' => 45,
        'injury' => 45,
        'hurt' => 35,
        'bleeding' => 45,
        'blood' => 40,
        'unconscious' => 45,
        'fainted' => 40,
        'medical' => 35,
        'emergency' => 45,
        'ambulance' => 45,
        'hospital' => 40,
        
        // Vehicle and structural damage (high)
        'crash' => 40,
        'crashed' => 40,
        'collision' => 40,
        'accident' => 40,
        'vehicle' => 25,
        'car' => 25,
        'door destroyed' => 40,
        'window broken' => 35,
        'smashed' => 30,
        'structural damage' => 35,
        
        // Building issues (medium/high)
        'no water' => 20,
        'leak' => 15,
        'broken lock' => 20,
        'no lights' => 12,
        
        // Minor issues (low)
        'squeaky' => 2,
        'cosmetic' => 2,
        'scratch' => 2,
        'loose handle' => 4,
        'paint' => 3,
    ];

    private const CATEGORY_BASE_WEIGHT = [
        'electrical' => 20,
        'plumbing' => 15,
        'structural' => 25,
        'appliance' => 8,
        'other' => 5,
    ];

    private const MEDIA_BONUS = 10;
    private const MAX_TIME_DECAY_BONUS = 20;
    private const TIME_DECAY_PER_HOUR = 0.5;

    public static function score(string $description, string $category, bool $hasMedia, float $hoursSinceSubmission = 0): array
    {
        $text = strtolower($description);
        $matched = [];
        $keywordScore = 0;

        // Check for matching keywords
        foreach (self::KEYWORD_WEIGHTS as $keyword => $weight) {
            if (str_contains($text, $keyword)) {
                $matched[] = $keyword;
                $keywordScore += $weight;
            }
        }

        $base = self::CATEGORY_BASE_WEIGHT[$category] ?? 5;
        $mediaBonus = $hasMedia ? self::MEDIA_BONUS : 0;
        $decay = min($hoursSinceSubmission * self::TIME_DECAY_PER_HOUR, self::MAX_TIME_DECAY_BONUS);

        $score = min($base + $keywordScore + $mediaBonus + $decay, 100);
        $tier = self::getTierForScore($score);

        return [
            'tier' => $tier,
            'score' => $score,
            'matched_keywords' => $matched,
            'scoring_pending' => false,
        ];
    }

    private static function getTierForScore(float $score): string
    {
        if ($score >= 70) {
            return 'critical';
        }
        if ($score >= 45) {
            return 'high';
        }
        if ($score >= 20) {
            return 'medium';
        }
        return 'low';
    }
}
