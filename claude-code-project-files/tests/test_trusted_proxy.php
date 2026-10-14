<?php
/**
 * Online-demo tunnel support: forwarded headers are believed only from a trusted
 * proxy address, so a visitor cannot fake their address or a Secure connection.
 * Reads and writes no data. Run via tests/run.php.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Support\TrustedProxy;

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . "\n";
    $failures += $ok ? 0 : 1;
}

$viaTunnel = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'];
$outsider = ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'];

echo "== No trusted proxy set (normal local use) ==\n";
putenv('TRUSTED_PROXIES');
check(TrustedProxy::clientIp($viaTunnel) === '127.0.0.1', 'forwarded address is ignored');
check(TrustedProxy::isHttps($viaTunnel) === false, 'forwarded https is ignored');
check(TrustedProxy::isHttps(['REMOTE_ADDR' => '127.0.0.1', 'HTTPS' => 'on']) === true, 'real HTTPS is still seen');
check(TrustedProxy::clientIp([]) === null, 'no address stays null');

echo "== Tunnel on this machine is trusted ==\n";
putenv('TRUSTED_PROXIES=127.0.0.1,::1');
check(TrustedProxy::clientIp($viaTunnel) === '203.0.113.9', 'visitor address comes from the tunnel');
check(TrustedProxy::isHttps($viaTunnel) === true, 'https comes from the tunnel');
check(
    TrustedProxy::isHttps(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'http']) === true,
    'a tunnel visitor on plain http still gets a Secure cookie'
);
check(TrustedProxy::isHttps(['REMOTE_ADDR' => '127.0.0.1']) === false, 'direct local visit is not treated as https');
check(
    TrustedProxy::clientIp(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '10.9.9.9, 203.0.113.9']) === '203.0.113.9',
    'a visitor-supplied address in front of the real one is not used'
);
check(
    TrustedProxy::clientIp(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'not-an-ip']) === '127.0.0.1',
    'garbage header falls back to the connecting address'
);
check(TrustedProxy::clientIp(['REMOTE_ADDR' => '127.0.0.1']) === '127.0.0.1', 'direct local visit keeps its own address');
check(TrustedProxy::clientIp($outsider) === '198.51.100.7', 'headers from an untrusted address are ignored');
check(TrustedProxy::isHttps($outsider) === false, 'https claim from an untrusted address is ignored');

echo "\n" . ($failures === 0 ? 'ALL PASSED' : "{$failures} FAILED") . "\n";
exit($failures === 0 ? 0 : 1);
