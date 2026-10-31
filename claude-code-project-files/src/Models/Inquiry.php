<?php

namespace App\Models;

use App\Database;
use App\Support\Phone;

/** Prospective-resident room inquiries, from the public website or logged by staff. */
class Inquiry
{
    private const MAX_WEBSITE_PER_IP_PER_HOUR = 5;

    /**
     * Validates and stores one inquiry. Returns ['ok' => true, 'id' => int, 'name' => string,
     * 'phone' => string, 'room_type' => string] or ['ok' => false, 'error' => string].
     */
    public static function submit(array $in, string $source, ?int $submittedBy, ?string $ip): array
    {
        $f = fn (string $k) => trim((string) ($in[$k] ?? ''));
        $name = $f('name');
        $phoneRaw = $in['phone'] ?? '';
        $phone = Phone::digits($phoneRaw);
        $email = $f('email');
        $roomType = $f('room_type') !== '' ? $f('room_type') : 'General Inquiry';
        $moveIn = $f('move_in_date');
        $message = $f('message');
        if ($source === 'staff' && $f('staff_notes') !== '') {
            $message = trim($message . "\n\nStaff notes: " . $f('staff_notes'));
        }

        $error = match (true) {
            $name === '' || mb_strlen($name) > 150 => 'Please enter your name (150 characters max).',
            ($phoneProblem = Phone::problem($phoneRaw, true)) !== null => $phoneProblem,
            $email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) => 'Please enter a valid email address.',
            mb_strlen($roomType) > 100 => 'Please choose a room type.',
            $moveIn !== '' && (!($d = \DateTimeImmutable::createFromFormat('!Y-m-d', $moveIn)) || $d->format('Y-m-d') !== $moveIn) => 'Please enter a valid move-in date.',
            mb_strlen($message) > 2000 => 'Your message is too long (2,000 characters max).',
            default => null,
        };
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        $pdo = Database::getConnection();
        if ($source === 'website' && $ip !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE ip_address = ? AND created_at > NOW() - INTERVAL 1 HOUR');
            $stmt->execute([$ip]);
            if ((int) $stmt->fetchColumn() >= self::MAX_WEBSITE_PER_IP_PER_HOUR) {
                return ['ok' => false, 'error' => 'We already received several inquiries from you. Please call us instead.'];
            }
        }

        $pdo->prepare('INSERT INTO inquiries (name, phone, email, room_type, move_in_date, message, source, submitted_by, ip_address)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$name, $phone, $email ?: null, $roomType, $moveIn ?: null, $message ?: null, $source, $submittedBy, $ip]);

        return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'name' => $name, 'phone' => $phone, 'room_type' => $roomType];
    }

    public static function stats(): array
    {
        return Database::getConnection()->query(
            'SELECT COUNT(*) AS total, COALESCE(SUM(DATE(created_at) = CURDATE()), 0) AS today FROM inquiries'
        )->fetch();
    }

    public const STATUSES = ['new', 'contacted', 'closed'];

    public static function find(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT i.*, u.name AS submitted_by_name, c.name AS converted_user_name
             FROM inquiries i LEFT JOIN users u ON u.id = i.submitted_by LEFT JOIN users c ON c.id = i.converted_user_id
             WHERE i.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Records that this inquiry became $userId's account and closes it. Called inside the
     * account-creation transaction; the row lock plus the UNIQUE key mean a double submit
     * cannot turn one inquiry into two accounts.
     */
    public static function markConverted(int $id, int $userId): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT converted_user_id FROM inquiries WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: throw new \RuntimeException('That inquiry no longer exists.');
        if ($row['converted_user_id'] !== null) {
            throw new \RuntimeException('This inquiry has already been registered as a boarder.');
        }
        $pdo->prepare("UPDATE inquiries SET converted_user_id = ?, converted_at = NOW(), status = 'closed' WHERE id = ?")
            ->execute([$userId, $id]);
    }

    public static function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }
        Database::getConnection()->prepare('UPDATE inquiries SET status = ? WHERE id = ?')->execute([$status, $id]);
        return true;
    }

    public static function recent(int $limit = 10): array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM inquiries ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
