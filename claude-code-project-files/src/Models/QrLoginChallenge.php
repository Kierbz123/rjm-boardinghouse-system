<?php

namespace App\Models;

use App\Database;

class QrLoginChallenge
{
    public const TTL_SECONDS = 120;

    public static function create(string $creatorSessionId): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO qr_login_challenges (token, creator_session_id, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$token, $creatorSessionId, $expiresAt]);

        $row = self::findByToken($token);
        if ($row === null) {
            throw new \RuntimeException('Failed to persist QR login challenge.');
        }
        return $row;
    }

    public static function findByToken(string $token): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM qr_login_challenges WHERE token = ?'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function publicStatus(array $row): string
    {
        if ($row['status'] === 'consumed') {
            return 'consumed';
        }
        if (strtotime((string) $row['expires_at']) <= time() && $row['status'] === 'pending') {
            return 'expired';
        }
        return (string) $row['status'];
    }

    public static function approve(string $token, int $userId): bool
    {
        $stmt = Database::getConnection()->prepare(
            'UPDATE qr_login_challenges
             SET status = \'approved\', user_id = ?
             WHERE token = ?
               AND status = \'pending\'
               AND expires_at > NOW()'
        );
        $stmt->execute([$userId, $token]);
        return $stmt->rowCount() === 1;
    }

    public static function consume(string $token, string $creatorSessionId): ?array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT * FROM qr_login_challenges WHERE token = ? FOR UPDATE'
            );
            $stmt->execute([$token]);
            $row = $stmt->fetch();
            if (
                !$row
                || $row['status'] !== 'approved'
                || (int) $row['user_id'] <= 0
                || !hash_equals((string) $row['creator_session_id'], $creatorSessionId)
            ) {
                $pdo->rollBack();
                return null;
            }

            $update = $pdo->prepare(
                'UPDATE qr_login_challenges
                 SET status = \'consumed\', consumed_at = NOW()
                 WHERE token = ? AND status = \'approved\''
            );
            $update->execute([$token]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                return null;
            }
            $pdo->commit();
            $row['status'] = 'consumed';
            return $row;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
