<?php

declare(strict_types=1);

namespace App\Security;

use App\Projects\Project;
use App\Users\User;
use Doctrine\DBAL\Connection;

final class ProjectPolicy implements Policy
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function can(User $user, string $ability, object $resource): bool
    {
        if (!$resource instanceof Project) {
            return false;
        }

        if ($resource->ownerId === $user->id) {
            return in_array($ability, ['view', 'edit', 'delete', 'manage_members'], true);
        }

        $memberRole = $this->db->fetchOne(
            'SELECT role FROM project_members WHERE project_id = ? AND user_id = ? AND status = ?',
            [$resource->id, $user->id, 'accepted'],
        );
        if (!is_string($memberRole)) {
            return false;
        }

        return match ($ability) {
            'view' => true,
            'edit' => $memberRole === 'editor',
            'delete', 'manage_members' => false,
            default => false,
        };
    }
}
