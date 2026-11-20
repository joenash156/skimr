<?php

declare(strict_types=1);

namespace App\Users;

/**
 * Turns a User into the contract's `User` JSON (docs/api/openapi.yaml).
 * This is the only way a user goes into a response: it never exposes
 * password or token hashes, numeric ids or Google ids.
 */
final class UserPresenter
{
    /** @return array<string, mixed> */
    public function present(User $user): array
    {
        return [
            'id' => $user->uuid,
            'role' => $user->role,
            'status' => $user->status,
            'first_name' => $user->firstName,
            'last_name' => $user->lastName,
            'other_names' => $user->otherNames,
            'email' => $user->email,
            'email_verified' => $user->emailVerifiedAt !== null,
            'phone' => $user->phone,
            'alt_phone' => $user->altPhone,
            'institution' => $user->institution,
            'discipline' => $user->discipline,
            // Signed Cloudinary URLs come with P2's MediaService (B2); until then no avatar.
            'avatar_url' => null,
            'has_password' => $user->passwordHash !== null,
            'google_linked' => $user->googleSub !== null,
            'locale' => $user->locale,
            'theme' => $user->theme,
            'citation_style' => $user->citationStyle,
            'created_at' => $user->createdAt->format(DATE_ATOM),
        ];
    }
}
