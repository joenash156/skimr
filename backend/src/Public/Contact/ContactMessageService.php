<?php

declare(strict_types=1);

namespace App\Public\Contact;

use App\Shared\Mail\Mailer;
use RuntimeException;

final class ContactMessageService
{
    public function __construct(
        private readonly ContactMessageRepository $messages,
        private readonly Mailer $mailer,
        private readonly ?string $teamInbox,
    ) {
    }

    public function send(ContactMessage $message, string $locale): void
    {
        if ($this->teamInbox === null) {
            throw new RuntimeException('MAIL_REPLY_TO is required for contact messages.');
        }

        $this->messages->store($message);
        $this->mailer->send('contact_message', $this->teamInbox, $locale, [
            'name' => $message->name,
            'email' => $message->email,
            'message' => $message->message,
        ]);
    }
}
