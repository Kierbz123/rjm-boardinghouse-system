<?php

namespace App\Support;

/**
 * Reads the visitor's real address and scheme when the app sits behind the
 * online-demo tunnel (start-online.bat), where every request arrives from
 * 127.0.0.1 over plain HTTP.
 *
 * Forwarded headers are believed only when the connection itself comes from an
 * address listed in the TRUSTED_PROXIES environment variable (comma-separated,
 * empty by default). With it empty, behaviour is exactly as before: the
 * connecting address is the visitor and HTTPS is whatever the server reports.
 */
class TrustedProxy
{
    public static function clientIp(array $server): ?string
    {
        $remote = $server['REMOTE_ADDR'] ?? null;
        $trusted = self::trustedAddresses();
        if ($remote === null || !in_array($remote, $trusted, true)) {
            return $remote;
        }
        // The right-most address that is not one of our own proxies is the one the proxy saw.
        $forwarded = array_reverse(array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''))));
        foreach ($forwarded as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false && !in_array($candidate, $trusted, true)) {
                return $candidate;
            }
        }
        return $remote;
    }

    public static function isHttps(array $server): bool
    {
        if (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') {
            return true;
        }
        if (!in_array((string) ($server['REMOTE_ADDR'] ?? ''), self::trustedAddresses(), true)) {
            return false;
        }
        // Anything that came through the tunnel counts, even if the visitor typed http://:
        // the browser then refuses the Secure cookie instead of sending it in the clear.
        return strtolower(trim((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https'
            || !empty($server['HTTP_X_FORWARDED_FOR']);
    }

    /** @return string[] */
    private static function trustedAddresses(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) getenv('TRUSTED_PROXIES')))));
    }
}
