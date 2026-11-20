<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Audit\AuditEvent;
use App\Security\Audit\AuditLogger;
use App\Shared\Exception\ForbiddenException;
use App\Users\User;
use App\Users\UserRepository;
use Psr\Log\LoggerInterface;

/**
 * Who may act on whom in the admin console (P1 brief B1 task 14,
 * "RoleGuard::assertCanActOn"). Inject it and call it before every admin
 * action on a user:
 *
 *     $this->rolePolicy->assertCanActOn($admin, $target, RolePolicy::ACTION_SUSPEND);
 *
 * Rules:
 * - only admins and super admins act on other accounts;
 * - an admin may only act on accounts with role=user;
 * - nobody may change their own role, suspend or delete themselves here;
 * - the last active super admin can't be demoted, suspended or deleted.
 *
 * A violation writes the security.forbidden audit entry and throws 403.
 */
final class RolePolicy
{
    public const ACTION_VIEW = 'view';
    public const ACTION_SUSPEND = 'suspend';
    public const ACTION_REACTIVATE = 'reactivate';
    public const ACTION_DELETE = 'delete';
    public const ACTION_CHANGE_ROLE = 'change_role';
    public const ACTION_RESET_QUOTA = 'reset_quota';

    /** Actions that could leave the app without an active super admin. */
    private const REMOVES_POWER = [self::ACTION_SUSPEND, self::ACTION_DELETE, self::ACTION_CHANGE_ROLE];

    public function __construct(
        private readonly UserRepository $users,
        private readonly AuditLogger $audit,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @throws ForbiddenException */
    public function assertCanActOn(User $actor, User $target, string $action, ?string $ip = null): void
    {
        $rule = $this->brokenRule($actor, $target, $action);
        if ($rule === null) {
            return;
        }

        try {
            $this->audit->log($actor, AuditEvent::SECURITY_FORBIDDEN, 'user', $target->uuid, null, [
                'action' => $action,
                'rule' => $rule,
            ], $ip);
        } catch (\Throwable $e) {
            // Still refuse: a failed audit write must never turn a 403 into an allowed action.
            $this->logger->error('Could not write security.forbidden audit entry: {error}', ['error' => $e->getMessage()]);
        }

        throw new ForbiddenException();
    }

    /** Null when allowed, otherwise a short name for the rule that was broken. */
    private function brokenRule(User $actor, User $target, string $action): ?string
    {
        if (!$actor->isAdmin() || !$actor->isActive()) {
            return 'not_admin';
        }
        if ($actor->role === User::ROLE_ADMIN && $target->role !== User::ROLE_USER) {
            return 'admin_on_non_user';
        }
        if ($actor->id === $target->id && in_array($action, self::REMOVES_POWER, true)) {
            return 'self';
        }
        if (
            $target->isSuperAdmin()
            && $target->isActive()
            && in_array($action, self::REMOVES_POWER, true)
            && $this->users->countActiveSuperAdmins() <= 1
        ) {
            return 'last_super_admin';
        }

        return null;
    }
}
