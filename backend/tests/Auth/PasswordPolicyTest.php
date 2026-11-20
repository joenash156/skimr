<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Password\PasswordHasher;
use App\Auth\Password\PasswordPolicy;
use App\Shared\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    private static function policy(): PasswordPolicy
    {
        return new PasswordPolicy(dirname(__DIR__, 2) . '/resources/common-passwords.txt');
    }

    public function testAcceptsAGoodPassword(): void
    {
        self::assertSame([], self::policy()->violations('river-Lantern-42'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function badPasswords(): iterable
    {
        yield 'too short' => ['abc12345', PasswordPolicy::ERROR_LENGTH];
        yield 'over 72 bytes' => [str_repeat('a1', 37), PasswordPolicy::ERROR_LENGTH];
        yield 'no number' => ['onlyletters-here', PasswordPolicy::ERROR_LETTER_AND_NUMBER];
        yield 'no letter' => ['1234567890123', PasswordPolicy::ERROR_LETTER_AND_NUMBER];
        // In the top-10,000 list, 10+ characters, and has a letter and a number.
        yield 'common' => ['charlie123', PasswordPolicy::ERROR_COMMON];
        yield 'common, any case' => ['CHARLIE123', PasswordPolicy::ERROR_COMMON];
    }

    #[DataProvider('badPasswords')]
    public function testRejectsBadPasswords(string $password, string $expectedError): void
    {
        self::assertContains($expectedError, self::policy()->violations($password));
    }

    public function testCountsCharactersNotBytesForTheMinimum(): void
    {
        // 10 characters, 20 bytes: long enough.
        self::assertNotContains(PasswordPolicy::ERROR_LENGTH, self::policy()->violations('ééééééééé1'));
    }

    public function testAssertValidReportsTheFieldAndFirstError(): void
    {
        try {
            self::policy()->assertValid('short1', 'new_password');
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame(['new_password' => PasswordPolicy::ERROR_LENGTH], $e->fields());
        }
    }

    public function testMissingListFailsLoudly(): void
    {
        $this->expectException(\RuntimeException::class);
        (new PasswordPolicy('/no/such/file.txt'))->violations('river-Lantern-42');
    }

    public function testHasherRoundTripAndTimingSafeNullHash(): void
    {
        $hasher = new PasswordHasher(4);
        $hash = $hasher->hash('river-Lantern-42');

        self::assertStringStartsWith('$2y$04$', $hash);
        self::assertTrue($hasher->verify('river-Lantern-42', $hash));
        self::assertFalse($hasher->verify('river-Lantern-43', $hash));
        self::assertFalse($hasher->verify('river-Lantern-42', null));
        self::assertTrue((new PasswordHasher(12))->needsRehash($hash));
        self::assertFalse($hasher->needsRehash($hash));
    }
}
