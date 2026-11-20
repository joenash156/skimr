<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\Audit\AuditEvent;
use App\Security\RolePolicy;
use App\Shared\Exception\ForbiddenException;
use App\Tests\DatabaseTestCase;
use App\Users\User;

final class RolePolicyTest extends DatabaseTestCase
{
    private function policy(): RolePolicy
    {
        return $this->service(RolePolicy::class);
    }

    public function testAdminMayActOnARegularUser(): void
    {
        $admin = $this->createUser(['role' => User::ROLE_ADMIN]);
        $user = $this->createUser();

        $this->policy()->assertCanActOn($admin, $user, RolePolicy::ACTION_SUSPEND);

        self::assertSame([], $this->auditRows(AuditEvent::SECURITY_FORBIDDEN));
    }

    public function testAdminActingOnAnAdminIs403AndAudited(): void
    {
        $admin = $this->createUser(['role' => User::ROLE_ADMIN]);
        $otherAdmin = $this->createUser(['role' => User::ROLE_ADMIN]);

        $this->assertDenied($admin, $otherAdmin, RolePolicy::ACTION_SUSPEND);

        $rows = $this->auditRows(AuditEvent::SECURITY_FORBIDDEN);
        self::assertCount(1, $rows);
        self::assertSame($admin->id, (int) $rows[0]['actor_id']);
        self::assertSame('user', $rows[0]['target_type']);
        self::assertSame($otherAdmin->uuid, $rows[0]['target_id']);
        // MySQL's JSON type reorders keys, so compare without caring about order.
        self::assertEquals(['action' => 'suspend', 'rule' => 'admin_on_non_user'], json_decode((string) $rows[0]['meta'], true));
    }

    public function testAdminCannotTouchASuperAdmin(): void
    {
        $this->assertDenied(
            $this->createUser(['role' => User::ROLE_ADMIN]),
            $this->createUser(['role' => User::ROLE_SUPER_ADMIN]),
            RolePolicy::ACTION_VIEW,
        );
    }

    public function testRegularUsersCannotActOnAnyone(): void
    {
        $this->assertDenied($this->createUser(), $this->createUser(), RolePolicy::ACTION_VIEW);
    }

    public function testNobodyChangesTheirOwnRole(): void
    {
        $super = $this->createUser(['role' => User::ROLE_SUPER_ADMIN]);
        $this->createUser(['role' => User::ROLE_SUPER_ADMIN]); // so "last super admin" isn't the reason

        $this->assertDenied($super, $super, RolePolicy::ACTION_CHANGE_ROLE);
        self::assertSame(['self'], $this->deniedRules());
    }

    public function testTheLastActiveSuperAdminCannotBeRemoved(): void
    {
        $actor = $this->createUser(['role' => User::ROLE_SUPER_ADMIN]);
        $target = $this->createUser(['role' => User::ROLE_SUPER_ADMIN]);
        $this->createUser(['role' => User::ROLE_SUPER_ADMIN, 'status' => User::STATUS_SUSPENDED]); // doesn't count

        // Two active super admins: removing one is fine.
        $this->policy()->assertCanActOn($actor, $target, RolePolicy::ACTION_DELETE);

        // The actor is suspended mid-request (their in-memory copy is stale), leaving the
        // target as the only active super admin. Every power-removing action is refused.
        $this->db()->update('users', ['status' => User::STATUS_SUSPENDED], ['id' => $actor->id]);
        foreach ([RolePolicy::ACTION_SUSPEND, RolePolicy::ACTION_DELETE, RolePolicy::ACTION_CHANGE_ROLE] as $action) {
            $this->assertDenied($actor, $target, $action);
        }
        // Harmless actions are still allowed.
        $this->policy()->assertCanActOn($actor, $target, RolePolicy::ACTION_VIEW);

        self::assertSame(['last_super_admin', 'last_super_admin', 'last_super_admin'], $this->deniedRules());
    }

    private function assertDenied(User $actor, User $target, string $action): void
    {
        try {
            $this->policy()->assertCanActOn($actor, $target, $action);
            self::fail('Expected 403.');
        } catch (ForbiddenException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return list<mixed> the "rule" of every security.forbidden row, oldest first */
    private function deniedRules(): array
    {
        return array_map(
            static function (array $row): mixed {
                $meta = json_decode((string) $row['meta'], true);

                return is_array($meta) ? ($meta['rule'] ?? null) : null;
            },
            $this->auditRows(AuditEvent::SECURITY_FORBIDDEN),
        );
    }
}
