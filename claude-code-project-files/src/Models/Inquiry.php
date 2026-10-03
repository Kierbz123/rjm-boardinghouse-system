<?php

namespace App\Models;

use App\Database;

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
        $phone = $f('phone');
        $email = $f('email');
        $roomType = $f('room_type') !== '' ? $f('room_type') : 'General Inquiry';
        $moveIn = $f('move_in_date');
        $message = $f('message');
        if ($source === 'staff' && $f('staff_notes') !== '') {
            $message = trim($message . "\n\nStaff notes: " . $f('staff_notes'));
        }

        $error = match (true) {
            $name === '' || mb_strlen($name) > 150 => 'Please enter your name (150 characters max).',
            !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone) => 'Please enter a valid contact number.',
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

    public static function recent(int $limit = 10): array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM inquiries ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
