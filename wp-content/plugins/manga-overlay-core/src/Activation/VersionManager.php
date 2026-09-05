<?php

declare(strict_types=1);

namespace MOL\Activation;

use MOL\Database\MigrationRunner;
use MOL\Database\Migrator;
use MOL\Support\Versions;

final class VersionManager
{
    public function __construct(
        private readonly RoleManager $roles = new RoleManager(),
        private readonly MigrationRunner $migrations = new Migrator()
    ) {
    }

    public function activate(): void
    {
        $this->migrateDatabaseWhenNeeded();
        $this->synchronizeRolesWhenNeeded();
    }

    public function maybeUpgrade(): void
    {
        $this->migrateDatabaseWhenNeeded();
        $this->synchronizeRolesWhenNeeded();
    }

    private function migrateDatabaseWhenNeeded(): void
    {
        $installed = get_option(Versions::DATABASE_OPTION, false);
        $installedVersion = false === $installed ? '0' : (string) $installed;

        // Preserve unknown or newer version markers rather than risking a
        // destructive downgrade from a bootstrap request.
        if (1 !== preg_match('/^\d+(?:\.\d+)*$/', $installedVersion)) {
            return;
        }

        if (version_compare($installedVersion, Versions::DATABASE, '>=')) {
            return;
        }

        $this->migrations->migrate();
        update_option(Versions::DATABASE_OPTION, Versions::DATABASE, false);
    }

    private function synchronizeRolesWhenNeeded(): void
    {
        if (Versions::ROLES === (string) get_option(Versions::ROLES_OPTION, '')) {
            return;
        }

        if ($this->roles->synchronize()) {
            update_option(Versions::ROLES_OPTION, Versions::ROLES, false);
        }
    }
}
