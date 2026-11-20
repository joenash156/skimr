<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use App\Auth\Jwt\JwtService;
use App\Security\Audit\AuditEvent;
use App\Security\Audit\AuditLogger;
use App\Security\RoleDeniedException;
use App\Shared\Exception\EmailNotVerifiedException;
use App\Shared\Exception\UnauthorizedException;
use App\Shared\Http\ClientIp;
use App\Shared\Http\RequestAttributes;
use App\Users\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Chain step 5 (spec 11): authentication. Add it to a route or group:
 *
 *     $group->...->add(AuthMiddleware::class);
 *
 * Checks (spec 11.1): `Authorization: Bearer <access JWT>` with a valid
 * signature, exp, iss and aud; the user still exists; `tv` equals
 * users.token_version (so "sign out everywhere" and suspensions apply on the
 * very next request); status is active.
 *
 * A user with status pending_verification may only call GET /api/v1/me and
 * /api/v1/auth/*; anything else gets 403 EMAIL_NOT_VERIFIED (spec 5.2).
 *
 * On success the App\Users\User is on the request attribute
 * RequestAttributes::USER. Failures are a plain 401 UNAUTHENTICATED.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    private const PENDING_ALLOWED_PREFIX = '/api/v1/auth/';
    private const PENDING_ALLOWED_ME = '/api/v1/me';

    public function __construct(
        private readonly JwtService $jwt,
        private readonly UserRepository $users,
        private readonly AuditLogger $audit,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = self::bearerToken($request);
        if ($token === null) {
            throw new UnauthorizedException();
        }

        $claims = $this->jwt->verifyAccessToken($token);
        $user = $this->users->findByUuid($claims->userUuid);
        if ($user === null || $user->tokenVersion !== $claims->tokenVersion) {
            throw new UnauthorizedException();
        }

        if ($user->isPendingVerification()) {
            if (!self::pendingUserMayCall($request)) {
                throw new EmailNotVerifiedException();
            }
        } elseif (!$user->isActive()) {
            // suspended, invited or deleted: the token is no longer honoured
            throw new UnauthorizedException();
        }

        try {
            return $handler->handle($request->withAttribute(RequestAttributes::USER, $user));
        } catch (RoleDeniedException $denied) {
            $this->auditDenied($denied, $request);
            throw $denied;
        }
    }

    private static function bearerToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+([A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+)$/', $header, $m)) {
            return null;
        }

        return $m[1];
    }

    private static function pendingUserMayCall(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return str_starts_with($path, self::PENDING_ALLOWED_PREFIX)
            || ($path === self::PENDING_ALLOWED_ME && $request->getMethod() === 'GET');
    }

    private function auditDenied(RoleDeniedException $denied, ServerRequestInterface $request): void
    {
        try {
            $this->audit->log(
                $denied->user,
                AuditEvent::SECURITY_FORBIDDEN,
                'route',
                null,
                null,
                [
                    'method' => $request->getMethod(),
                    'path' => $denied->path,
                    'required_roles' => $denied->allowedRoles,
                ],
                ClientIp::of($request),
            );
        } catch (\Throwable $e) {
            // The 403 still goes out; a failed audit write is logged, never swallowed silently.
            $this->logger->error('Could not write security.forbidden audit entry: {error}', ['error' => $e->getMessage()]);
        }
    }
}
