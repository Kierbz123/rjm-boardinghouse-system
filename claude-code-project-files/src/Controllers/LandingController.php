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
        $userName = $_SESSION['user_name'] ?? null;
        $userEmail = $_SESSION['user_email'] ?? null;

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
            'curfew_hours'   => '10:00 PM Daily (Smart QR Gate Pass Access)',
            'emergency_line' => '24/7 Security Alarm Dispatch'
        ];

        $mapData = [
            'latitude'     => 6.340433,
            'longitude'    => 124.935983,
            'plus_code'    => '8WRP+59H',
            'short_url'    => 'https://maps.app.goo.gl/r2xxZ1Kap72VGa8M9',
            'display_name' => 'RJM Boardinghouse (MJL Residence)',
            'address'      => '8WRP+59H, Zone 3, Tupi, South Cotabato, 9505 Philippines',
            'landmarks'    => [
                ['name' => 'South East Asian Institute of Technology (SEAIT)', 'distance' => '4-6 mins', 'type' => 'College'],
                ['name' => 'Tupi Municipal Hall & Civic Plaza', 'distance' => '3-4 mins', 'type' => 'Civic Center'],
                ['name' => 'Tupi Public Market & Commercial Center', 'distance' => '3 mins', 'type' => 'Market'],
                ['name' => 'General Santos - Marbel National Highway', 'distance' => '2 mins', 'type' => 'Transit'],
            ],
        ];

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

        $stats = [
            'total_rooms'     => 12,
            'total_beds'      => 24,
            'vacant_beds'     => 6,
            'security_uptime' => '99.9%',
            'ai_response'     => '< 15 mins',
        ];

        try {
            $pdo = Database::getConnection();
            $roomCount = (int) $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
            if ($roomCount > 0) {
                $stats['total_rooms'] = $roomCount;
            }
            $totalBeds = (int) $pdo->query("SELECT COUNT(*) FROM beds")->fetchColumn();
            $vacantBeds = (int) $pdo->query("SELECT COUNT(*) FROM beds WHERE status = 'vacant'")->fetchColumn();
            if ($totalBeds > 0) {
                $stats['total_beds'] = $totalBeds;
                $stats['vacant_beds'] = $vacantBeds;
            }
        } catch (\Throwable $e) {
            // Graceful degradation per architecture guidelines
        }

        $inquirySuccess = $_SESSION['flash_inquiry_success'] ?? null;
        unset($_SESSION['flash_inquiry_success']);
        $inquiryError = $_SESSION['flash_inquiry_error'] ?? null;
        unset($_SESSION['flash_inquiry_error']);

        require __DIR__ . '/../Views/shared/landing.php';
    }

    /**
     * Handles public prospective resident room inquiry submissions.
     */
    public static function handleInquiry(): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $roomType = trim((string) ($_POST['room_type'] ?? 'General Inquiry'));
        $moveInDate = trim((string) ($_POST['move_in_date'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        $isJson = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

        if ($name === '' || $phone === '') {
            $errorMsg = 'Please provide both your name and a valid contact phone number.';
            if ($isJson) {
                header('Content-Type: application/json');
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $errorMsg]);
                exit;
            }
            $_SESSION['flash_inquiry_error'] = $errorMsg;
            header('Location: /#contact');
            exit;
        }

        try {
            $pdo = Database::getConnection();
            $adminIds = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(\PDO::FETCH_COLUMN);

            $notifMsg = "New Room Inquiry from {$name} ({$phone})";
            $notifDetails = "Requested: {$roomType}" . ($moveInDate ? " | Move-in: {$moveInDate}" : "") . ($email ? " | Email: {$email}" : "") . ($message ? " | Notes: {$message}" : "");

            foreach ($adminIds as $adminId) {
                \App\Models\Notification::create(
                    (int) $adminId,
                    'inquiry',
                    $notifMsg . ' — ' . $notifDetails,
                    '/admin/inquiry-center'
                );
            }
        } catch (\Throwable $e) {
            // Graceful resilience: log or ignore notification error
        }

        $successMsg = "Thank you, {$name}! Your room inquiry has been transmitted to our front desk caretaker. We will call/SMS {$phone} promptly.";

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $successMsg]);
            exit;
        }

        $_SESSION['flash_inquiry_success'] = $successMsg;
        header('Location: /#contact');
        exit;
    }
}
