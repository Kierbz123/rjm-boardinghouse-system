<?php

namespace App\Support;

/** 403 / 404 / 500 responses: a real page for people, JSON for /api/ calls. */
class ErrorPage
{
    private const COPY = [
        403 => ['You don\'t have access to this page', 'Your account can\'t open this page. Go back to your dashboard to continue.'],
        404 => ['Page not found', 'This page doesn\'t exist or was moved. Check the address, or go back to your dashboard.'],
        500 => ['Something went wrong on our side', 'The problem has been logged. Try again in a moment; if it keeps happening, tell the administrator.'],
    ];

    public static function render(int $code): never
    {
        http_response_code($code);
        [$title, $body] = self::COPY[$code] ?? self::COPY[500];

        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (str_starts_with($path, '/api/') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $title]);
            exit;
        }

        $home = match ($_SESSION['role'] ?? null) {
            'admin' => '/admin/dashboard',
            'staff' => '/staff/dashboard',
            'boarder' => '/portal/dashboard',
            default => '/',
        };
        $homeLabel = $home === '/' ? 'Go to the home page' : 'Back to my dashboard';
        require __DIR__ . '/../Views/shared/error.php';
        exit;
    }
}
