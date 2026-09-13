<?php

namespace App\Controllers;

use App\Models\Room;
use App\Models\Bed;
use App\Support\Csrf;
use RuntimeException;

class RoomController
{
    public static function index(): void
    {
        $rooms = Room::all();
        $beds = Bed::all();
        require __DIR__ . '/../Views/admin/rooms.php';
    }

    public static function create(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $roomNumber = trim((string) $_POST['room_number']);
        $floor = trim((string) ($_POST['floor'] ?? ''));
        $capacity = (int) $_POST['capacity'];
        if ($roomNumber === '' || $capacity <= 0) {
            $_SESSION['flash_error'] = 'Room number is required and capacity must be greater than zero.';
            header('Location: /admin/rooms');
            exit;
        }
        if (mb_strlen($roomNumber) > 20 || mb_strlen($floor) > 20) {
            $_SESSION['flash_error'] = 'Room number and floor must be 20 characters or fewer.';
            header('Location: /admin/rooms');
            exit;
        }
        if (Room::existsByNumber($roomNumber)) {
            $_SESSION['flash_error'] = "Room {$roomNumber} already exists.";
            header('Location: /admin/rooms');
            exit;
        }
        Room::create($roomNumber, $floor ?: null, $capacity, (float) $_POST['base_price']);
        header('Location: /admin/rooms');
        exit;
    }

    /** Closes the CRUD gap from QA-VALIDATION-REPORT.md — edit room details. */
    public static function update(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $roomNumber = trim((string) $_POST['room_number']);
        $floor = trim((string) ($_POST['floor'] ?? ''));
        $capacity = (int) $_POST['capacity'];
        if ($roomNumber === '' || $capacity <= 0) {
            $_SESSION['flash_error'] = 'Room number is required and capacity must be greater than zero.';
            header('Location: /admin/rooms');
            exit;
        }
        if (mb_strlen($roomNumber) > 20 || mb_strlen($floor) > 20) {
            $_SESSION['flash_error'] = 'Room number and floor must be 20 characters or fewer.';
            header('Location: /admin/rooms');
            exit;
        }
        if (Room::existsByNumber($roomNumber, (int) $id)) {
            $_SESSION['flash_error'] = "Room {$roomNumber} already exists.";
            header('Location: /admin/rooms');
            exit;
        }
        Room::update((int) $id, $roomNumber, $floor ?: null, $capacity, (float) $_POST['base_price']);
        header('Location: /admin/rooms');
        exit;
    }

