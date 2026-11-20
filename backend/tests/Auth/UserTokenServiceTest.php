<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Token\TokenType;
use App\Auth\Token\UserTokenService;
use App\Shared\Clock\FrozenClock;
use App\Shared\Exception\InvalidTokenException;
use App\Tests\DatabaseTestCase;

final class UserTokenServiceTest extends DatabaseTestCase
{
    private FrozenClock $clock;
    private UserTokenService $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock('2026-11-20 10:00:00');
        $this->tokens = new UserTokenService($this->db(), $this->clock);
    }

    public function testIssueStoresOnlyTheHashAndConsumeReturnsTheOwnerAndPayload(): void
    {
        $user = $this->createUser();
        $raw = $this->tokens->issue($user->id, TokenType::CHANGE_EMAIL, 3600, ['email' => 'new@example.com']);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $raw);
        $stored = $this->db()->fetchOne('SELECT token_hash FROM user_tokens');
        self::assertSame(hash('sha256', $raw), $stored);
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM user_tokens WHERE token_hash = ?', [$raw]));

        $consumed = $this->tokens->consume($raw, TokenType::CHANGE_EMAIL);
        self::assertSame($user->id, $consumed->userId);
        self::assertSame(['email' => 'new@example.com'], $consumed->payload);
    }

    public function testATokenWorksOnlyOnce(): void
    {
        $raw = $this->tokens->issue($this->createUser()->id, TokenType::VERIFY_EMAIL, 86400);
        $this->tokens->consume($raw, TokenType::VERIFY_EMAIL);

        $this->expectException(InvalidTokenException::class);
        $this->tokens->consume($raw, TokenType::VERIFY_EMAIL);
    }

    public function testAnExpiredTokenIsRejected(): void
    {
        $raw = $this->tokens->issue($this->createUser()->id, TokenType::RESET_PASSWORD, 3600);
        $this->clock->advance('+61 minutes');

        $this->expectException(InvalidTokenException::class);
        $this->tokens->consume($raw, TokenType::RESET_PASSWORD);
    }

    public function testATokenOfAnotherTypeIsRejected(): void
    {
        $raw = $this->tokens->issue($this->createUser()->id, TokenType::VERIFY_EMAIL, 86400);

        $this->expectException(InvalidTokenException::class);
        $this->tokens->consume($raw, TokenType::RESET_PASSWORD);
    }

    public function testMalformedAndUnknownTokensAreRejected(): void
    {
        foreach (["' OR 1=1 --", str_repeat('a', 64), ''] as $raw) {
            try {
                $this->tokens->consume($raw, TokenType::VERIFY_EMAIL);
                self::fail('Expected InvalidTokenException.');
            } catch (InvalidTokenException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testIssuingAgainCancelsTheEarlierLink(): void
    {
        $userId = $this->createUser()->id;
        $first = $this->tokens->issue($userId, TokenType::ADMIN_INVITE, 86400);
        $second = $this->tokens->issue($userId, TokenType::ADMIN_INVITE, 86400);

        self::assertSame($userId, $this->tokens->consume($second, TokenType::ADMIN_INVITE)->userId);
        $this->expectException(InvalidTokenException::class);
        $this->tokens->consume($first, TokenType::ADMIN_INVITE);
    }

    public function testRevokeCancelsUnusedTokens(): void
    {
        $userId = $this->createUser()->id;
        $raw = $this->tokens->issue($userId, TokenType::ADMIN_INVITE, 86400);
        $this->tokens->revoke($userId, TokenType::ADMIN_INVITE);

        $this->expectException(InvalidTokenException::class);
        $this->tokens->consume($raw, TokenType::ADMIN_INVITE);
    }

    public function testRejectsUnknownTypesAndSillyLifetimes(): void
    {
        $userId = $this->createUser()->id;
        foreach ([['bogus', 3600], [TokenType::VERIFY_EMAIL, 0], [TokenType::VERIFY_EMAIL, 30 * 86400]] as [$type, $ttl]) {
            try {
                $this->tokens->issue($userId, $type, $ttl);
                self::fail('Expected InvalidArgumentException.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
