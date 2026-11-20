<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\RateLimit\RateLimit;
use App\Security\RateLimit\RateLimiter;
use App\Shared\Clock\FrozenClock;
use App\Shared\Exception\QuotaExceededException;
use App\Tests\DatabaseTestCase;

final class RateLimiterTest extends DatabaseTestCase
{
    private FrozenClock $clock;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock('2026-11-20 10:05:00');
        $this->limiter = new RateLimiter($this->db(), $this->clock);
    }

    public function testAllowsUpToTheLimitThenThrows429WithRetryAfter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->consume(RateLimit::login(), 'email:a@example.com|ip:203.0.113.10');
        }

        try {
            $this->limiter->consume(RateLimit::login(), 'email:a@example.com|ip:203.0.113.10');
            self::fail('Expected 429.');
        } catch (QuotaExceededException $e) {
            self::assertSame('RATE_LIMITED', $e->errorCode());
            // Window is 10:00-10:15 and it is 10:05, so 10 minutes remain.
            self::assertSame(['Retry-After' => '600'], $e->headers());
        }
    }

    public function testSubjectsAndBucketsAreCountedSeparately(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->consume(RateLimit::login(), 'ip:203.0.113.10');
        }
        $this->limiter->consume(RateLimit::login(), 'ip:203.0.113.11');
        $this->limiter->consume(RateLimit::register(), 'ip:203.0.113.10');

        self::assertSame(5, $this->limiter->hits(RateLimit::login(), 'ip:203.0.113.10'));
        self::assertTrue($this->limiter->isFull(RateLimit::login(), 'ip:203.0.113.10'));
        self::assertFalse($this->limiter->isFull(RateLimit::login(), 'ip:203.0.113.11'));
    }

    public function testANewWindowStartsFromZero(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->consume(RateLimit::forgotPassword(), 'email:a@example.com');
        }
        $this->clock->advance('+1 hour');

        $this->limiter->consume(RateLimit::forgotPassword(), 'email:a@example.com');
        self::assertSame(1, $this->limiter->hits(RateLimit::forgotPassword(), 'email:a@example.com'));
    }

    public function testSubjectsAreStoredHashedAndClearForgetsThem(): void
    {
        $this->limiter->consume(RateLimit::login(), 'email:secret@example.com');

        self::assertSame(0, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM rate_limits WHERE bucket_key LIKE '%example%'"));

        $this->limiter->clear(RateLimit::login(), 'email:secret@example.com');
        self::assertSame(0, $this->limiter->hits(RateLimit::login(), 'email:secret@example.com'));
    }

    public function testPruneDeletesOldWindows(): void
    {
        $this->limiter->consume(RateLimit::api(), 'ip:203.0.113.10');
        $this->clock->advance('+2 days');
        $this->limiter->consume(RateLimit::api(), 'ip:203.0.113.10');

        self::assertSame(1, $this->limiter->prune($this->clock->now()->modify('-1 day')));
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM rate_limits'));
    }

    public function testTheGlobalMiddlewareLimitsApiCallsPerIp(): void
    {
        for ($i = 0; $i < 120; $i++) {
            self::assertSame(404, $this->request('GET', '/api/v1/does-not-exist')->getStatusCode());
        }

        $response = $this->request('GET', '/api/v1/does-not-exist');
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('RATE_LIMITED', self::json($response)['error']['code']);
        self::assertNotSame('', $response->getHeaderLine('Retry-After'));

        // Another IP and the health check are unaffected.
        self::assertSame(404, $this->request('GET', '/api/v1/does-not-exist', ip: '203.0.113.99')->getStatusCode());
        self::assertSame(200, $this->request('GET', '/api/v1/health')->getStatusCode());
    }
}
