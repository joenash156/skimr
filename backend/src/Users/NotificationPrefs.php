<?php

declare(strict_types=1);

namespace App\Users;

/**
 * Optional-email switches (spec 15.2). Security emails can't be turned off
 * and aren't listed here. users.notification_prefs stores only what the user
 * changed; NULL means "all defaults".
 */
final class NotificationPrefs
{
    public const DEFAULTS = [
        'processing_done' => true,
        'report_ready' => true,
        'project_invite' => true,
        'weekly_digest' => false,
    ];

    private function __construct()
    {
    }

    /** @return array<string, bool> every pref, with defaults filled in */
    public static function resolve(User $user): array
    {
        return array_merge(self::DEFAULTS, array_intersect_key($user->notificationPrefs ?? [], self::DEFAULTS));
    }

    /** True when the user wants this optional email. P2's mailer checks it before optional sends. */
    public static function wants(User $user, string $pref): bool
    {
        return self::resolve($user)[$pref] ?? false;
    }
}
