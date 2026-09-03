<?php

declare(strict_types=1);

namespace MOL\Support;

final class Versions
{
    public const DATABASE_OPTION = 'mol_db_version';
    public const ROLES_OPTION = 'mol_roles_version';

    // T-03 owns no custom tables. T-04 will bump this when its first migration lands.
    public const DATABASE = '0';
    public const ROLES = '1';
}
