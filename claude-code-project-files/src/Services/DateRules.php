<?php

namespace App\Services;

use App\Database;
use App\Models\Setting;

/**
 * What move-in and move-out dates are acceptable. The same limits are enforced three times:
 * the date pickers (min/max attributes), the controller (these functions) and the database
 * (triggers in migration 0034), so none of them can be bypassed by skipping another.
 *
 *   move-in : from the property's opening date (a setting) to 12 months from today
 *   move-out: not before the move-in date, and at most 5 years from today
 */
final class DateRules
{
    public static function openingDate(): string
    {
        return Setting::get('property_opening_date', '2000-01-01') ?? '2000-01-01';
    }

    /** Computed by the database so it agrees with the triggers to the day (month-end rules included). */
    public static function latestMoveIn(): string
    {
        return (string) Database::getConnection()->query('SELECT DATE_ADD(CURDATE(), INTERVAL 12 MONTH)')->fetchColumn();
    }

    public static function latestMoveOut(): string
    {
        return (string) Database::getConnection()->query('SELECT DATE_ADD(CURDATE(), INTERVAL 5 YEAR)')->fetchColumn();
    }

    /** A message for the admin, or null when the date is acceptable. $date is already a valid Y-m-d. */
    public static function moveInProblem(string $date): ?string
    {
        $min = self::openingDate();
        $max = self::latestMoveIn();
        return $date < $min || $date > $max ? "Move-in date must be between {$min} and {$max}." : null;
    }

    public static function moveOutProblem(string $date, ?string $moveIn): ?string
    {
        if ($moveIn !== null && $moveIn !== '' && $date < $moveIn) {
            return 'Move-out date cannot be before move-in date.';
        }
        $max = self::latestMoveOut();
        return $date > $max ? "Move-out date must be between the move-in date and {$max}." : null;
    }
}
