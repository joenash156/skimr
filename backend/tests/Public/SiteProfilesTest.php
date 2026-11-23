<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Public\Site\SiteProfiles;
use App\Shared\Settings\SettingsRepository;
use App\Tests\DatabaseTestCase;
use Psr\Log\NullLogger;

final class SiteProfilesTest extends DatabaseTestCase
{
    public function testFallsBackToTheTeamInboxWhenNothingIsStored(): void
    {
        $profile = $this->profiles('Skimr Team <team@example.org>')->forLocale('en');

        self::assertSame('team@example.org', $profile->email);
        self::assertNull($profile->phone);
        self::assertSame([], $profile->socials);
        self::assertTrue($profile->hasContact());
    }

    public function testShowsOnlyValidValuesFromTheAdminSettings(): void
    {
        $this->settings()->set(SiteProfiles::SETTINGS_KEY, [
            'email' => 'hello@skimr.example',
            'phone' => '+233 (0)20 123-4567',
            'address' => ['en' => 'Accra, Ghana', 'fr' => 'Accra, Ghana (FR)'],
            'socials' => [
                'linkedin' => 'https://www.linkedin.com/company/skimr',
                'x' => 'https://twitter.com/skimr',
                'github' => 'http://github.com/skimr',              // not https
                'instagram' => 'https://evil.example/instagram.com', // wrong host
                'facebook' => 'javascript:alert(1)',
                'youtube' => 'https://user:pass@youtube.com/@skimr', // credentials
                'tiktok' => 'https://tiktok.com/@skimr',              // unknown network
            ],
        ]);

        $profile = $this->profiles(null)->forLocale('fr');

        self::assertSame('hello@skimr.example', $profile->email);
        self::assertSame('tel:+2330201234567', $profile->phoneHref());
        self::assertSame('Accra, Ghana (FR)', $profile->address);
        self::assertSame([
            'linkedin' => 'https://www.linkedin.com/company/skimr',
            'x' => 'https://twitter.com/skimr',
        ], $profile->socials);
        self::assertSame('Accra, Ghana', $this->profiles(null)->forLocale('es')->address, 'falls back to English');
    }

    public function testDropsAnInvalidEmailAndPhone(): void
    {
        $this->settings()->set(SiteProfiles::SETTINGS_KEY, ['email' => 'not-an-email', 'phone' => 'call us!']);

        $profile = $this->profiles(null)->forLocale('en');

        self::assertNull($profile->email);
        self::assertNull($profile->phone);
        self::assertFalse($profile->hasContact());
    }

    private function profiles(?string $fallbackEmail): SiteProfiles
    {
        return new SiteProfiles($this->settings(), new NullLogger(), $fallbackEmail);
    }

    private function settings(): SettingsRepository
    {
        $settings = $this->service(SettingsRepository::class);
        self::assertInstanceOf(SettingsRepository::class, $settings);

        return $settings;
    }
}
