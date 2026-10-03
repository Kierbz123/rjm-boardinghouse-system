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
    private const MAX_BYTES = 20 * 1024 * 1024; // 20MB

    /** @return string|null the app path stored in the DB, e.g. /uploads/receipts/<hex>.jpg */
    public static function store(array $file, string $subdir): ?string
    {
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if (!in_array($subdir, self::SUBDIRS, true)) {
            throw new \InvalidArgumentException("Unknown upload folder {$subdir}");
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('File is too large (max 20MB).');
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
