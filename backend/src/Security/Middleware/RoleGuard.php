<?php

declare(strict_types=1);

namespace App\Security\Middleware;

use App\Shared\Exception\ForbiddenException;
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
 * B0 stub that fails closed: every request is 403 until B1 reads the user's
 * role, adds assertCanActOn() and writes the security.forbidden audit entry.
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
        return new self(array_values($roles));
    }

    /** @return list<'user'|'admin'|'super_admin'> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new ForbiddenException();
    }
}
