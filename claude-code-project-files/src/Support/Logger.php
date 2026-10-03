<?php

namespace App\Support;

/**
 * Minimal file logger matching the convention named in CLAUDE.md §9:
 * timestamped, leveled lines in logs/app.log. Not a dependency — swap for
 * Monolog later if the project outgrows this.
 */
class Logger
{
    private const LOG_PATH = __DIR__ . '/../../logs/app.log';

    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function warn(string $message): void
    {
        self::write('WARN', $message);
    }

    public static function error(string $message): void
    {
        self::write('ERROR', $message);
    }

    private static function write(string $level, string $message): void
    {
        $line = sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), $level, $message, PHP_EOL);
        if (@file_put_contents(self::LOG_PATH, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(rtrim($line)); // never lose a log line silently
        }
    }

    /** "juan.dela@x.ph" -> "j***@x.ph" — enough to correlate, not enough to identify. */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($local, 0, 1) . '***' . ($domain !== '' ? "@{$domain}" : '');
    }
}
