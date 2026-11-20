<?php

declare(strict_types=1);

namespace App\Shared\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;

/**
 * Runs the Doctrine migrations from inside the app, with the same
 * configuration as the CLI (migrations.php). Used by the browser migration
 * page on staging, where there's no shell and the database can't be reached
 * from outside (spec 17.3).
 */
final class MigrationRunner
{
    public function __construct(
        private readonly Connection $db,
        private readonly string $configFile,
    ) {
    }

    /** @return list<string> versions not yet run, oldest first (e.g. "Migrations\Version20261119230000") */
    public function pending(): array
    {
        $dependencies = $this->dependencies();

        return array_values(array_map(
            static fn ($migration): string => (string) $migration->getVersion(),
            $dependencies->getMigrationStatusCalculator()->getNewMigrations()->getItems(),
        ));
    }

    /** @return list<string> versions already run */
    public function executed(): array
    {
        $dependencies = $this->dependencies();

        return array_values(array_map(
            static fn ($migration): string => (string) $migration->getVersion(),
            $dependencies->getMetadataStorage()->getExecutedMigrations()->getItems(),
        ));
    }

    /**
     * Runs every pending migration.
     *
     * @return list<string> the versions that ran
     */
    public function migrate(): array
    {
        $dependencies = $this->dependencies();
        $latest = $dependencies->getVersionAliasResolver()->resolveVersionAlias('latest');
        $plan = $dependencies->getMigrationPlanCalculator()->getPlanUntilVersion($latest);
        if (count($plan) === 0) {
            return [];
        }

        $versions = [];
        foreach ($plan->getItems() as $item) {
            $versions[] = (string) $item->getVersion();
        }
        $dependencies->getMigrator()->migrate($plan, new MigratorConfiguration());

        return $versions;
    }

    private function dependencies(): DependencyFactory
    {
        $dependencies = DependencyFactory::fromConnection(new PhpFile($this->configFile), new ExistingConnection($this->db));
        $dependencies->getMetadataStorage()->ensureInitialized();

        return $dependencies;
    }
}