    /** Guarded — see Room::delete() for why a room with beds can't be removed. */
    public static function delete(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        try {
            Room::delete((int) $id);
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        header('Location: /admin/rooms');
        exit;
    }

    public static function createBed(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $rawLabel = trim((string) ($_POST['label'] ?? ''));
        $count = max(1, min(50, (int) ($_POST['count'] ?? 1)));

        if ($roomId <= 0) {
            $_SESSION['flash_error'] = 'Please select a room.';
            header('Location: /admin/rooms');
            exit;
        }

        $room = Room::find($roomId);
        if (!$room) {
            $_SESSION['flash_error'] = 'Selected room does not exist.';
            header('Location: /admin/rooms');
            exit;
        }

        if ($rawLabel === '') {
            $_SESSION['flash_error'] = 'Bed label is required.';
            header('Location: /admin/rooms');
            exit;
        }

        $labels = self::generateBedLabels($roomId, $rawLabel, $count);

        foreach ($labels as $lbl) {
            if (mb_strlen($lbl) > 20) {
                $_SESSION['flash_error'] = "Bed label \"{$lbl}\" must be 20 characters or fewer.";
                header('Location: /admin/rooms');
                exit;
            }
            if (Bed::existsByLabelInRoom($roomId, $lbl)) {
                $_SESSION['flash_error'] = "Room {$room['room_number']} already has a bed labeled \"{$lbl}\".";
                header('Location: /admin/rooms');
                exit;
            }
        }

        $pdo = \App\Database::getConnection();
        $pdo->beginTransaction();
        try {
            foreach ($labels as $lbl) {
                Bed::create($roomId, $lbl);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Failed to create beds: ' . $e->getMessage();
            header('Location: /admin/rooms');
            exit;
        }

        if ($count === 1) {
            $_SESSION['flash_success'] = "Bed \"{$labels[0]}\" added to Room {$room['room_number']}.";
        } else {
            $_SESSION['flash_success'] = "Successfully added {$count} beds to Room {$room['room_number']} (" . implode(', ', $labels) . ").";
        }

        header('Location: /admin/rooms');
        exit;
    }

    /**
     * Helper to generate sequenced bed labels when adding multiple beds at once.
     * Supports:
     * - Letter series: "Bed A" -> Bed A, Bed B, Bed C...
     * - Number series: "Bed 1" -> Bed 1, Bed 2, Bed 3...
     * - Generic word: "Bed" -> Bed 1, Bed 2, Bed 3... (skipping existing numbers)
     */
    private static function generateBedLabels(int $roomId, string $rawLabel, int $count): array
    {
        if ($count <= 1) {
            return [$rawLabel];
        }

        // Pattern 1: Standalone single letter ("A") or letter after separator ("Bed A", "Bed-A", "Bed_A")
        if (preg_match('/^(?:(.*?[ \t\-_#])([A-Za-z])|([A-Za-z]))$/', $rawLabel, $m)) {
            $prefix    = !empty($m[3]) ? '' : $m[1];
            $startChar = !empty($m[3]) ? $m[3] : $m[2];
            $isUpper   = ctype_upper($startChar);
            $startCode = ord($startChar);
            $maxCode   = $isUpper ? ord('Z') : ord('z');
            $labels    = [];
            for ($i = 0; $i < $count; $i++) {
                $code = $startCode + $i;
                if ($code <= $maxCode) {
                    $labels[] = $prefix . chr($code);
                } else {
                    $labels[] = $prefix . ($i + 1);
                }
            }
            return $labels;
        }

        // Pattern 2: Ends with a number (e.g. "Bed 1", "Bunk-1")
        if (preg_match('/^(.*?[ \t\-_#]?)(\d+)$/', $rawLabel, $m)) {
            $prefix   = $m[1];
            $startNum = (int) $m[2];
            $labels   = [];
            for ($i = 0; $i < $count; $i++) {
                $labels[] = $prefix . ($startNum + $i);
            }
            return $labels;
        }

        // Pattern 3: Generic word without trailing number/letter (e.g. "Bed", "Upper Bunk")
        $prefix   = rtrim($rawLabel) . ' ';
        $startNum = 1;
        while (Bed::existsByLabelInRoom($roomId, $prefix . $startNum)) {
            $startNum++;
        }
        $labels  = [];
        $current = $startNum;
        for ($i = 0; $i < $count; $i++) {
            while (Bed::existsByLabelInRoom($roomId, $prefix . $current)) {
                $current++;
            }
            $labels[] = $prefix . $current;
            $current++;
        }
        return $labels;
    }

    /** Closes the CRUD gap from QA-VALIDATION-REPORT.md — edit a bed's label. */
    public static function updateBed(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $label = trim((string) $_POST['label']);
        if ($label === '') {
            $_SESSION['flash_error'] = 'Bed label is required.';
            header('Location: /admin/rooms');
            exit;
        }
        if (mb_strlen($label) > 20) {
            $_SESSION['flash_error'] = 'Bed label must be 20 characters or fewer.';
            header('Location: /admin/rooms');
            exit;
        }
        $bed = Bed::find((int) $id);
        if ($bed === null) {
            $_SESSION['flash_error'] = 'Bed not found.';
            header('Location: /admin/rooms');
            exit;
        }
        if (Bed::existsByLabelInRoom((int) $bed['room_id'], $label, (int) $id)) {
            $_SESSION['flash_error'] = "This room already has a bed labeled \"{$label}\".";
            header('Location: /admin/rooms');
            exit;
        }
        Bed::updateLabel((int) $id, $label);
        header('Location: /admin/rooms');
        exit;
    }

    /** Guarded — see Bed::delete() for why an occupied bed can't be removed. */
    public static function deleteBed(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        try {
            Bed::delete((int) $id);
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        header('Location: /admin/rooms');
        exit;
    }
}
