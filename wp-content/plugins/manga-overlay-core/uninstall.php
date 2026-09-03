<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (1 !== (int) get_option('mol_delete_data_on_uninstall', 0)) {
    return;
}

$mol_autoloader = __DIR__ . '/vendor/autoload.php';
if (! is_readable($mol_autoloader)) {
    return;
}

require_once $mol_autoloader;

(new MOL\Database\Migrator())->removeSchema();
(new MOL\Activation\RoleManager())->uninstall();

delete_option(MOL\Support\Versions::DATABASE_OPTION);
delete_option(MOL\Support\Versions::ROLES_OPTION);
delete_option('mol_delete_data_on_uninstall');
