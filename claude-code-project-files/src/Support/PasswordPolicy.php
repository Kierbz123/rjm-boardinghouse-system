<?php

namespace App\Support;

/**
 * The single password rule, used by every form and by the User model itself.
 * bcrypt only reads the first 72 BYTES, so anything longer would be silently cut and a
 * wrong password with the same first 72 bytes would log in. Hence a byte limit, not a
 * character limit: accented letters and emoji take more than one byte each.
 */
final class PasswordPolicy
{
    public const MIN_CHARS = 8;
    public const MAX_BYTES = 72;

    /** A message for the person typing the password, or null when it is acceptable. */
    public static function problem(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_CHARS || strlen($password) > self::MAX_BYTES) {
            return 'The password must be 8 to 72 characters long (72 bytes at most: accented letters and emoji count as more than one).';
        }
        return null;
    }

    /** @throws \RuntimeException with a message that is safe to show */
    public static function assert(string $password): void
    {
        if (($problem = self::problem($password)) !== null) {
            throw new \RuntimeException($problem);
        }
    }
}
