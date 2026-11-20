<?php

declare(strict_types=1);

namespace App\Security\Audit;

/**
 * Audit event names (spec 11.5). Use these constants instead of typing the
 * strings, so the admin console's filters always match.
 */
final class AuditEvent
{
    public const AUTH_LOGIN_SUCCESS = 'auth.login.success';
    public const AUTH_LOGIN_FAILED = 'auth.login.failed';
    public const AUTH_LOCKOUT = 'auth.lockout';
    public const AUTH_PASSWORD_RESET = 'auth.password.reset';
    public const AUTH_REFRESH_REUSE_DETECTED = 'auth.refresh.reuse_detected';

    public const USER_EMAIL_CHANGED = 'user.email.changed';

    public const ADMIN_USER_SUSPENDED = 'admin.user.suspended';
    public const ADMIN_USER_REACTIVATED = 'admin.user.reactivated';
    public const ADMIN_USER_DELETED = 'admin.user.deleted';
    public const ADMIN_USER_QUOTA_RESET = 'admin.user.quota_reset';

    public const SUPERADMIN_ADMIN_INVITED = 'superadmin.admin.invited';
    public const SUPERADMIN_ADMIN_SUSPENDED = 'superadmin.admin.suspended';
    public const SUPERADMIN_ADMIN_DELETED = 'superadmin.admin.deleted';
    public const SUPERADMIN_ADMIN_DEMOTED = 'superadmin.admin.demoted';

    public const SETTINGS_UPDATED = 'settings.updated';

    public const SYNTHESIS_PUBLISHED = 'synthesis.published';
    public const SYNTHESIS_UNPUBLISHED = 'synthesis.unpublished';

    /** A role check failed (RoleGuard or RolePolicy). Required by the P1 brief, B1 task 14. */
    public const SECURITY_FORBIDDEN = 'security.forbidden';

    private function __construct()
    {
    }
}
