<?php

declare(strict_types=1);

namespace App\Tests\Public;

use App\Public\Contact\ContactMessage;
use App\Public\Contact\ContactMessageRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

final class ContactMessageRepositoryTest extends TestCase
{
    public function testStoresContactMessageWithPackedIp(): void
    {
        $message = new ContactMessage(
            'Ada Lovelace',
            'ada@example.test',
            'A question about Skimr.',
            "\x7f\x00\x00\x01",
        );
        $db = $this->createMock(Connection::class);
        $db->expects(self::once())
            ->method('insert')
            ->with(
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

        (new ContactMessageRepository($db))->store($message);
    }
}
