<?php

namespace App\Models;

use App\Database;
use App\Services\BillingService;

/**
 * A room's electricity bill (every room has its own submeter), split equally among
 * the residents staying in the room on the day the admin enters it. Each share is due
 * on that resident's next rent due date, so it is paid alongside the rent.
 */
class ElectricityBill
{
    /**
     * @return array{id:int, share:float, due_dates:array<int,string>} due_dates is boarder id => Y-m-d
     * @throws \RuntimeException when nobody is staying in the room
     */
    public static function create(int $roomId, float $amount, string $label, ?int $createdBy): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            // Sharing the bill: living in the room now (not pending, not moved out).
            $stmt = $pdo->prepare("SELECT user_id, move_in_date FROM boarder_profiles
                                   WHERE room_id = ? AND status IN ('active', 'on_notice') ORDER BY user_id FOR UPDATE");
            $stmt->execute([$roomId]);
            $residents = $stmt->fetchAll();
            if (!$residents) {
                throw new \RuntimeException('Nobody is staying in that room, so there is no one to bill.');
            }
            // A double-click or a second entry of the same bill must not charge the room twice.
            // (The row locks above make two requests for one room take turns.)
            $dup = $pdo->prepare('SELECT COUNT(*) FROM electricity_bills WHERE room_id = ? AND label = ?');
            $dup->execute([$roomId, $label]);
            if ((int) $dup->fetchColumn() > 0) {
                throw new \RuntimeException("A bill for \"{$label}\" is already entered for that room. Remove it first to enter it again.");
            }
            // ponytail: equal shares rounded to the centavo, so a bill that does not divide
            // evenly is a centavo or two off in total. Give one resident the remainder if that matters.
            $share = round($amount / count($residents), 2);

            $pdo->prepare('INSERT INTO electricity_bills (room_id, label, amount, created_by) VALUES (?, ?, ?, ?)')
                ->execute([$roomId, $label, $amount, $createdBy]);
            $billId = (int) $pdo->lastInsertId();

            $insert = $pdo->prepare('INSERT INTO electricity_charges (bill_id, boarder_id, amount, due_date) VALUES (?, ?, ?, ?)');
            $dueDates = [];
            foreach ($residents as $r) {
                $boarderId = (int) $r['user_id'];
                $dueDates[$boarderId] = BillingService::nextDueDate($r['move_in_date'])->format('Y-m-d');
                $insert->execute([$billId, $boarderId, $share, $dueDates[$boarderId]]);
                BillingService::syncBalance($boarderId, $pdo);
            }

            $pdo->commit();
            return ['id' => $billId, 'share' => $share, 'due_dates' => $dueDates];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Removes a bill entered by mistake, with every resident's share of it. Anything
     * already paid toward a share goes to the resident's next charge, or becomes credit.
     */
    public static function delete(int $billId): bool
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            // Same order as create() locks residents in, so the two cannot deadlock each other.
            $stmt = $pdo->prepare('SELECT boarder_id FROM electricity_charges WHERE bill_id = ? ORDER BY boarder_id');
            $stmt->execute([$billId]);
            $boarderIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // The shares go with the bill (ON DELETE CASCADE).
            $delete = $pdo->prepare('DELETE FROM electricity_bills WHERE id = ?');
            $delete->execute([$billId]);
            foreach ($boarderIds as $boarderId) {
                BillingService::syncBalance((int) $boarderId, $pdo);
            }

            $pdo->commit();
            return $delete->rowCount() > 0;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** The latest bills, each with its room number, how many residents shared it and their share. */
    public static function recent(): array
    {
        return Database::getConnection()->query('
            SELECT eb.id, eb.label, eb.amount, eb.created_at, r.room_number,
                   COUNT(ec.id) AS residents, MAX(ec.amount) AS share
            FROM electricity_bills eb
            JOIN rooms r ON r.id = eb.room_id
            LEFT JOIN electricity_charges ec ON ec.bill_id = eb.id
            GROUP BY eb.id, eb.label, eb.amount, eb.created_at, r.room_number
            ORDER BY eb.id DESC
            LIMIT 20
        ')->fetchAll();
    }

    /** @return array<int,int> room id => residents who would share a bill entered now */
    public static function residentsPerRoom(): array
    {
        return array_map('intval', Database::getConnection()->query(
            "SELECT room_id, COUNT(*) FROM boarder_profiles
             WHERE room_id IS NOT NULL AND status IN ('active', 'on_notice') GROUP BY room_id"
        )->fetchAll(\PDO::FETCH_KEY_PAIR));
    }
}
