<?php
/**
 * Shared helpers for the audit-fix test files (tests/test_probe_*.php, tests/test_wp*.php).
 * Run through tests/run.php only: it starts the private server(s), builds the _test
 * database and exports TEST_BASE_URL, TEST_BASE_URL_2 and the TEST_*_PASSWORD values.
 *
 * Conventions
 *  - check()        a hard assertion; a failure makes the file (and the run) fail.
 *  - knownDefect()  an assertion for a defect that is still open. Prints [KNOWN] and does NOT
 *                   fail the run. The commit that fixes the defect turns it into check().
 *  - Every test creates its own data with unique names (uniq()), so files can run in any
 *    order against one shared database.
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Database;

$LIB_BASE = getenv('TEST_BASE_URL') ?: exit("Run through tests/run.php\n");
$LIB_FAILURES = 0;
$LIB_KNOWN = 0;
$LIB_PASSES = 0;

register_shutdown_function(function () {
    global $LIB_FAILURES, $LIB_KNOWN, $LIB_PASSES;
    echo "\n  {$LIB_PASSES} passed, {$LIB_FAILURES} failed, {$LIB_KNOWN} known open defect(s)\n";
    if ($LIB_FAILURES > 0) {
        exit(1);
    }
});

function check(bool $ok, string $label, string $detail = ''): void
{
    global $LIB_FAILURES, $LIB_PASSES;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . (!$ok && $detail !== '' ? "  -- {$detail}" : '') . "\n";
    $ok ? $LIB_PASSES++ : $LIB_FAILURES++;
}

/** An assertion about a defect that is still open: reported, not failing. */
function knownDefect(string $id, bool $ok, string $label, string $detail = ''): void
{
    global $LIB_KNOWN, $LIB_PASSES;
    if ($ok) {
        echo "  [PASS] {$label}\n";
        $LIB_PASSES++;
        return;
    }
    echo "  [KNOWN] {$id} {$label}" . ($detail !== '' ? "  -- {$detail}" : '') . "\n";
    $LIB_KNOWN++;
}

function section(string $title): void
{
    echo "\n== {$title} ==\n";
}

function uniq(): string
{
    static $n = 0;
    return substr(bin2hex(random_bytes(4)), 0, 6) . (++$n);
}

function db(): PDO
{
    return Database::getConnection();
}

function q(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function q1(string $sql, array $params = [])
{
    $rows = q($sql, $params);
    return $rows ? array_values($rows[0])[0] : null;
}

function pw(string $role): string
{
    return (string) getenv('TEST_' . strtoupper($role) . '_PASSWORD');
}

function png(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
}

function tmpFileWith(string $content, string $ext): string
{
    $path = sys_get_temp_dir() . '/rjmtest_' . bin2hex(random_bytes(4)) . '.' . $ext;
    file_put_contents($path, $content);
    return $path;
}

class Res
{
    public ?string $loc;

    public function __construct(public int $code, public string $body, public array $headers)
    {
        $this->loc = $headers['location'] ?? null;
    }

    public function has(string $needle): bool
    {
        return str_contains($this->body, $needle);
    }
}

class Client
{
    public string $jar;

    public function __construct(public string $name = 'c', public ?string $base = null)
    {
        $this->base ??= $GLOBALS['LIB_BASE'];
        $this->jar = sys_get_temp_dir() . "/rjmtest_jar_{$name}_" . getmypid() . '_' . bin2hex(random_bytes(3)) . '.txt';
        @unlink($this->jar);
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    public function req(string $method, string $path, $fields = null, array $headers = [], bool $follow = false, int $timeout = 60): Res
    {
        $ch = curl_init($this->base . $path);
        $seen = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$seen) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $seen[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ]);
        if ($fields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return new Res($code, $body, $seen);
    }

    public function get(string $path, bool $follow = false): Res
    {
        return $this->req('GET', $path, null, [], $follow);
    }

    public function token(): string
    {
        $page = $this->get('/profile', true);
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $page->body, $m);
        return $m[1] ?? '';
    }

    /** POST a form with a valid CSRF token (unless $csrf is false). */
    public function post(string $path, array $fields = [], bool $csrf = true): Res
    {
        if ($csrf) {
            $fields['csrf_token'] = $this->token();
        }
        return $this->req('POST', $path, $fields);
    }

    public function postJson(string $path, array $json, bool $csrf = true): Res
    {
        if ($csrf) {
            $json['csrf_token'] = $this->token();
        }
        return $this->req('POST', $path, json_encode($json), ['Content-Type: application/json', 'Accept: application/json']);
    }

    public function login(string $email, string $password): Res
    {
        $page = $this->get('/login', true);
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $page->body, $m);
        return $this->req('POST', '/login', ['email' => $email, 'password' => $password, 'csrf_token' => $m[1] ?? '']);
    }

    /** Visible text of the page a redirect points to (flash messages), with scripts removed. */
    public function flash(Res $r): string
    {
        if (($r->loc ?? '') === '') {
            return '';
        }
        $html = preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $this->get($r->loc, true)->body);
        return html_entity_decode(trim(preg_replace('/\s+/', ' ', strip_tags($html))), ENT_QUOTES);
    }
}

