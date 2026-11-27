<?php

declare(strict_types=1);

namespace App\Projects;

use App\Shared\Clock\Clock;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

final class ProjectRepository
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
    ) {
    }

    public function findByUuid(string $uuid): ?Project
    {
        if (!Uuid::isValid($uuid)) {
            return null;
        }

        $row = $this->db->fetchAssociative(
            'SELECT * FROM projects WHERE uuid = :uuid AND deleted_at IS NULL',
            ['uuid' => strtolower($uuid)],
        );

        return $row === false ? null : Project::fromRow($row);
    }

    /**
     * @return array{items: list<Project>, total: int}
     */
    public function findByOwner(int $ownerId, int $page, int $perPage, ?string $status): array
    {
        $qb = $this->db->createQueryBuilder()
            ->select('*')
            ->from('projects')
            ->where('owner_id = :owner')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('owner', $ownerId)
            ->orderBy('updated_at', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($status !== null) {
            $qb->andWhere('status = :status')->setParameter('status', $status);
        }

        $rows = $qb->fetchAllAssociative();
        $countQb = $this->db->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('projects')
            ->where('owner_id = :owner')
            ->andWhere('deleted_at IS NULL')
            ->setParameter('owner', $ownerId);
        if ($status !== null) {
            $countQb->andWhere('status = :status')->setParameter('status', $status);
        }
        $count = $countQb->fetchOne();

        return [
            'items' => array_map(static fn (array $row): Project => Project::fromRow($row), $rows),
            'total' => (int) $count,
        ];
    }

    /** @param array<string, mixed> $values */
    public function create(int $ownerId, array $values): Project
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
        $uuid = Uuid::uuid4()->toString();
        $this->db->insert('projects', [
            'uuid' => $uuid,
            'owner_id' => $ownerId,
            'title' => (string) $values['title'],
            'research_question' => $values['research_question'],
            'discipline' => $values['discipline'],
            'language' => $values['language'],
            'status' => Project::STATUS_ACTIVE,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByUuid($uuid)
            ?? throw new \RuntimeException('Project vanished right after insert.');
    }

    /** @param array<string, mixed> $values */
    public function update(Project $project, array $values): Project
    {
        $allowed = ['title', 'research_question', 'discipline', 'language', 'status'];
        $columns = array_intersect_key($values, array_flip($allowed));
        if ($columns !== []) {
            $columns['updated_at'] = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
            $this->db->update('projects', $columns, ['id' => $project->id]);
        }

        return $this->findByUuid($project->uuid)
            ?? throw new \RuntimeException('Project vanished during update.');
    }

    public function softDelete(Project $project): void
    {
        $this->db->update('projects', [
            'deleted_at' => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
            'updated_at' => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
        ], ['id' => $project->id]);
    }
}
