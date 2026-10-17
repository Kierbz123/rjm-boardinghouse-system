<?php

namespace App\Support;

/**
 * The single password rule, used by every form and by the User model itself.
 *
 * Length (NIST SP 800-63B-4, where the password is the only sign-in factor): at least 15 characters,
 * counted as Unicode code points, and no "must contain a symbol" rules. bcrypt only reads the first
 * 72 BYTES, so anything longer would be silently cut and a wrong password with the same first 72 bytes
 * would log in. Hence a byte limit on top: accented letters and emoji take more than one byte each.
 *
 * Guessability: a password made mostly of common words, keyboard runs, the boarding house's name or
 * the person's own name / e-mail is refused, and the message says why. There is no breached-password
 * list: the app makes no outbound calls and ships no corpus.
 */
final class PasswordPolicy
{
    public const MIN_CHARS = 15;
    public const MAX_BYTES = 72;

    /** What is left after removing these (and the account's own name / e-mail, spaces, punctuation) must be 8+ characters. */
    private const GUESSABLE = [
        'rjm', 'boardinghouse', 'boarding', 'house', 'boarder', 'staff', 'admin', 'password', 'passw0rd',
        'letmein', 'welcome', 'iloveyou', 'qwerty', 'dragon', 'monkey', 'sunshine', 'princess', 'football',
        'secret', 'changeme', 'temporary', 'manila', 'philippines', 'pilipinas',
    ];
    private const RUNS = ['0123456789', 'abcdefghijklmnopqrstuvwxyz', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm'];

    /**
     * A message for the person typing the password, or null when it is acceptable.
     * @param list<string> $context the account's name and e-mail, which make poor passwords
     */
    public static function problem(string $password, array $context = []): ?string
    {
        // A NUL byte would end bcrypt's input early on PHP < 8.4: the same silent truncation as length.
        if (mb_strlen($password) < self::MIN_CHARS || strlen($password) > self::MAX_BYTES || str_contains($password, "\0")) {
            return 'The password must be at least 15 characters and no more than 72 bytes (accented letters and emoji use more than one byte). A short phrase of unrelated words works well.';
        }
        $plain = mb_strtolower($password);
        if (count(array_unique(mb_str_split($plain))) < 5) {
            return 'That password repeats the same few characters, so it is easy to guess. Try a short phrase of unrelated words.';
        }
        $rest = str_replace(self::guessableParts($context), '', $plain);
        if (mb_strlen((string) preg_replace('/[\s\p{P}]/u', '', $rest)) < 8) { // emoji and symbols still count
            return "That password is mostly common words, keyboard runs, the boarding house's name or the person's own name or e-mail, so it is easy to guess. Try a short phrase of unrelated words.";
        }
        return null;
    }

    /** @throws \RuntimeException with a message that is safe to show */
    public static function assert(string $password, array $context = []): void
    {
        if (($problem = self::problem($password, $context)) !== null) {
            throw new \RuntimeException($problem);
        }
    }

    /** @return list<string> longest first, so "boardinghouse" is removed before "boarding" */
    private static function guessableParts(array $context): array
    {
        $parts = self::GUESSABLE;
        foreach (self::RUNS as $run) {
            foreach ([$run, strrev($run)] as $seq) {
                // 4+, not 3: three-letter runs ("rst", "wer") sit inside ordinary words like "first" and "power"
                for ($len = 4, $n = strlen($seq); $len <= $n; $len++) {
                    for ($i = 0; $i + $len <= $n; $i++) {
                        $parts[] = substr($seq, $i, $len);
                    }
                }
            }
        }
        foreach ($context as $value) {
            // "Ana Cruz" / "ana.cruz@mail.com" -> ana, cruz, mail, com: every piece of 3+ characters
            foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $value)) ?: [] as $piece) {
                if (mb_strlen($piece) >= 3) {
                    $parts[] = $piece;
                }
            }
        }
        $parts = array_values(array_unique($parts));
        usort($parts, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        return $parts;
    }
}
