<?php

declare(strict_types=1);

namespace MOL\Activation;

final class Activator
{
    public static function activate(): void
    {
        (new VersionManager())->activate();
    }
}
