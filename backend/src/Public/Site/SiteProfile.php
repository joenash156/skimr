<?php

declare(strict_types=1);

namespace App\Public\Site;

/** Public contact details and social links, resolved for one language. Every value is already validated. */
final readonly class SiteProfile
{
    /**
     * @param array<string, string> $socials network => https URL, in display order
     */
    public function __construct(
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public array $socials,
    ) {
    }

    public function hasContact(): bool
    {
        return $this->email !== null || $this->phone !== null || $this->address !== null;
    }

    /** The phone number as a tel: link target (digits and a leading +). */
    public function phoneHref(): ?string
    {
        return $this->phone === null ? null : 'tel:' . preg_replace('/[^0-9+]/', '', $this->phone);
    }
}
