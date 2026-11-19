<?php

declare(strict_types=1);

/*
 * Doctrine Migrations configuration (spec 9: every schema change is a
 * migration; nobody edits the database by hand).
 *
 * Create a migration:  vendor/bin/doctrine-migrations generate
 * Run them:            composer migrate
 * Undo the last one:   composer migrate:prev
 *
 * File names are timestamps (Version20261120143000.php), so P1, P2 and P5
 * adding migrations in parallel never collide. A migration that references
 * another table must be newer than the one that creates it.
 */

return [
    'table_storage' => [
        'table_name' => 'doctrine_migration_versions',
    ],
    'migrations_paths' => [
        'Migrations' => __DIR__ . '/migrations',
    ],
    'all_or_nothing' => false,
    'transactional' => false,
    'check_database_platform' => true,
    'organize_migrations' => 'none',
];
