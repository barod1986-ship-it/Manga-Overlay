<?php

declare(strict_types=1);

namespace MOL\Activation;

use MOL\Content\RewriteManager;
use MOL\Content\WorkContent;

final class Activator
{
    public static function activate(): void
    {
        (new VersionManager())->activate();

        $content = new WorkContent();
        $content->register();
        $content->synchronizeCanonicalTerms();

        (new RewriteManager())->activate();
    }
}
