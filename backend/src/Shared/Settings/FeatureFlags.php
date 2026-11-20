<?php

declare(strict_types=1);

namespace App\Shared\Settings;

/**
 * Feature flags the super admin switches on (spec 5.4), returned by GET /me
 * so the dashboard can hide options that aren't live yet.
 *
 * Stored as ONE settings row, key "features", value a JSON object:
 *
 *     {"upload_images": true, "add_link": false, "paste_text": true, "discover": false, "chat": false}
 *
 * A missing row or missing flag means false.
 */
final class FeatureFlags
{
    public const SETTINGS_KEY = 'features';

    public const FLAGS = ['upload_images', 'add_link', 'paste_text', 'discover', 'chat'];

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /** @return array<string, bool> every flag in FLAGS, true only when explicitly enabled */
    public function all(): array
    {
        $stored = $this->settings->get(self::SETTINGS_KEY, []);
        $stored = is_array($stored) ? $stored : [];

        $flags = [];
        foreach (self::FLAGS as $flag) {
            $flags[$flag] = ($stored[$flag] ?? false) === true;
        }

        return $flags;
    }

    public function isEnabled(string $flag): bool
    {
        return $this->all()[$flag] ?? false;
    }
}
