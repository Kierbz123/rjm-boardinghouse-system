<?php

namespace App\Controllers;

use App\Database;

class LandingController
{
    /**
     * Renders the cinematic animated motion landing page for RJM Boardinghouse.
     */
    public static function index(): void
    {
        $sessionRole = $_SESSION['role'] ?? null;
        $userName = $_SESSION['name'] ?? null;

        $dashboardUrl = match ($sessionRole) {
            'admin'   => '/admin/dashboard',
            'staff'   => '/staff/dashboard',
            'boarder' => '/portal/dashboard',
            default   => null,
        };

        $contactInfo = [
            'caretakers'     => 'Ate Mary & Kuya Jun (On-site Caretakers)',
            'primary_phone'  => '+63 917 842 5900',
            'primary_tel'    => 'tel:+639178425900',
            'landline'       => '(083) 228-4012',
            'landline_tel'   => 'tel:+63832284012',
            'inquiry_email'  => 'inquiries@rjmboardinghouse.ph',
            'admin_email'    => 'admin@rjmboardinghouse.ph',
            'facebook_url'   => 'https://facebook.com/rjmboardinghouse',
            'messenger_url'  => 'https://m.me/rjmboardinghouse',
            'twitter_url'    => 'https://twitter.com/rjmboardinghouse',
            'instagram_url'  => 'https://instagram.com/rjmboardinghouse',
            'address_line1'  => 'Zone 3, Purok Sanctuary',
            'address_line2'  => 'Tupi, South Cotabato, 9505 Philippines',
            'office_hours'   => 'Mon – Sat: 8:00 AM – 6:00 PM | Sun: 1:00 PM – 5:00 PM',
            'security'       => 'Live CCTV · no curfew',
            'emergency_line' => '24/7 Security Alarm Dispatch'
        ];

        $mapData = [
            'latitude'     => 6.340433,
            'longitude'    => 124.935983,
            'plus_code'    => '8WRP+59H',
            'short_url'    => 'https://maps.app.goo.gl/r2xxZ1Kap72VGa8M9',
            'display_name' => 'RJM Boardinghouse (MJL Residence)',
            'address'      => '8WRP+59H, Zone 3, Tupi, South Cotabato, 9505 Philippines',
            // Coordinates from OpenStreetMap (looked up Oct 2026). Distances are computed below
            // from the boardinghouse pin; exact routes and travel times open in Google Maps.
            'landmarks'    => [
                ['name' => 'South East Asian Institute of Technology (SEAIT)', 'short' => 'SEAIT', 'type' => 'College', 'lat' => 6.3461957, 'lng' => 124.9358150],
                ['name' => 'General Santos–Koronadal Highway (Crossing Rubber)', 'short' => 'National highway', 'type' => 'Jeepney & bus route', 'lat' => 6.3461957, 'lng' => 124.9358150],
                ['name' => 'Tupi Public Market', 'short' => 'Public market', 'type' => 'Market', 'lat' => 6.3320136, 'lng' => 124.9494007],
                ['name' => 'Tupi Municipal Hall', 'short' => 'Municipal hall', 'type' => 'Town center', 'lat' => 6.3311036, 'lng' => 124.9506640],
            ],
        ];

        foreach ($mapData['landmarks'] as &$lm) {
            $lm['km'] = self::kmBetween($mapData['latitude'], $mapData['longitude'], $lm['lat'], $lm['lng']);
            $lm['directions'] = 'https://www.google.com/maps/dir/?api=1&travelmode=walking&origin='
                . $mapData['latitude'] . ',' . $mapData['longitude'] . '&destination=' . $lm['lat'] . ',' . $lm['lng'];
        }
        unset($lm);
        usort($mapData['landmarks'], fn ($x, $y) => $x['km'] <=> $y['km']);

        // The offerings come from room_types, the same records the admin edits on Rooms & Beds.
        $roomTiers = [];
        try {
            foreach (\App\Models\RoomType::all() as $t) {
                $roomTiers[] = [
                    'id'       => $t['code'],
                    'type'     => $t['name'],
                    'badge'    => $t['badge'],
                    'popular'  => (bool) $t['is_popular'],
                    'price'    => '₱' . number_format((float) $t['price']),
                    'period'   => $t['price_unit'] === 'bed' ? '/ bed / month' : '/ month',
                    'capacity' => $t['capacity_label'],
                    'features' => $t['feature_list'],
                    'image'    => '/assets/images/rooms/room_' . $t['code'] . '.jpg',
                ];
            }
        } catch (\Throwable $e) {
            \App\Support\Logger::warn('Room offerings unavailable: ' . $e->getMessage());
        }

        // Only real numbers from the database (owner decision 8): no invented figures.
        $stats = ['total_rooms' => 0, 'total_beds' => 0, 'vacant_beds' => 0, 'residents' => 0,
                  'occupancy_pct' => 0, 'avg_repair_hours' => null];
        try {
            $pdo = Database::getConnection();
            $row = $pdo->query("SELECT (SELECT COUNT(*) FROM rooms) AS total_rooms,
                                       COUNT(*) AS total_beds,
                                       COALESCE(SUM(status = 'vacant'), 0) AS vacant_beds
                                FROM beds")->fetch();
            $stats['total_rooms'] = (int) $row['total_rooms'];
            $stats['total_beds'] = (int) $row['total_beds'];
            $stats['vacant_beds'] = (int) $row['vacant_beds'];
            $stats['occupancy_pct'] = $stats['total_beds'] > 0
                ? (int) round(100 * ($stats['total_beds'] - $stats['vacant_beds']) / $stats['total_beds']) : 0;
            $stats['residents'] = (int) $pdo->query("SELECT COUNT(*) FROM boarder_profiles bp JOIN users u ON u.id = bp.user_id
                                                     WHERE bp.status IN ('active', 'on_notice') AND u.status = 'active'")->fetchColumn();
            $hours = $pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 FROM maintenance_requests
                                  WHERE status = 'resolved' AND resolved_at IS NOT NULL")->fetchColumn();
            $stats['avg_repair_hours'] = $hours !== null ? round((float) $hours, 1) : null;
        } catch (\Throwable $e) {
            \App\Support\Logger::warn('Landing stats unavailable: ' . $e->getMessage());
        }

        $blueprintData = self::blueprintData();

        $inquirySuccess = $_SESSION['flash_inquiry_success'] ?? null;
        unset($_SESSION['flash_inquiry_success']);
        $inquiryError = $_SESSION['flash_inquiry_error'] ?? null;
        unset($_SESSION['flash_inquiry_error']);

        require __DIR__ . '/../Views/shared/landing.php';
    }

    /**
     * Live blueprint room and floor dataset, queried directly from the database;
     * the room list is empty when the database has no rooms yet.
     */
    public static function blueprintData(): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('
                SELECT r.id,
                       r.room_number,
                       r.floor,
                       r.capacity,
                       r.base_price,
                       r.room_type_id,
                       rt.name AS room_type_name,
                       rt.code AS room_type_code,
                       rt.capacity_label,
                       COUNT(b.id) AS total_beds,
                       COALESCE(SUM(CASE WHEN b.status = "vacant" THEN 1 ELSE 0 END), 0) AS vacant_beds,
                       COALESCE(SUM(CASE WHEN b.status = "occupied" THEN 1 ELSE 0 END), 0) AS occupied_beds
                FROM rooms r
                LEFT JOIN room_types rt ON rt.id = r.room_type_id
                LEFT JOIN beds b ON b.room_id = r.id
                GROUP BY r.id
                ORDER BY r.floor ASC, r.room_number ASC
            ');
            $rawRooms = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            \App\Support\Logger::warn('Blueprint query failed: ' . $e->getMessage());
            $rawRooms = [];
        }

        $rooms = [];
        $floorsSet = [];

        foreach ($rawRooms as $row) {
            $floorRaw = trim((string) ($row['floor'] ?? ''));
            $roomNum = trim((string) ($row['room_number'] ?? ''));

            // Deduce floor number if floor is missing/blank
            if ($floorRaw === '' || $floorRaw === null) {
                if (preg_match('/^(\d+)/', $roomNum, $matches)) {
                    $numVal = (int) $matches[1];
                    $floor = $numVal >= 100 ? (int) floor($numVal / 100) : 1;
                } else {
                    $floor = 1;
                }
            } else {
                $floor = preg_replace('/\D/', '', $floorRaw);
                $floor = $floor !== '' ? (int) $floor : 1;
            }
            if ($floor < 1) $floor = 1;

            $floorsSet[$floor] = true;

            $capacity = (int) $row['capacity'];
            $totalBeds = (int) $row['total_beds'];
            $vacantBeds = (int) $row['vacant_beds'];
            $occupiedBeds = (int) $row['occupied_beds'];

            // Status determination
            $status = 'vacant';
            if ($totalBeds > 0 && $vacantBeds === 0) {
                $status = 'occupied';
            } elseif ($occupiedBeds > 0) {
                $status = 'partially_occupied';
            }

            $rooms[] = [
                'id'             => (int) $row['id'],
                'room_number'    => $roomNum,
                'floor'          => (string) $floor,
                'floor_number'   => $floor,
                'capacity'       => $capacity,
                'base_price'     => (float) $row['base_price'],
                'room_type_name' => $row['room_type_name'] ?? ($capacity === 1 ? 'Solo Executive Room' : ($capacity === 2 ? 'Twin Sharing Suite' : 'Bedspace Sanctuary')),
                'room_type_code' => $row['room_type_code'] ?? ($capacity === 1 ? 'solo' : ($capacity === 2 ? 'twin' : 'quad')),
                'capacity_label' => $row['capacity_label'] ?? ($capacity . ' ' . ($capacity === 1 ? 'Resident' : 'Beds')),
                'total_beds'     => $totalBeds,
                'vacant_beds'    => $vacantBeds,
                'occupied_beds'  => $occupiedBeds,
                'status'         => $status,
            ];
        }

        // The landing page draws the compound's fixed site plan (two storeys); rooms not in the
        // database yet are shown there as "Not listed", so no demo rooms are invented here.
        $floorsSet += [1 => true, 2 => true];
        ksort($floorsSet);
        $floors = array_map('strval', array_keys($floorsSet));

        return [
            'floors' => $floors,
            'rooms'  => $rooms,
            'updated_at' => date('c'),
        ];
    }

    /**
     * Public JSON endpoint for the live architectural blueprint.
     */
    public static function blueprintJson(): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo json_encode(self::blueprintData());
        exit;
    }

