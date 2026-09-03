<?php

declare(strict_types=1);

use MOL\Activation\RoleManager;
use MOL\Activation\VersionManager;
use MOL\Database\MigrationRunner;
use MOL\Support\Versions;

final class MolTestRole
{
    /** @var array<string, bool> */
    public array $capabilities;

    /** @param array<string, bool> $capabilities */
    public function __construct(array $capabilities = array())
    {
        $this->capabilities = $capabilities;
    }

    public function add_cap(string $capability): void
    {
        $this->capabilities[$capability] = true;
    }

    public function remove_cap(string $capability): void
    {
        unset($this->capabilities[$capability]);
    }

    public function has_cap(string $capability): bool
    {
        return $this->capabilities[$capability] ?? false;
    }
}

/** @var array<string, mixed> $molTestOptions */
$molTestOptions = array();
/** @var array<string, bool|null> $molTestAutoload */
$molTestAutoload = array();
/** @var array<string, MolTestRole> $molTestRoles */
$molTestRoles = array('administrator' => new MolTestRole(array('manage_options' => true)));
/** @var array<string, list<callable>> $molTestActions */
$molTestActions = array();
$molTestActivationCallback = null;

function get_option(string $name, mixed $default = false): mixed
{
    global $molTestOptions;
    return $molTestOptions[$name] ?? $default;
}

function add_option(string $name, mixed $value = '', string $deprecated = '', bool|null $autoload = null): bool
{
    global $molTestAutoload, $molTestOptions;
    unset($deprecated);
    if (array_key_exists($name, $molTestOptions)) {
        return false;
    }
    $molTestOptions[$name] = $value;
    $molTestAutoload[$name] = $autoload;
    return true;
}

function update_option(string $name, mixed $value, bool|null $autoload = null): bool
{
    global $molTestAutoload, $molTestOptions;
    $changed = ! array_key_exists($name, $molTestOptions) || $molTestOptions[$name] !== $value;
    $molTestOptions[$name] = $value;
    $molTestAutoload[$name] = $autoload;
    return $changed;
}

function delete_option(string $name): bool
{
    global $molTestAutoload, $molTestOptions;
    $existed = array_key_exists($name, $molTestOptions);
    unset($molTestOptions[$name], $molTestAutoload[$name]);
    return $existed;
}

function add_role(string $slug, string $label, array $capabilities = array()): MolTestRole|null
{
    global $molTestRoles;
    unset($label);
    if (isset($molTestRoles[$slug])) {
        return null;
    }
    $molTestRoles[$slug] = new MolTestRole($capabilities);
    return $molTestRoles[$slug];
}

function get_role(string $slug): MolTestRole|null
{
    global $molTestRoles;
    return $molTestRoles[$slug] ?? null;
}

function remove_role(string $slug): void
{
    global $molTestRoles;
    unset($molTestRoles[$slug]);
}

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
    global $molTestActions;
    unset($priority, $acceptedArgs);
    $molTestActions[$hook][] = $callback;
    return true;
}

function register_activation_hook(string $file, callable $callback): void
{
    global $molTestActivationCallback;
    unset($file);
    $molTestActivationCallback = $callback;
}

function plugin_dir_path(string $file): string
{
    return dirname($file) . DIRECTORY_SEPARATOR;
}

function esc_html_e(string $text, string $domain = 'default'): void
{
    unset($domain);
    echo htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function molTestAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (! is_readable($autoload)) {
    throw new RuntimeException('Run Composer before the bootstrap smoke test.');
}
require_once $autoload;

$migrations = new class() implements MigrationRunner {
    public int $runs = 0;

    public function migrate(): void
    {
        ++$this->runs;
    }
};

$roles = new RoleManager();
molTestAssert($roles->synchronize(), 'Role synchronization failed.');

foreach (RoleManager::managedRoleSlugs() as $roleSlug) {
    $role = get_role($roleSlug);
    molTestAssert(null !== $role, sprintf('Role %s was not created.', $roleSlug));
    molTestAssert($role->has_cap('read'), sprintf('Role %s cannot read.', $roleSlug));
    $expectedCapabilities = RoleManager::capabilitiesForRole($roleSlug);
    foreach (RoleManager::canonicalCapabilities() as $capability) {
        $expected = in_array($capability, $expectedCapabilities, true);
        molTestAssert(
            $expected === $role->has_cap($capability),
            sprintf('%s has an incorrect value for %s.', $roleSlug, $capability)
        );
    }
}

$translator = get_role('mol_translator');
molTestAssert(null !== $translator && ! $translator->has_cap('mol_manage_content'), 'Translator gained content management.');
$translator->add_cap('mol_manage_content');
molTestAssert($roles->synchronize(), 'Role re-synchronization failed.');
molTestAssert(! $translator->has_cap('mol_manage_content'), 'Role re-synchronization kept a stale capability.');
$manager = get_role('mol_manager');
molTestAssert(null !== $manager, 'Manager role was not created.');
$administrator = get_role('administrator');
molTestAssert(null !== $administrator, 'Administrator fixture is missing.');
foreach (RoleManager::canonicalCapabilities() as $capability) {
    molTestAssert($manager->has_cap($capability), sprintf('Manager is missing %s.', $capability));
    molTestAssert($administrator->has_cap($capability), sprintf('Administrator is missing %s.', $capability));
}

$versions = new VersionManager($roles, $migrations);
$versions->activate();
molTestAssert(Versions::DATABASE === get_option(Versions::DATABASE_OPTION), 'Database baseline was not stored.');
molTestAssert(Versions::ROLES === get_option(Versions::ROLES_OPTION), 'Roles version was not stored.');
molTestAssert(false === $molTestAutoload[Versions::DATABASE_OPTION], 'Database version must not autoload.');
molTestAssert(false === $molTestAutoload[Versions::ROLES_OPTION], 'Roles version must not autoload.');
molTestAssert(1 === $migrations->runs, 'Database migration did not run exactly once.');

$versions->maybeUpgrade();
molTestAssert(1 === $migrations->runs, 'Current database migration ran more than once.');

$molTestOptions[Versions::DATABASE_OPTION] = '999';
$versions->maybeUpgrade();
molTestAssert('999' === get_option(Versions::DATABASE_OPTION), 'Bootstrap downgraded the DB version.');
molTestAssert(1 === $migrations->runs, 'Bootstrap migrated a future DB version.');

$molTestOptions[Versions::DATABASE_OPTION] = '0';
$failingMigrations = new class() implements MigrationRunner {
    public function migrate(): void
    {
        throw new RuntimeException('Intentional migration failure.');
    }
};
try {
    (new VersionManager($roles, $failingMigrations))->activate();
    throw new RuntimeException('A failed migration did not throw.');
} catch (RuntimeException $error) {
    molTestAssert('Intentional migration failure.' === $error->getMessage(), 'Migration failure was replaced.');
}
molTestAssert('0' === get_option(Versions::DATABASE_OPTION), 'A failed migration advanced the DB version.');
$molTestOptions[Versions::DATABASE_OPTION] = '999';

if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/tests/wordpress/');
}
require dirname(__DIR__) . '/manga-overlay-core.php';
molTestAssert(is_callable($molTestActivationCallback), 'Activation hook was not registered.');
molTestAssert(isset($molTestActions['plugins_loaded'][0]), 'Plugin boot hook was not registered.');

call_user_func($molTestActivationCallback);
call_user_func($molTestActions['plugins_loaded'][0]);

echo "Manga Overlay bootstrap smoke test passed.\n";
