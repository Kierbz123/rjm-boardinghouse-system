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

        $roomTiers = [
            [
                'id'          => 'solo',
                'type'        => 'Solo Executive Room',
                'badge'       => 'Maximum Privacy',
                'popular'     => false,
                'price'       => '₱3,500',
                'period'      => '/ month',
                'capacity'    => '1 Resident (Private)',
                'features'    => [
                    'Private furnished room with solid hardwood door',
                    'Dedicated ergonomic study desk & reading lamp',
                    'Individual steel locker & wardrobe cabinet',
                    'Dedicated electric submeter & private ceiling fan',
                    'Fiber Wi-Fi & quiet study floor allocation'
                ],
                'cta_label'   => 'Inquire for Solo Room'
            ],
            [
                'id'          => 'twin',
                'type'        => 'Twin Sharing Scholar Suite',
                'badge'       => 'Most Popular',
                'popular'     => true,
                'price'       => '₱2,200',
                'period'      => '/ bed / month',
                'capacity'    => '2 Residents (Shared)',
                'features'    => [
                    'Spacious 2-bed layout with dual study stations',
                    'Two separate secure steel storage lockers',
                    'Wide cross-ventilation windows with blackout curtains',
                    'High-speed Wi-Fi & power charging hubs',
                    'Split electricity submetering per occupant'
                ],
                'cta_label'   => 'Inquire for Twin Sharing'
            ],
            [
                'id'          => 'quad',
                'type'        => 'Quad Bedspace Sanctuary',
                'badge'       => 'Best Value',
                'popular'     => false,
                'price'       => '₱1,600',
                'period'      => '/ bed / month',
                'capacity'    => '4 Residents (Bunk Bed)',
                'features'    => [
                    'Heavy-duty mahogany double-deck bunks with privacy curtains',
                    'Individual LED night lamp & dual USB wall sockets',
                    'Assigned lockable heavy-duty storage compartment',
                    'Complimentary purified drinking water station',
                    'Full access to shared study hall & pantry station'
                ],
                'cta_label'   => 'Inquire for Bedspace'
            ]
        ];

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

        $inquirySuccess = $_SESSION['flash_inquiry_success'] ?? null;
        unset($_SESSION['flash_inquiry_success']);
        $inquiryError = $_SESSION['flash_inquiry_error'] ?? null;
        unset($_SESSION['flash_inquiry_error']);

        require __DIR__ . '/../Views/shared/landing.php';
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
            $result = \App\Models\Inquiry::submit($_POST, 'website', null, $_SERVER['REMOTE_ADDR'] ?? null);
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
