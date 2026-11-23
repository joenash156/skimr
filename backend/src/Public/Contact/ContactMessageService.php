<?php

declare(strict_types=1);

namespace App\Public\Contact;

use App\Shared\Clock\Clock;
use App\Shared\Mail\Mailer;
use Psr\Log\LoggerInterface;

/**
 * Stores a contact form message, then emails a copy to the team inbox (spec 15.2).
 *
 * The message is always stored first, so nothing a visitor sends is lost when the
 * inbox isn't configured or the mail provider is down (Mailer never throws).
 */
final class ContactMessageService
{
    /** The team reads these, so the email itself is always in English. */
    private const MAIL_LOCALE = 'en';

    public function __construct(
        private readonly ContactMessageRepository $messages,
        private readonly Mailer $mailer,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
        private readonly ?string $teamInbox,
    ) {
    }

    public function send(ContactMessage $message, string $locale): void
    {
        $this->messages->store($message);

        if ($this->teamInbox === null) {
            $this->logger->warning('Contact message stored but not emailed: MAIL_REPLY_TO (team inbox) is not set.');

            return;
        }

        $this->mailer->send('contact_message', $this->teamInbox, self::MAIL_LOCALE, [
            'name' => $message->name,
            'email' => $message->email,
            'message' => $message->message,
            'locale' => $locale,
            'at' => $this->clock->now()->format(DATE_ATOM),
        ]);
    }
}
