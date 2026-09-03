<?php

declare(strict_types=1);

namespace MOL;

use MOL\Activation\VersionManager;

final class Plugin
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        (new VersionManager())->maybeUpgrade();
        self::$booted = true;
    }
}
