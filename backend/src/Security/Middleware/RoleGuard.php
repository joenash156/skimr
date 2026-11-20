<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use App\Security\RoleDeniedException;
use App\Shared\Exception\UnauthorizedException;
use App\Shared\Http\RequestAttributes;
use App\Users\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Chain step 6 (spec 11): role check. Runs after AuthMiddleware, so add it
 * before AuthMiddleware in Slim (Slim runs the last-added middleware first):
 *
 *     $group->...->add(RoleGuard::allow('admin', 'super_admin'))->add(AuthMiddleware::class);
 *
 * A user whose role isn't listed gets 403 FORBIDDEN, and AuthMiddleware
 * writes the security.forbidden audit entry. Used without AuthMiddleware it
 * fails closed with 401.
 *
 * For "may this admin act on that user?" use App\Security\RolePolicy::assertCanActOn().
 */
final class RoleGuard implements MiddlewareInterface
{
    /** @param list<'user'|'admin'|'super_admin'> $roles */
    private function __construct(private readonly array $roles)
    {
    }

    /** @param 'user'|'admin'|'super_admin' ...$roles */
    public static function allow(string ...$roles): self
    {
        if ($roles === []) {
            throw new \InvalidArgumentException('RoleGuard::allow() needs at least one role.');
        }

        return new self(array_values($roles));
    }

    /** @return list<'user'|'admin'|'super_admin'> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(RequestAttributes::USER);
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }
        if (!in_array($user->role, $this->roles, true)) {
            throw new RoleDeniedException($user, $this->roles, $request->getUri()->getPath());
        }

        return $handler->handle($request);
    }
}
