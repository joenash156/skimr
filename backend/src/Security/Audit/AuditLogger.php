<?php

declare(strict_types=1);

namespace App\Security\Audit;

use App\Shared\Clock\Clock;
use App\Shared\Http\ClientIp;
use App\Users\User;
use Doctrine\DBAL\Connection;

/**
 * Writes the audit trail (audit_logs, spec 11.5).
 *
 *     $audit->log($admin, AuditEvent::ADMIN_USER_SUSPENDED, 'user', $target->uuid, $reason, ['until' => ...], $ip);
 *
 * Never put passwords, tokens, JWTs or other secrets in $reason or $meta.
 * $actor is null for anonymous attempts (e.g. a failed login on an unknown
 * email) and system jobs.
 */
final class AuditLogger
{
    private const MAX_REASON = 500;

    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param string $action one of the AuditEvent constants
     * @param string|null $targetType e.g. 'user', 'project', 'setting'
     * @param string|null $targetId the target's uuid (or setting key), never a numeric id
     * @param array<string, mixed> $meta small JSON-able context, no secrets
     */
    public function log(
        ?User $actor,
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $reason = null,
        array $meta = [],
        ?string $ip = null,
    ): void {
        $this->db->insert('audit_logs', [
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $reason === null ? null : mb_substr($reason, 0, self::MAX_REASON),
            'ip' => ClientIp::toBinary($ip),
            'meta' => $meta === [] ? null : json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
        ]);
    }
}
