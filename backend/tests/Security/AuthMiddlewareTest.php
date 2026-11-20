<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Auth\Jwt\JwtService;
use App\Security\Audit\AuditEvent;
use App\Security\Middleware\AuthMiddleware;
use App\Security\Middleware\RoleGuard;
use App\Shared\Exception\EmailNotVerifiedException;
use App\Shared\Exception\HttpException;
use App\Shared\Exception\UnauthorizedException;
use App\Shared\Http\RequestAttributes;
use App\Tests\DatabaseTestCase;
use App\Users\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AuthMiddlewareTest extends DatabaseTestCase
{
    private ?ServerRequestInterface $seen = null;

    public function testValidTokenPutsTheUserOnTheRequest(): void
    {
        $user = $this->createUser();

        $response = $this->callThrough('GET', '/api/v1/projects', $this->tokenFor($user));

        self::assertSame(200, $response->getStatusCode());
        $attached = $this->seen?->getAttribute(RequestAttributes::USER);
        self::assertInstanceOf(User::class, $attached);
        self::assertSame($user->uuid, $attached->uuid);
    }

    public function testMissingOrMalformedHeaderIs401(): void
    {
        $this->expectFailure(UnauthorizedException::class, fn () => $this->callThrough('GET', '/api/v1/projects', null));
        $this->expectFailure(UnauthorizedException::class, fn () => $this->callThrough('GET', '/api/v1/projects', 'Token abc'));
        $this->expectFailure(UnauthorizedException::class, fn () => $this->callThrough('GET', '/api/v1/projects', 'not.a.jwt'));
    }

    public function testBumpingTokenVersionRevokesExistingTokens(): void
    {
        $user = $this->createUser();
        $token = $this->tokenFor($user);
        $this->db()->executeStatement('UPDATE users SET token_version = token_version + 1 WHERE id = ?', [$user->id]);

        $this->expectFailure(UnauthorizedException::class, fn () => $this->callThrough('GET', '/api/v1/projects', $token));
    }

    public function testSuspendedAndDeletedUsersAreRejectedOnTheNextRequest(): void
    {
        foreach ([User::STATUS_SUSPENDED, User::STATUS_DELETED, User::STATUS_INVITED] as $status) {
            $user = $this->createUser();
            $token = $this->tokenFor($user);
            $this->db()->update('users', ['status' => $status], ['id' => $user->id]);

            $this->expectFailure(UnauthorizedException::class, fn () => $this->callThrough('GET', '/api/v1/projects', $token));
        }
    }

    public function testPendingUserMayOnlyReachMeAndAuthRoutes(): void
    {
        $token = $this->tokenFor($this->createUser(['status' => User::STATUS_PENDING_VERIFICATION, 'email_verified_at' => null]));

        self::assertSame(200, $this->callThrough('GET', '/api/v1/me', $token)->getStatusCode());
        self::assertSame(200, $this->callThrough('POST', '/api/v1/auth/verify-email/resend', $token)->getStatusCode());
        $this->expectFailure(EmailNotVerifiedException::class, fn () => $this->callThrough('PATCH', '/api/v1/me', $token));
        $this->expectFailure(EmailNotVerifiedException::class, fn () => $this->callThrough('GET', '/api/v1/projects', $token));
    }

    public function testRoleGuardLetsAllowedRolesThrough(): void
    {
        $admin = $this->createUser(['role' => User::ROLE_ADMIN]);

        $response = $this->callThrough('GET', '/api/v1/admin/users', $this->tokenFor($admin), RoleGuard::allow('admin', 'super_admin'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->auditRows(AuditEvent::SECURITY_FORBIDDEN));
    }

    public function testRoleGuardDenialIs403AndWritesAnAuditRow(): void
    {
        $user = $this->createUser();

        try {
            $this->callThrough('GET', '/api/v1/admin/users', $this->tokenFor($user), RoleGuard::allow('admin', 'super_admin'));
            self::fail('Expected 403.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status());
            self::assertSame('FORBIDDEN', $e->errorCode());
        }

        $rows = $this->auditRows(AuditEvent::SECURITY_FORBIDDEN);
        self::assertCount(1, $rows);
        self::assertSame($user->id, (int) $rows[0]['actor_id']);
        self::assertSame('user', $rows[0]['actor_role']);
        self::assertSame(inet_pton('203.0.113.10'), $rows[0]['ip']);
        self::assertStringContainsString('/api/v1/admin/users', (string) $rows[0]['meta']);
    }

    public function testRoleGuardWithoutAuthFailsClosed(): void
    {
        $this->expectException(UnauthorizedException::class);
        RoleGuard::allow('admin')->process($this->buildRequest('GET', '/api/v1/admin/users'), $this->handler());
    }

    private function tokenFor(User $user): string
    {
        return 'Bearer ' . $this->service(JwtService::class)->issueAccessToken($user)->token;
    }

    /** Runs [RoleGuard ->] handler wrapped in AuthMiddleware, like a Slim route group would. */
    private function callThrough(string $method, string $path, ?string $authorization, ?RoleGuard $guard = null): ResponseInterface
    {
        $request = $this->buildRequest($method, $path, $authorization === null ? [] : ['Authorization' => $authorization]);
        $inner = $this->handler();
        if ($guard !== null) {
            $inner = new class ($guard, $inner) implements RequestHandlerInterface {
                public function __construct(private readonly RoleGuard $guard, private readonly RequestHandlerInterface $next)
                {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->guard->process($request, $this->next);
                }
            };
        }

        return $this->service(AuthMiddleware::class)->process($request, $inner);
    }

    private function handler(): RequestHandlerInterface
    {
        $test = $this;

        return new class ($test) implements RequestHandlerInterface {
            public function __construct(private readonly AuthMiddlewareTest $test)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->test->remember($request);

                return new Response(200);
            }
        };
    }

    /** @internal called by the stub handler */
    public function remember(ServerRequestInterface $request): void
    {
        $this->seen = $request;
    }

    /** @param class-string<\Throwable> $expected */
    private function expectFailure(string $expected, callable $call): void
    {
        try {
            $call();
            self::fail('Expected ' . $expected);
        } catch (\Throwable $e) {
            self::assertInstanceOf($expected, $e);
        }
    }
}
