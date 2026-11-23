<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Public\Hero\HeroSlides;
use App\Shared\Settings\SettingsRepository;
use App\Shared\Translator;
use App\Tests\DatabaseTestCase;
use Psr\Log\NullLogger;

final class HeroSlidesTest extends DatabaseTestCase
{
    public function testShowsTheTranslatedDefaultsWhenNothingIsStored(): void
    {
        $slides = $this->slides()->forLocale('fr');

        self::assertCount(4, $slides);
        self::assertSame('/assets/hero/slide-1.jpg', $slides[0]->imageUrl);
        self::assertSame('Chaque constat renvoie à sa source.', $slides[1]->heading);
        self::assertNotSame('', $slides[0]->alt);
    }

    public function testUsesAdminSlidesSkipsUnsafeOnesAndFallsBackToEnglish(): void
    {
        $this->settings()->set(HeroSlides::SETTINGS_KEY, [
            [
                'image_url' => 'https://res.cloudinary.com/skimr/image/upload/skimr/site/hero/a.jpg',
                'heading' => ['en' => 'Evidence first', 'fr' => 'Les preuves d’abord'],
                'alt' => ['en' => 'A library'],
            ],
            ['image_url' => 'https://evil.example/x.jpg', 'heading' => ['en' => 'Blocked host']],
            ['image_url' => '/assets/hero/slide-2.jpg', 'heading' => ['en' => '   ']],
            ['image_url' => 'javascript:alert(1)', 'heading' => ['en' => 'Blocked scheme']],
            'not an object',
        ]);

        $fr = $this->slides()->forLocale('fr');
        self::assertCount(1, $fr);
        self::assertSame('Les preuves d’abord', $fr[0]->heading);
        self::assertSame('A library', $fr[0]->alt, 'missing French alt falls back to English');

        self::assertSame('Evidence first', $this->slides()->forLocale('es')[0]->heading);
    }

    public function testFallsBackToDefaultsWhenEveryStoredSlideIsInvalid(): void
    {
        $this->settings()->set(HeroSlides::SETTINGS_KEY, [['image_url' => 'http://insecure.example/a.jpg', 'heading' => ['en' => 'x']]]);

        self::assertCount(4, $this->slides()->forLocale('en'));
    }

    public function testNeverShowsMoreThanTheMaximum(): void
    {
        $slide = ['image_url' => '/assets/hero/slide-1.jpg', 'heading' => ['en' => 'Slide']];
        $this->settings()->set(HeroSlides::SETTINGS_KEY, array_fill(0, 10, $slide));

        self::assertCount(HeroSlides::MAX_SLIDES, $this->slides()->forLocale('en'));
    }

    private function slides(): HeroSlides
    {
        return new HeroSlides(
            $this->settings(),
            new Translator(dirname(__DIR__, 2) . '/translations/public'),
            new NullLogger(),
        );
    }

    private function settings(): SettingsRepository
    {
        $settings = $this->service(SettingsRepository::class);
        self::assertInstanceOf(SettingsRepository::class, $settings);

        return $settings;
    }
}
