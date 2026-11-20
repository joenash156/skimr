<?php

declare(strict_types=1);

namespace App\Auth\Google;

use App\Auth\Session;

/** What the Google callback should do next (spec 5.2). */
final class GoogleSignInResult
{
    public const SIGNED_IN = 'signed_in';
    public const NEW_ACCOUNT = 'new_account';
    public const LINK_REQUIRED = 'link_required';

    /** @param array<string, string>|null $linkPayload for LINK_REQUIRED: what the link cookie carries */
    private function __construct(
        public readonly string $kind,
        public readonly ?Session $session,
        public readonly ?array $linkPayload,
    ) {
    }

    public static function signedIn(Session $session): self
    {
        return new self(self::SIGNED_IN, $session, null);
    }

    public static function newAccount(Session $session): self
    {
        return new self(self::NEW_ACCOUNT, $session, null);
    }

    public static function linkRequired(string $sub, string $email): self
    {
        return new self(self::LINK_REQUIRED, null, ['sub' => $sub, 'email' => $email]);
    }
}
