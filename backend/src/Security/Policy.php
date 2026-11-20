<?php

declare(strict_types=1);

namespace App\Security;

use App\Users\User;

/**
 * Object-level access check (spec 11, chain step 7). P2's ProjectPolicy
 * implements it:
 *
 *     final class ProjectPolicy implements Policy
 *     {
 *         public function can(User $user, string $ability, object $resource): bool { ... }
 *     }
 *
 * $ability is a short verb such as 'view', 'edit' or 'delete'. When can()
 * returns false, project-scoped code throws NotFoundException (404, not 403),
 * so ids can't be probed (spec 11.2).
 */
interface Policy
{
    public function can(User $user, string $ability, object $resource): bool;
}
