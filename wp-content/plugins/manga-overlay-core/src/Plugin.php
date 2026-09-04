<?php

declare(strict_types=1);

namespace MOL;

use MOL\Admin\ChapterAdmin;
use MOL\Activation\VersionManager;
use MOL\Content\RewriteManager;
use MOL\Content\WorkContent;
use MOL\Frontend\EditorPage;
use MOL\REST\Routes;

final class Plugin
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        (new WorkContent())->registerHooks();
        (new RewriteManager())->registerHooks();
        (new Routes())->registerHooks();
        (new ChapterAdmin())->registerHooks();
        (new EditorPage())->registerHooks();
        (new VersionManager())->maybeUpgrade();
        self::$booted = true;
    }
}
