<?php

namespace App\Support;

use App\Database;

/**
 * Validates, stores and serves uploaded files. Per .claude/rules/php-conventions.md:
 * mime type + extension + size allow-list, generated filename — never the
 * user-supplied one.
 *
 * Files live in storage/uploads (outside the web root) and are only served through
 * serve(), which checks who is asking: payment receipts are financial records and
 * repair photos can show inside someone's room.
 */
class Uploads
{
    private const DIR = __DIR__ . '/../../storage/uploads';
    private const SUBDIRS = ['receipts', 'maintenance'];
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
    ];
    /**
     * The app's own cap per file. 20 MB holds any phone photo (2–8 MB) and roughly
     * 10–20 seconds of 1080p phone video, or 30–40 seconds at 720p. PHP's own
     * upload_max_filesize / post_max_size can be lower (2 MB / 8 MB on a stock
     * install), so start-system.bat raises them to match; maxBytes() is the real limit.
     */
    public const MAX_MB = 20;

    /** The real per-file limit: the smallest of the app cap and PHP's two settings. */
    public static function maxBytes(): int
    {
        return min(self::MAX_MB * 1024 * 1024, self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size'));
    }

    /** maxBytes() in whole megabytes, for messages and form hints. */
    public static function maxMb(): int
    {
        return max(1, intdiv(self::maxBytes(), 1024 * 1024));
    }

    /**
     * True when the request body was bigger than post_max_size. PHP then drops the
     * whole form (including the CSRF token), so check this before anything else to
     * show "file too large" instead of a misleading "session expired".
     */
    public static function requestTooLarge(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && empty($_POST) && empty($_FILES)
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > self::iniBytes('post_max_size');
    }

    public static function tooLargeMessage(): string
    {
        return 'That file is too large. The limit is ' . self::maxMb() . ' MB — try a photo or a shorter video.';
    }

    /**
     * @return string|null the app path stored in the DB, e.g. /uploads/receipts/<hex>.jpg,
     *                     or null when no file was chosen
     * @throws \RuntimeException with a message safe to show the user
     */
    public static function store(array $file, string $subdir): ?string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new \RuntimeException(self::tooLargeMessage());
        }
        if ($error === UPLOAD_ERR_PARTIAL) {
            throw new \RuntimeException('The upload was interrupted. Please try again.');
        }
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            throw new \RuntimeException('The file could not be uploaded. Please try again.');
        }
        if (!in_array($subdir, self::SUBDIRS, true)) {
            throw new \InvalidArgumentException("Unknown upload folder {$subdir}");
        }
        if ($file['size'] > self::maxBytes()) {
            throw new \RuntimeException(self::tooLargeMessage());
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Unsupported file type.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        $destDir = self::DIR . '/' . $subdir;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)) {
            throw new \RuntimeException('Failed to save uploaded file.');
        }

        return '/uploads/' . $subdir . '/' . $filename;
    }

    /** Deletes a file stored by store() when the record it belonged to was never saved. */
    public static function discard(?string $appPath): void
    {
        if ($appPath !== null && preg_match('#^/uploads/(receipts|maintenance)/([a-f0-9]{32}\.(?:jpg|png|webp|mp4))$#', $appPath, $m)) {
            @unlink(self::DIR . "/{$m[1]}/{$m[2]}");
        }
    }

        /** GET /uploads/{subdir}/{file} — streams the file if the logged-in user may see it, else 404. */
    public static function serve(string $subdir, string $filename): void
    {
        $path = "/uploads/{$subdir}/{$filename}";
        $file = self::DIR . "/{$subdir}/{$filename}";
        if (
            !in_array($subdir, self::SUBDIRS, true)
            || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|mp4)$/', $filename)
            || !is_file($file)
            || !self::mayView($subdir, $path)
        ) {
            http_response_code(404); // same answer for "missing" and "not yours"
            echo 'Not found';
            return;
        }

        $mime = array_search(pathinfo($filename, PATHINFO_EXTENSION), self::ALLOWED, true);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        readfile($file);
    }

    /** "20M" / "2G" / "8388608" → bytes. */
    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        $number = (int) $value;
        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number > 0 ? $number : PHP_INT_MAX,
        };
    }

    private static function mayView(string $subdir, string $path): bool
    {
        $role = $_SESSION['role'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($role === 'admin' || ($role === 'staff' && $subdir === 'maintenance')) {
            return true;
        }
        if ($role !== 'boarder') {
            return false;
        }
        $sql = $subdir === 'receipts'
            ? 'SELECT COUNT(*) FROM payments WHERE proof_path = ? AND boarder_id = ?'
            : 'SELECT COUNT(*) FROM maintenance_requests WHERE media_path = ? AND boarder_id = ?';
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute([$path, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
