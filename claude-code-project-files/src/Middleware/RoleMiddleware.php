<?php

namespace App\Middleware;

class RoleMiddleware
{
    /**
     * Enforces RBAC. A logged-in user with the wrong role gets a plain 403 —
     * not a redirect to their own dashboard — so a boarder guessing an admin
     * URL learns nothing about what's there. See CLAUDE.md Phase 1 verification.
     */
    public static function require(array $allowedRoles): void
    {
        if (empty($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles, true)) {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
    }
}
