<?php

namespace App\Controllers;

use App\Models\Room;
use App\Models\Bed;
use App\Models\RoomType;
use App\Models\ElectricityBill;
use App\Services\NotificationDispatcher;
use App\Support\Csrf;
use RuntimeException;

class RoomController
{
    public static function index(): void
    {
        $rooms = Room::all();
        $beds = Bed::all();
        $roomTypes = \App\Models\RoomType::all();
        $electricityBills = ElectricityBill::recent();
        $residentsPerRoom = ElectricityBill::residentsPerRoom();
        require __DIR__ . '/../Views/admin/rooms.php';
    }

    /**
     * The admin enters a room's electricity bill; it is split equally among the residents
     * staying in that room, each share due with that resident's next rent (ElectricityBill).
     */
    public static function createElectricityBill(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $room = Room::find((int) ($_POST['room_id'] ?? 0));
        $label = trim((string) ($_POST['label'] ?? ''));
        $raw = $_POST['amount'] ?? null;
        if (!$room) {
            self::backWithError('Please select a room.');
        }
        if ($label === '' || mb_strlen($label) > 60) {
            self::backWithError('Say what the bill covers, for example "September 2026" (up to 60 characters).');
        }
        if (!is_numeric($raw) || (float) $raw < 1 || (float) $raw > 1000000) {
            self::backWithError('Enter the amount of the room\'s bill, from ₱1 up.');
        }

        try {
            $bill = ElectricityBill::create((int) $room['id'], round((float) $raw, 2), $label, (int) $_SESSION['user_id']);
        } catch (RuntimeException $e) {
            self::backWithError($e->getMessage());
        }
        foreach ($bill['due_dates'] as $boarderId => $dueDate) {
            NotificationDispatcher::electricityBilled($boarderId, $label, $bill['share'], $dueDate);
        }

        $count = count($bill['due_dates']);
        $_SESSION['flash_success'] = "Room {$room['room_number']} electricity ({$label}): ₱" . number_format((float) $raw, 2)
            . " split among {$count} resident" . ($count === 1 ? '' : 's') . ', ₱' . number_format($bill['share'], 2)
            . ' each, due with their next rent.';
        header('Location: /admin/rooms');
        exit;
    }

    /** Removes a bill entered by mistake; every resident's balance is recalculated. */
    public static function deleteElectricityBill(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        if (!ElectricityBill::delete((int) $id)) {
            self::backWithError('That electricity bill was not found.');
        }
        $_SESSION['flash_success'] = 'Electricity bill removed. The residents\' balances have been recalculated.';
        header('Location: /admin/rooms');
        exit;
    }

    private static function backWithError(string $error): never
    {
        $_SESSION['flash_error'] = $error;
        header('Location: /admin/rooms');
        exit;
    }

