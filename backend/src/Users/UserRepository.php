<?php

declare(strict_types=1);

namespace App\Users;

use Doctrine\DBAL\Connection;

/**
 * Reads users (spec 9.1). Writes for registration, login and profile changes
 * are added with the endpoints that need them (B1 PR 2, B2).
 *
 * Deleted users (status = deleted) are never returned: to the rest of the
 * app they don't exist.
 */
final class UserRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function findById(int $id): ?User
    {
        return $this->findOneBy('id', $id);
    }

    public function findByUuid(string $uuid): ?User
    {
        if (!preg_match('/^[0-9a-f-]{36}$/', $uuid)) {
            return null;
        }

        return $this->findOneBy('uuid', $uuid);
    }

    /** The email is matched lowercased, the way it is stored. */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy('email', mb_strtolower(trim($email)));
    }

    /** Used to stop the last active super admin from being demoted, suspended or deleted. */
    public function countActiveSuperAdmins(): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM users WHERE role = ? AND status = ?',
            [User::ROLE_SUPER_ADMIN, User::STATUS_ACTIVE],
        );
    }

    /** @param 'id'|'uuid'|'email' $column */
    private function findOneBy(string $column, int|string $value): ?User
    {
        $row = $this->db->createQueryBuilder()
            ->select('*')
            ->from('users')
            ->where($column . ' = :value')
            ->andWhere('status <> :deleted')
            ->setParameter('value', $value)
            ->setParameter('deleted', User::STATUS_DELETED)
            ->setMaxResults(1)
            ->fetchAssociative();

        return $row === false ? null : User::fromRow($row);
    }
}
