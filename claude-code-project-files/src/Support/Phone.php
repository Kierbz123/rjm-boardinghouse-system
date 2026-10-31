<?php

namespace App\Support;

/**
 * The one place that decides what a phone number is: digits only.
 *
 * Every phone field in the app (boarder contact and emergency contact, staff phone, inquiry
 * phone) used to have its own rule, and the two boarder fields had none beyond a length cap, so
 * letters went straight into the database. Forms mark these inputs `data-digits` so the browser
 * strips anything else as it is typed; this class is the server-side half of the same rule.
 *
 * problem() judges what was submitted, digits() is what gets stored. Validate before normalising:
 * "call me" has no digits, and a request that skipped the browser's stripping must be refused
 * rather than quietly clearing a number the resident still has.
 */
final class Phone
{
    public const MIN_DIGITS = 7;
    public const MAX_DIGITS = 15;

    /** Everything that is not a digit, removed. A number that was never typed stays ''. */
    public static function digits(mixed $raw): string
    {
        return is_scalar($raw) ? (string) preg_replace('/\D/', '', (string) $raw) : '';
    }

    /**
     * Why this submitted number is unacceptable, or null when it is fine. Pass the raw input, not
     * digits(): only the raw value distinguishes a field left blank from one holding no digits.
     * A blank field is only a problem where the form requires a number. $label names the field in
     * the message, so a form with two numbers on it says which one it means.
     */
    public static function problem(mixed $raw, bool $required = false, string $label = 'contact number'): ?string
    {
        if (!is_scalar($raw) || trim((string) $raw) === '') {
            return $required || !is_scalar($raw) ? "Please enter a {$label}." : null;
        }
        $length = strlen(self::digits($raw));
        return $length >= self::MIN_DIGITS && $length <= self::MAX_DIGITS
            ? null
            : "Enter the {$label} as digits only, " . self::MIN_DIGITS . ' to ' . self::MAX_DIGITS . ' of them (for example 09171234567).';
    }
}
