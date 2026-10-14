<?php

namespace App;

/**
 * Single shared PDO connection helper.
 * Per .claude/rules/php-conventions.md: every DB access goes through
 * Database::getConnection() — never a new PDO(...) scattered per file,
 * and always prepared statements, never string-built SQL.
 *
 * Settings come from real environment variables first, then from the project's .env file
 * (APP_ENV_FILE overrides its location; point it at a file that does not exist, e.g. "none", to switch the file off - an empty value cannot be used because Windows has no empty environment variables). A login that
 * came only from .env and that the server refuses (a stale password) falls back to XAMPP's
 * default root login, loudly: it is logged, describe() reports it, and the admin dashboard
 * and the online launcher complain until bin/setup-db-user.php has been run.
 */
class Database
{
    private static ?\PDO $instance = null;
    private static bool $envLoaded = false;
    /** @var string[] names that were set from the .env file rather than the real environment */
    private static array $fromFile = [];
    private static array $info = ['user' => null, 'insecure' => false, 'fellBack' => false];

    /** Fills environment variables that are not already set from the .env file. */
    public static function loadEnvFile(): void
    {
        if (self::$envLoaded) {
            return;
        }
        self::$envLoaded = true;
        $path = getenv('APP_ENV_FILE');
        if ($path === false) {
            $path = dirname(__DIR__) . '/.env';
        }
        if ($path === '' || !is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (!preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=\s*(.*?)\s*$/', $line, $m) || getenv($m[1]) !== false) {
                continue;
            }
            putenv($m[1] . '=' . trim($m[2], "\"'"));
            self::$fromFile[] = $m[1];
        }
    }

    public static function getConnection(): \PDO
    {
        if (self::$instance === null) {
            self::loadEnvFile();
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('DB_NAME') ?: 'rjm_boardinghouse';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $options = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $fellBack = false;
            try {
                self::$instance = new \PDO($dsn, $user, $pass, $options);
            } catch (\PDOException $e) {
                $fromFile = in_array('DB_USER', self::$fromFile, true) || in_array('DB_PASS', self::$fromFile, true);
                if (!$fromFile || (int) ($e->errorInfo[1] ?? 0) !== 1045) {
                    throw $e;
                }
                \App\Support\Logger::warn("The database login for {$user} from .env was refused; using root without a password. Run bin/setup-db-user.php.");
                [$user, $pass, $fellBack] = ['root', '', true];
                self::$instance = new \PDO($dsn, $user, $pass, $options);
            }
            self::$info = ['user' => $user, 'insecure' => $user === 'root' && $pass === '', 'fellBack' => $fellBack];
            // NOW()/CURDATE() must agree with PHP's date() (set in autoload.php).
            self::$instance->exec("SET time_zone = '" . date('P') . "'");
            // XAMPP's default mode silently stores '' for a bad ENUM and truncates long text.
            // Strict mode turns those into errors so bad data never lands quietly.
            self::$instance->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }

        return self::$instance;
    }

    /** Drops the shared connection so the next call opens a fresh one (long test runs after a server restart). */
    public static function reconnect(): void
    {
        self::$instance = null;
    }

    /** @return array{user:?string, insecure:bool, fellBack:bool} how this process is logged in to the database */
    public static function describe(): array
    {
        self::getConnection();
        return self::$info;
    }
}
