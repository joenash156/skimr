<?php

declare(strict_types=1);

namespace App\Shared\Migrations;

use App\Security\Audit\AuditEvent;
use App\Security\Audit\AuditLogger;
use App\Shared\Exception\NotFoundException;
use App\Shared\Http\ClientIp;
use App\Shared\Http\RequestAttributes;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * GET/POST /_system/migrate: run database migrations from a browser.
 *
 * Staging (InfinityFree) has no shell, its MySQL can't be reached from
 * outside, and its browser check blocks scripts, so a deploy can't run
 * migrations itself (spec 17.3). This page can, in a real browser.
 *
 * Locked down:
 * - It exists only while MIGRATION_TOKEN (32+ characters) is set in the
 *   server's .env; otherwise 404. Set it for a deploy, remove it afterwards.
 * - Every action needs that token, compared in constant time; a wrong token
 *   is logged and answered slowly.
 * - It sits outside /api, so it works on a fresh database where the
 *   rate_limits and users tables don't exist yet.
 * - It shows version names and counts only, never SQL or error details.
 *
 * This is an internal operations page for P1, not a user screen, so it is
 * English-only by design.
 */
final class MigrationPageController
{
    private const MIN_TOKEN_LENGTH = 32;
    private const FAILURE_DELAY_MICROSECONDS = 1_000_000;

    public function __construct(
        private readonly MigrationRunner $runner,
        private readonly AuditLogger $audit,
        private readonly LoggerInterface $logger,
        private readonly ?string $token,
        private readonly int $failureDelay = self::FAILURE_DELAY_MICROSECONDS,
    ) {
    }

    public function show(Request $request, Response $response): Response
    {
        $this->assertEnabled();

        return $this->page($response, 200, $this->form());
    }

    public function act(Request $request, Response $response): Response
    {
        $this->assertEnabled();

        $body = $request->getParsedBody();
        $given = is_array($body) && is_string($body['token'] ?? null) ? $body['token'] : '';
        $action = is_array($body) && is_string($body['action'] ?? null) ? $body['action'] : 'status';

        if (!hash_equals((string) $this->token, $given)) {
            $this->logger->warning('Migration page: wrong token from {ip}.', ['ip' => ClientIp::of($request) ?? 'unknown']);
            usleep($this->failureDelay);

            return $this->page($response, 403, '<p class="bad">Wrong token.</p>' . $this->form());
        }

        try {
            if ($action === 'run') {
                $ran = $this->runner->migrate();
                $this->auditRun($ran, ClientIp::of($request));
                $summary = $ran === []
                    ? '<p class="ok">Nothing to run: the database is up to date.</p>'
                    : '<p class="ok">Ran ' . count($ran) . ' migration(s):</p>' . self::list($ran);
            } else {
                $pending = $this->runner->pending();
                $summary = '<p>' . count($this->runner->executed()) . ' migration(s) already run.</p>'
                    . ($pending === []
                        ? '<p class="ok">Nothing pending.</p>'
                        : '<p>' . count($pending) . ' pending:</p>' . self::list($pending));
            }
        } catch (\Throwable $e) {
            $requestId = (string) $request->getAttribute(RequestAttributes::REQUEST_ID, '');
            $this->logger->error('Migration page: {action} failed: {error}', ['action' => $action, 'error' => $e->getMessage(), 'exception' => $e]);

            return $this->page($response, 500, '<p class="bad">It failed. Details are in var/log/app.log (request id '
                . self::e($requestId) . ').</p>' . $this->form());
        }

        return $this->page($response, 200, $summary . $this->form());
    }

    private function assertEnabled(): void
    {
        if ($this->token === null || strlen($this->token) < self::MIN_TOKEN_LENGTH) {
            throw new NotFoundException();
        }
    }

    /** @param list<string> $versions */
    private function auditRun(array $versions, ?string $ip): void
    {
        try {
            $this->audit->log(null, AuditEvent::SYSTEM_MIGRATIONS_RUN, 'system', null, null, ['versions' => $versions], $ip);
        } catch (\Throwable $e) {
            // audit_logs may not exist if the very first migration failed halfway.
            $this->logger->warning('Migration page: could not audit the run: {error}', ['error' => $e->getMessage()]);
        }
    }

    private function form(): string
    {
        return <<<'HTML'
            <form method="post" action="/_system/migrate" autocomplete="off">
              <label>Migration token <input type="password" name="token" required></label>
              <button name="action" value="status">Show status</button>
              <button name="action" value="run">Run pending migrations</button>
            </form>
            HTML;
    }

    private function page(Response $response, int $status, string $content): Response
    {
        $html = <<<HTML
            <!doctype html>
            <html lang="en"><head><meta charset="utf-8"><title>Skimr migrations</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>body{font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem}
            .ok{color:#166534}.bad{color:#b91c1c}input,button{font:inherit;margin:.25rem 0}</style></head>
            <body><h1>Database migrations</h1>{$content}</body></html>
            HTML;

        $response->getBody()->write($html);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store');
    }

    /** @param list<string> $items */
    private static function list(array $items): string
    {
        return '<ul>' . implode('', array_map(static fn (string $v): string => '<li>' . self::e($v) . '</li>', $items)) . '</ul>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