    /** Public room inquiry form on the landing page. */
    public static function handleInquiry(): void
    {
        $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        // Bots fill every field, including the hidden "website" one that people never see.
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            self::respondToInquiry($isJson, true, 'Thank you! Your inquiry has been sent.', '/#contact');
        }

        try {
            $result = \App\Models\Inquiry::submit($_POST, 'website', null, \App\Support\TrustedProxy::clientIp($_SERVER));
            if ($result['ok']) {
                \App\Services\NotificationDispatcher::inquiryReceived($result);
            }
        } catch (\Throwable $e) {
            \App\Support\Logger::error('Inquiry could not be saved: ' . $e->getMessage());
            $result = ['ok' => false, 'error' => 'Sorry, we could not send your inquiry right now. Please call us instead.'];
        }

        self::respondToInquiry(
            $isJson,
            $result['ok'],
            $result['ok'] ? "Thank you, {$result['name']}! Our caretaker will call or text {$result['phone']} soon." : $result['error'],
            '/#contact'
        );
    }

    /** JSON for the fetch()-based forms, flash + redirect otherwise. Shared with InquiryController. */
    public static function respondToInquiry(bool $isJson, bool $ok, string $message, string $redirect): never
    {
        if ($isJson) {
            header('Content-Type: application/json');
            http_response_code($ok ? 200 : 422);
            echo json_encode($ok ? ['success' => true, 'message' => $message] : ['success' => false, 'error' => $message]);
            exit;
        }
        $_SESSION[$ok ? 'flash_inquiry_success' : 'flash_inquiry_error'] = $message;
        header('Location: ' . $redirect);
        exit;
    }

    /** Straight-line (great-circle) distance in km, rounded to 0.1. */
    private static function kmBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return round(2 * $r * asin(min(1, sqrt($h))), 1);
    }
}
