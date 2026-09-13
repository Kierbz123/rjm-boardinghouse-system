<?php

namespace App\Middleware;

class AuthMiddleware
{
    /** Blocks unauthenticated access. Redirects to /login rather than exposing any page content. */
    public static function require(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }
}
