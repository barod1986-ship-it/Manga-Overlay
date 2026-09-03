<?php

declare(strict_types=1);

namespace MOL\Support;

final class Versions
{
    public const DATABASE_OPTION = 'mol_db_version';
    public const ROLES_OPTION = 'mol_roles_version';

    // T-04 creates the first complete domain schema.
    public const DATABASE = '1';
    public const ROLES = '1';
}
