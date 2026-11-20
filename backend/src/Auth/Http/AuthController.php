<?php

declare(strict_types=1);

namespace App\Auth\Http;

use App\Auth\AuthService;
use App\Auth\EmailVerificationService;
use App\Auth\Exception\SessionExpiredException;
use App\Auth\InviteService;
use App\Auth\Jwt\JwtService;
use App\Auth\LoginService;
use App\Auth\Password\PasswordPolicy;
use App\Auth\PasswordResetService;
use App\Auth\RefreshCookie;
use App\Auth\RegistrationService;
use App\Auth\Session;
use App\Security\RateLimit\RateLimit;
use App\Security\RateLimit\RateLimiter;
use App\Shared\Exception\UnauthorizedException;
use App\Shared\Http\ClientIp;
use App\Shared\Http\Input;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RequestAttributes;
use App\Shared\Translator;
use App\Users\User;
use App\Users\UserPresenter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * The /api/v1/auth endpoints (P1 brief B1 tasks 7-11; contract in
 * docs/api/openapi.yaml). Turnstile, rate limits and AuthMiddleware are
 * attached in config/routes/auth.php; this class validates input and shapes
 * responses.
 */
final class AuthController
{
    public function __construct(
        private readonly RegistrationService $registration,
        private readonly LoginService $login,
        private readonly AuthService $auth,
        private readonly JwtService $jwt,
        private readonly EmailVerificationService $verification,
        private readonly PasswordResetService $passwordReset,
        private readonly InviteService $invites,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly RateLimiter $rateLimiter,
        private readonly RefreshCookie $cookie,
        private readonly CsrfGuard $csrf,
        private readonly UserPresenter $presenter,
        private readonly JsonResponder $json,
        private readonly Translator $translator,
        private readonly int $accessTtl,
    ) {
    }

    /** POST /auth/register: 201 {user}; the account starts pending_verification. */
    public function register(Request $request, Response $response): Response
    {
        $in = Input::fromRequest($request);
        $profile = [
            'first_name' => $in->requiredString('first_name', 80),
            'last_name' => $in->requiredString('last_name', 80),
            'other_names' => $in->optionalString('other_names', 120),
            'email' => $in->email('email'),
            'phone' => $in->optionalPhone('phone'),
            'locale' => $in->optionalLocale('locale') ?? $this->locale($request),
        ];
        $password = $this->newPassword($in);
        $in->accepted('accept_terms');
        $in->validate();

        $user = $this->registration->register($profile, $password);

        return $this->json->created($response, ['user' => $this->presenter->present($user)]);
    }

    /** POST /auth/login: 200 session + refresh cookie. */
    public function login(Request $request, Response $response): Response
    {
        $in = Input::fromRequest($request);
        $email = $in->email('email');
        $password = $in->secret('password');
        $in->validate();

        $session = $this->login->login($email, $password, ClientIp::of($request), $request->getHeaderLine('User-Agent'));

        return $this->sessionResponse($response, $session);
    }

    /** POST /auth/refresh: rotates the cookie and returns a new access token. */
    public function refresh(Request $request, Response $response): Response
    {
        $this->csrf->assert($request);

        $token = RefreshCookie::read($request);
        if ($token === null) {
            throw SessionExpiredException::unauthenticated();
        }

        // The per-user limit (30/min) is counted BEFORE rotating: refusing after a
        // rotation would leave the browser holding a superseded token, and its next
        // refresh would look like theft.
        try {
            $subject = $this->jwt->verifyRefreshToken($token)->userUuid;
        } catch (UnauthorizedException) {
            throw SessionExpiredException::unauthenticated();
        }
        $this->rateLimiter->consume(RateLimit::refresh(), 'user:' . $subject);

        return $this->sessionResponse($response, $this->auth->refresh($token, ClientIp::of($request)));
    }

    /** POST /auth/logout: forgets the session and expires the cookie. Always 204. */
    public function logout(Request $request, Response $response): Response
    {
        $this->csrf->assert($request);
        $this->auth->logout(RefreshCookie::read($request));

        return $this->cookie->clear($this->json->noContent($response));
    }

    /** POST /auth/verify-email: activates the account. */
    public function verifyEmail(Request $request, Response $response): Response
    {
        $in = Input::fromRequest($request);
        $token = $in->secret('token', 128);
        $in->validate();

        $user = $this->verification->verify($token);

        return $this->json->success($response, ['user' => $this->presenter->present($user)]);
    }

    /** POST /auth/verify-email/resend (signed in): at most 3 per hour. 204 even if already verified. */
    public function resendVerification(Request $request, Response $response): Response
    {
        $user = $this->currentUser($request);
        if ($user->emailVerifiedAt === null) {
            $this->rateLimiter->consume(RateLimit::resendVerification(), 'user:' . $user->uuid);
            $this->verification->send($user);
        }

        return $this->json->noContent($response);
    }

    /** POST /auth/password/forgot: always the same 200, whether or not the email exists. */
    public function forgotPassword(Request $request, Response $response): Response
    {
        $in = Input::fromRequest($request);
        $email = $in->email('email');
        $in->validate();

        $this->passwordReset->requestReset($email);

        return $this->json->success($response, [
            'message' => $this->translator->trans('auth.messages.reset_link_sent', [], $this->locale($request)),
        ]);
    }

    /** POST /auth/password/reset: 204; every session is signed out. */
    public function resetPassword(Request $request, Response $response): Response
    {
        $in = Input::fromRequest($request);
        $token = $in->secret('token', 128);
        $password = $this->newPassword($in);
        $in->validate();

        // Validated first, so a typo in the new password doesn't burn the one-time link.
        $this->passwordReset->reset($token, $password, ClientIp::of($request));

        return $this->json->noContent($response);
    }

    /** POST /auth/invite/accept: sets the first password and logs in. */
    public function acceptInvite(Request $request, Response $response): Response
    {
        $in = Input::fromRequest($request);
        $token = $in->secret('token', 128);
        $password = $this->newPassword($in);
        $in->validate();

        return $this->sessionResponse($response, $this->invites->accept($token, $password));
    }

    /** Reads password + password_confirmation and applies the spec 5.2 rules. */
    private function newPassword(Input $in): string
    {
        $password = $in->secret('password', 1024);
        if ($password !== '') {
            foreach ($this->passwordPolicy->violations($password) as $error) {
                $in->addError('password', $error);
            }
            $in->confirms('password_confirmation', $password);
        }

        return $password;
    }

    private function sessionResponse(Response $response, Session $session): Response
    {
        $response = $this->json->success($response, [
            'access_token' => $session->accessToken->token,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTtl,
            'user' => $this->presenter->present($session->user),
        ]);

        // The tokens must never be cached by a browser or proxy.
        return $this->cookie->set($response, $session->refreshToken)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }

    private function currentUser(Request $request): User
    {
        $user = $request->getAttribute(RequestAttributes::USER);

        return $user instanceof User ? $user : throw new UnauthorizedException();
    }

    private function locale(Request $request): string
    {
        $locale = $request->getAttribute(RequestAttributes::LOCALE);
        if (is_string($locale) && Translator::isSupported($locale)) {
            return $locale;
        }

        return Translator::negotiate($request->getHeaderLine('Accept-Language')) ?? Translator::DEFAULT;
    }
}