function flashHas(Client $c, Res $r, string $needle): bool
{
    return stripos($c->flash($r), $needle) !== false;
}

/** A logged-in client for one of the three seeded accounts. */
function seededClient(string $role, ?string $base = null): Client
{
    q('DELETE FROM login_attempts');
    $c = new Client($role, $base);
    $c->login("{$role}@rjm.test", pw($role));
    return $c;
}

/**
 * Creates a room, optionally with beds, through the admin UI. Returns ['id'=>, 'number'=>, 'beds'=>[ids]].
 */
function makeRoom(Client $admin, int $capacity = 2, float $price = 3000, int $beds = 2): array
{
    $number = 'T' . uniq();
    $admin->post('/admin/rooms', ['room_number' => $number, 'floor' => '1', 'capacity' => $capacity, 'base_price' => $price]);
    $id = (int) q1('SELECT id FROM rooms WHERE room_number = ?', [$number]);
    if ($beds > 0) {
        $admin->post('/admin/beds', ['room_id' => $id, 'label' => 'Bed A', 'count' => $beds]);
    }
    return ['id' => $id, 'number' => $number, 'beds' => array_map('intval', array_column(q('SELECT id FROM beds WHERE room_id = ? ORDER BY id', [$id]), 'id'))];
}

/**
 * Creates a resident through the admin UI, optionally on a bed, optionally active since $daysAgo days.
 * Returns ['id'=>, 'email'=>, 'password'=>, 'client'=> logged-in Client].
 */
function makeResident(Client $admin, ?int $bedId = null, ?int $daysAgo = null, string $password = 'Tmp#Resident1'): array
{
    $email = 'res' . uniq() . '@t.test';
    $fields = ['name' => 'Test Resident', 'email' => $email, 'password' => $password];
    if ($bedId) {
        $fields['bed_id'] = $bedId;
    }
    $admin->post('/admin/boarders', $fields);
    $id = (int) q1('SELECT id FROM users WHERE email = ?', [$email]);
    if ($daysAgo !== null) {
        $admin->post("/admin/boarders/{$id}/info", ['name' => 'Test Resident', 'email' => $email, 'status' => 'active']);
        $admin->post("/admin/boarders/{$id}/dates", ['move_in_date' => date('Y-m-d', strtotime("-{$daysAgo} days")), 'move_out_date' => '']);
    }
    $client = new Client('res');
    $client->login($email, $password);
    return ['id' => $id, 'email' => $email, 'password' => $password, 'client' => $client];
}

/** Uploads a receipt as the resident; returns the new payment id (0 on refusal). */
function submitReceipt(Client $resident, float $amount, string $ref = 'REF1'): int
{
    $before = (int) q1('SELECT COALESCE(MAX(id), 0) FROM payments');
    $resident->req('POST', '/portal/payments', [
        'csrf_token' => $resident->token(), 'payment_method' => 'gcash', 'reference_number' => $ref,
        'claimed_amount' => (string) $amount, 'proof' => new CURLFile(tmpFileWith(png(), 'png'), 'image/png', 'r.png'),
    ]);
    $id = (int) q1('SELECT COALESCE(MAX(id), 0) FROM payments');
    return $id > $before ? $id : 0;
}
