<?php

declare(strict_types=1);

namespace MOL\Activation;

use MOL\Support\Versions;

final class VersionManager
{
    public function __construct(private readonly RoleManager $roles = new RoleManager())
    {
    }

    public function activate(): void
    {
        $this->ensureDatabaseBaseline();
        $this->synchronizeRolesWhenNeeded();
    }

    public function maybeUpgrade(): void
    {
        $this->ensureDatabaseBaseline();
        $this->synchronizeRolesWhenNeeded();
    }

    private function ensureDatabaseBaseline(): void
    {
        if (false !== get_option(Versions::DATABASE_OPTION, false)) {
            return;
        }

        add_option(Versions::DATABASE_OPTION, Versions::DATABASE, '', false);
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
