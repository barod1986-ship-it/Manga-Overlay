<?php

declare(strict_types=1);

namespace MOL\Database;

use RuntimeException;

final class Migrator implements MigrationRunner
{
    public function migrate(): void
    {
        $database = $this->database();
        $this->loadUpgradeApi();

        foreach (Schema::statements($database->prefix, $database->get_charset_collate()) as $statement) {
            dbDelta($statement);
        }

        $this->assertInnoDb($database);
    }

    public function removeSchema(): void
    {
        $database = $this->database();
        $tables = array_reverse((new TableNames($database->prefix))->all());

        foreach ($tables as $table) {
            $result = $database->query("DROP TABLE IF EXISTS `{$table}`");
            if (false === $result) {
                throw DatabaseException::fromWpdb($database, sprintf('Dropping table %s', $table));
            }
        }
    }

    private function database(): \wpdb
    {
        global $wpdb;

        if (! $wpdb instanceof \wpdb) {
            throw new RuntimeException('WordPress database connection is unavailable.');
        }

        return $wpdb;
    }

    private function loadUpgradeApi(): void
    {
        if (! function_exists('dbDelta')) {
            if (! defined('ABSPATH')) {
                throw new RuntimeException('WordPress is not loaded.');
            }
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        if (! function_exists('dbDelta')) {
            throw new RuntimeException('WordPress dbDelta() is unavailable.');
        }
    }

    private function assertInnoDb(\wpdb $database): void
    {
        $tables = new TableNames($database->prefix);

        foreach ($tables->all() as $table) {
            $query = $database->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $table
            );
            $engine = $database->get_var($query);

            if (! is_string($engine) || 0 !== strcasecmp('InnoDB', $engine)) {
                throw new DatabaseException(sprintf('Table %s must exist and use InnoDB.', $table));
            }
        }
    }
}
