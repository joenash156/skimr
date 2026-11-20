<?php

declare(strict_types=1);

namespace App\Users\Http;

use App\Auth\Password\PasswordPolicy;
use App\Auth\RefreshCookie;
use App\Shared\Exception\UnauthorizedException;
use App\Shared\Exception\ValidationException;
use App\Shared\Http\ClientIp;
use App\Shared\Http\Input;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RequestAttributes;
use App\Shared\Settings\FeatureFlags;
use App\Shared\Translator;
use App\Users\AccountService;
use App\Users\NotificationPrefs;
use App\Users\User;
use App\Users\UserPresenter;
use App\Users\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * /api/v1/me (P1 brief B2 task 2). Every route sits behind AuthMiddleware.
 * P2 owns /me/avatar, /me/usage and /me/export in their own routes file.
 */
final class MeController
{
    /** Fields PATCH /me accepts; anything else is a 422 (contract: additionalProperties false). */
    private const UPDATABLE = [
        'first_name', 'last_name', 'other_names', 'phone', 'alt_phone', 'institution',
        'discipline', 'locale', 'theme', 'citation_style', 'notification_prefs',
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly AccountService $accounts,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly FeatureFlags $features,
        private readonly UserPresenter $presenter,
        private readonly RefreshCookie $cookie,
        private readonly JsonResponder $json,
        private readonly Translator $translator,
    ) {
    }

    /** GET /me: the profile, preferences and feature flags. Pending users may call it. */
    public function show(Request $request, Response $response): Response
    {
        return $this->json->success($response, $this->me($this->user($request)));
    }

    /** PATCH /me: change only the fields sent. */
    public function update(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $in = Input::fromRequest($request);

        $unknown = array_diff($in->keys(), self::UPDATABLE);
        if ($in->keys() === [] || $unknown !== []) {
            throw new ValidationException(array_fill_keys($unknown === [] ? ['body'] : array_values($unknown), 'validation.invalid'));
        }

        $changes = [];
        foreach (['first_name' => 80, 'last_name' => 80] as $field => $max) {
            if ($in->has($field)) {
                $changes[$field] = $in->requiredString($field, $max);
            }
        }
        foreach (['other_names' => 120, 'institution' => 160, 'discipline' => 160] as $field => $max) {
            if ($in->has($field)) {
                $changes[$field] = $in->optionalString($field, $max);
            }
        }
        foreach (['phone', 'alt_phone'] as $field) {
            if ($in->has($field)) {
                $changes[$field] = $in->optionalPhone($field);
            }
        }
        $enums = [
            'locale' => ['en', 'fr', 'es'],
            'theme' => ['light', 'dark', 'system'],
            'citation_style' => ['apa7', 'mla9', 'harvard'],
        ];
        foreach ($enums as $field => $allowed) {
            if ($in->has($field)) {
                $changes[$field] = $in->oneOf($field, $allowed);
            }
        }
        if ($in->has('notification_prefs')) {
            // Merge, so the app can send just the switch that changed.
            $changes['notification_prefs'] = array_merge(
                $user->notificationPrefs ?? [],
                $in->booleanMap('notification_prefs', array_keys(NotificationPrefs::DEFAULTS)),
            );
        }
        $in->validate();

        $this->users->update($user->id, $changes);
        $fresh = $this->users->findById($user->id) ?? $user;

        return $this->json->success($response, $this->me($fresh));
    }

    /** POST /me/password: needs the current password; signs out everywhere. */
    public function changePassword(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $in = Input::fromRequest($request);
        $current = $in->secret('current_password');
        $new = $in->secret('password');
        if ($new !== '') {
            foreach ($this->passwordPolicy->violations($new) as $error) {
                $in->addError('password', $error);
            }
            $in->confirms('password_confirmation', $new);
        }
        $in->validate();

        $this->accounts->changePassword($user, $current, $new, ClientIp::of($request));

        // This device's session ended too: clear its cookie so the app goes to login.
        return $this->cookie->clear($this->json->noContent($response));
    }

    /** POST /me/email: link to the new address, notice to the old one. 202 until the link is opened. */
    public function changeEmail(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $in = Input::fromRequest($request);
        $email = $in->email('email');
        $password = $in->secret('password');
        $in->validate();

        $this->accounts->requestEmailChange($user, $email, $password);

        return $this->json->success($response, [
            'message' => $this->translator->trans('auth.messages.email_change_sent', [], $user->locale),
        ], [], 202);
    }

    /** DELETE /me: needs the password; soft delete; signs out everywhere. */
    public function delete(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $in = Input::fromRequest($request);
        $password = $in->secret('password');
        $in->validate();

        $this->accounts->deleteAccount($user, $password, ClientIp::of($request));

        return $this->cookie->clear($this->json->noContent($response));
    }

    /** @return array<string, mixed> the contract's `Me` object */
    private function me(User $user): array
    {
        return $this->presenter->present($user) + [
            'notification_prefs' => NotificationPrefs::resolve($user),
            'features' => $this->features->all(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->getAttribute(RequestAttributes::USER);

        return $user instanceof User ? $user : throw new UnauthorizedException();
    }
}
