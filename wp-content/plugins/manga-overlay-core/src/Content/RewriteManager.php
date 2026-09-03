<?php

declare(strict_types=1);

namespace MOL\Content;

final class RewriteManager
{
    public const VERSION_OPTION = 'mol_rewrite_version';
    public const VERSION = '1';

    public function registerHooks(): void
    {
        add_action('init', array($this, 'registerRules'), 10);
        add_action('init', array($this, 'maybeFlush'), 99);
        add_filter('query_vars', array($this, 'registerQueryVars'));
    }

    public function registerRules(): void
    {
        add_rewrite_rule(
            '^series/([^/]+)/chapter/([^/]+)/edit/?$',
            'index.php?post_type=mol_work&name=$matches[1]&mol_chapter=$matches[2]&mol_editor=1',
            'top'
        );
        add_rewrite_rule(
            '^series/([^/]+)/chapter/([^/]+)/?$',
            'index.php?post_type=mol_work&name=$matches[1]&mol_chapter=$matches[2]',
            'top'
        );
        add_rewrite_rule(
            '^u/([^/]+)/?$',
            'index.php?author_name=$matches[1]',
            'top'
        );
    }

    /**
     * @param list<string> $queryVariables
     * @return list<string>
     */
    public function registerQueryVars(array $queryVariables): array
    {
        $queryVariables[] = 'mol_chapter';
        $queryVariables[] = 'mol_editor';

        return array_values(array_unique($queryVariables));
    }

    public function activate(): void
    {
        $this->registerRules();
        flush_rewrite_rules(false);
        $this->writeVersion();
    }

    public function maybeFlush(): void
    {
        if (self::VERSION === (string) get_option(self::VERSION_OPTION, '')) {
            return;
        }

        flush_rewrite_rules(false);
        $this->writeVersion();
    }

    private function writeVersion(): void
    {
        if (false === get_option(self::VERSION_OPTION, false)) {
            add_option(self::VERSION_OPTION, self::VERSION, '', false);
            return;
        }

        update_option(self::VERSION_OPTION, self::VERSION, false);
    }
}
