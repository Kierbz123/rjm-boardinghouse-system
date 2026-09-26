<?php

namespace App\Support;

/**
 * Validates and stores uploaded files. Per .claude/rules/php-conventions.md:
 * mime type + extension + size allow-list, generated filename — never the
 * user-supplied one, never stored anywhere web-executable as a script.
 */
class Uploads
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
    ];
    private const MAX_BYTES = 20 * 1024 * 1024; // 20MB

    public static function store(array $file, string $subdir): ?string
    {
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
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

        $ext = self::ALLOWED[$mime];
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destDir = __DIR__ . '/../../public/uploads/' . $subdir;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $destPath = $destDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Failed to save uploaded file.');
        }

        return '/uploads/' . $subdir . '/' . $filename;
    }
}
