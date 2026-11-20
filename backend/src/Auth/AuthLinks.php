<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Builds the links put in auth emails. They open React screens (P4 brief,
 * routing), which read ?token= and call the matching API endpoint.
 */
final class AuthLinks
{
    public function __construct(private readonly string $appUrl)
    {
    }

    public function verifyEmail(string $token): string
    {
        return $this->link('/app/verify-email', $token);
    }

    public function resetPassword(string $token): string
    {
        return $this->link('/app/reset-password', $token);
    }

    public function acceptInvite(string $token): string
    {
        return $this->link('/app/accept-invite', $token);
    }

    private function link(string $path, string $token): string
    {
        return rtrim($this->appUrl, '/') . $path . '?token=' . rawurlencode($token);
    }
}