    public static function create(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $roomNumber = trim((string) ($_POST['room_number'] ?? ''));
        $floor = trim((string) ($_POST['floor'] ?? ''));
        $type = self::roomTypeOrRedirect();
        $capacity = $type ? (int) $type['capacity'] : (int) ($_POST['capacity'] ?? 0);
        if ($roomNumber === '' || $capacity <= 0) {
            $_SESSION['flash_error'] = 'Room number is required and capacity must be greater than zero.';
            header('Location: /admin/rooms');
            exit;
        }
        if ($capacity > 50) {
            $_SESSION['flash_error'] = 'A room holds at most 50 beds.';
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
        $basePrice = $type ? RoomType::ratePerBed($type) : self::basePriceOrRedirect();
        Room::create($roomNumber, $floor ?: null, $capacity, $basePrice, $type ? (int) $type['id'] : null);
        $_SESSION['flash_success'] = "Room {$roomNumber} added.";
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
        $roomNumber = trim((string) ($_POST['room_number'] ?? ''));
        $floor = trim((string) ($_POST['floor'] ?? ''));
        $type = self::roomTypeOrRedirect();
        $capacity = $type ? (int) $type['capacity'] : (int) ($_POST['capacity'] ?? 0);
        if ($roomNumber === '' || $capacity <= 0) {
            $_SESSION['flash_error'] = 'Room number is required and capacity must be greater than zero.';
            header('Location: /admin/rooms');
            exit;
        }
        if ($capacity > 50) {
            $_SESSION['flash_error'] = 'A room holds at most 50 beds.';
            header('Location: /admin/rooms');
            exit;
        }
        if (mb_strlen($roomNumber) > 20 || mb_strlen($floor) > 20) {
            $_SESSION['flash_error'] = 'Room number and floor must be 20 characters or fewer.';
            header('Location: /admin/rooms');
            exit;
        }
        if (Room::find((int) $id) === null) {
            $_SESSION['flash_error'] = 'Room not found.';
            header('Location: /admin/rooms');
            exit;
        }
        if (Room::existsByNumber($roomNumber, (int) $id)) {
            $_SESSION['flash_error'] = "Room {$roomNumber} already exists.";
            header('Location: /admin/rooms');
            exit;
        }
        $basePrice = $type ? RoomType::ratePerBed($type) : self::basePriceOrRedirect();
        $bedCount = count(array_filter(Bed::all(), fn ($b) => (int) $b['room_id'] === (int) $id));
        if ($capacity < $bedCount) {
            $_SESSION['flash_error'] = $type
                ? "This room already has {$bedCount} beds and {$type['name']} holds {$capacity}. Remove beds first, or choose another type."
                : "This room already has {$bedCount} beds; capacity cannot be lower. Remove beds first.";
            header('Location: /admin/rooms');
            exit;
        }
        Room::update((int) $id, $roomNumber, $floor ?: null, $capacity, $basePrice, $type ? (int) $type['id'] : null);
        $_SESSION['flash_success'] = "Room {$roomNumber} updated.";
        header('Location: /admin/rooms');
        exit;
    }

    /**
     * Empty means "no type" (capacity and price typed by hand); anything else must be an
     * existing room type, which then sets the room's capacity and rent.
     */
    private static function roomTypeOrRedirect(): ?array
    {
        $raw = (string) ($_POST['room_type_id'] ?? '');
        if ($raw === '') {
            return null;
        }
        $type = ctype_digit($raw) ? RoomType::find((int) $raw) : null;
        if ($type === null) {
            self::backWithError('Please choose a room type from the list.');
        }
        return $type;
    }

    /**
     * Edits an advertised offering. The website, the inquiry forms and Add Boarder all read it,
     * and every room linked to it takes the new capacity and rent (RoomType::update), so the
     * offering, the rooms and the bills never disagree.
     */
    public static function updateType(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $type = \App\Models\RoomType::find((int) $id);
        if ($type === null) {
            self::backWithError('That room type was not found.');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $capacityLabel = trim((string) ($_POST['capacity_label'] ?? ''));
        $capacity = $_POST['capacity'] ?? $type['capacity'];
        $price = $_POST['price'] ?? null;
        $unit = (string) ($_POST['price_unit'] ?? '');
        $badge = trim((string) ($_POST['badge'] ?? ''));
        $features = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) ($_POST['features'] ?? '')))), 'strlen'));

        $error = match (true) {
            $name === '' || mb_strlen($name) > 100 => 'Give the room type a name (up to 100 characters).',
            \App\Models\RoomType::nameTaken($name, (int) $id) => "Another room type is already called {$name}.",
            $capacityLabel === '' || mb_strlen($capacityLabel) > 60 => 'Describe who it is for, for example "2 Residents (Shared)" (up to 60 characters).',
            !ctype_digit((string) $capacity) || (int) $capacity < 1 || (int) $capacity > 50 => 'Residents per room must be a whole number from 1 to 50.',
            (int) $capacity < ($most = RoomType::largestBedCount((int) $id)) => "A room of this type already has {$most} beds, so it must hold at least {$most}. Remove beds first.",
            !is_numeric($price) || (float) $price < 0 || (float) $price > 1000000 => 'Enter a monthly price from ₱0 to ₱1,000,000.',
            !in_array($unit, ['room', 'bed'], true) => 'Choose whether the price is per room or per bed.',
            mb_strlen($badge) > 40 => 'Keep the badge to 40 characters.',
            $features === [] || count($features) > 8 => 'List between 1 and 8 features, one per line.',
            max(array_map('mb_strlen', $features)) > 120 => 'Keep each feature to 120 characters.',
            default => null,
        };
        if ($error !== null) {
            self::backWithError($error);
        }
        $synced = RoomType::update((int) $id, $name, $capacityLabel, (int) $capacity, round((float) $price, 2), $unit,
            $badge !== '' ? $badge : null, !empty($_POST['is_popular']), $features);
        $_SESSION['flash_success'] = "{$name} updated. The website shows the new details now"
            . ($synced > 0 ? ", and its {$synced} room" . ($synced === 1 ? '' : 's') . ' now hold ' . (int) $capacity . ' and bill the new rate from the current cycle.' : '.');
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

        $existingBeds = count(array_filter(Bed::all(), fn ($b) => (int) $b['room_id'] === $roomId));
        if ($existingBeds + $count > (int) $room['capacity']) {
            $_SESSION['flash_error'] = "Room {$room['room_number']} holds {$room['capacity']} and already has {$existingBeds} bed(s). Raise its capacity first.";
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
            $_SESSION['flash_error'] = \App\Support\Flash::error($e, 'The beds could not be added. Please try again.');
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

    /** Monthly rent per bed: a number from 0 to 1,000,000, or back to the form with an error. */
    private static function basePriceOrRedirect(): float
    {
        $raw = $_POST['base_price'] ?? null;
        if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 1000000) {
            $_SESSION['flash_error'] = 'Monthly price must be a number from 0 up.';
            header('Location: /admin/rooms');
            exit;
        }
        return round((float) $raw, 2);
    }

    /** Closes the CRUD gap from QA-VALIDATION-REPORT.md — edit a bed's label. */
    public static function updateBed(string $id): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Invalid session, please retry.';
            return;
        }
        $label = trim((string) ($_POST['label'] ?? ''));
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
        $_SESSION['flash_success'] = "Bed renamed to \"{$label}\".";
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
