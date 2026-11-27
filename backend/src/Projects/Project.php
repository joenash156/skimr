<?php

declare(strict_types=1);

namespace App\Projects;

final class Project
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly int $ownerId,
        public readonly string $title,
        public readonly ?string $researchQuestion,
        public readonly ?string $discipline,
        public readonly string $language,
        public readonly string $status,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            uuid: (string) $row['uuid'],
            ownerId: (int) $row['owner_id'],
            title: (string) $row['title'],
            researchQuestion: self::nullableString($row['research_question'] ?? null),
            discipline: self::nullableString($row['discipline'] ?? null),
            language: (string) $row['language'],
            status: (string) $row['status'],
            createdAt: self::date($row['created_at']),
            updatedAt: self::date($row['updated_at']),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'research_question' => $this->researchQuestion,
            'discipline' => $this->discipline,
            'language' => $this->language,
            'status' => $this->status,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function date(mixed $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
    }
}
