<?php

namespace App\Support;

/** What a person is told when something failed. */
final class Flash
{
    /**
     * Our own "why not" messages (a RuntimeException we threw ourselves) are shown as written.
     * Anything else, including every database error (PDOException is a RuntimeException too),
     * is logged with its details and replaced by $fallback: users never see SQL.
     */
    public static function error(\Throwable $e, string $fallback): string
    {
        if ($e instanceof \RuntimeException && !$e instanceof \PDOException) {
            return $e->getMessage();
        }
        Logger::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return $fallback;
    }
}
