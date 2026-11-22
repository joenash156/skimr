<?php

declare(strict_types=1);

namespace App\Public\Contact;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final class ContactMessageRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function store(ContactMessage $message): void
    {
        $this->db->insert(
            'contact_messages',
            [
                'name' => $message->name,
                'email' => $message->email,
                'message' => $message->message,
                'ip' => $message->ip,
            ],
            [
                'name' => Types::STRING,
                'email' => Types::STRING,
                'message' => Types::TEXT,
                'ip' => Types::BINARY,
            ],
        );
    }
}
