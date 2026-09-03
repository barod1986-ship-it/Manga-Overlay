<?php

declare(strict_types=1);

namespace MOL\Database;

interface MigrationRunner
{
    public function migrate(): void;
}
