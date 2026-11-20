<?php

declare(strict_types=1);

namespace App\Security;

use App\Shared\Exception\HttpException;
use App\Users\User;

/**
 * 403 FORBIDDEN thrown by the RoleGuard middleware when the signed-in user's
 * role isn't allowed on the route. AuthMiddleware (which wraps RoleGuard)
 * catches it, writes the security.forbidden audit entry, and rethrows.
 */
final class RoleDeniedException extends HttpException
{
    /** @param list<string> $allowedRoles */
    public function __construct(
        public readonly User $user,
        public readonly array $allowedRoles,
        public readonly string $path,
    ) {
        parent::__construct(403, 'FORBIDDEN', 'errors.forbidden');
    }
}
