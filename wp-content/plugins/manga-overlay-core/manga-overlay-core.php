<?php
/**
 * Plugin Name:       Manga Overlay Core
 * Plugin URI:        https://github.com/barod1986-ship-it/Manga-Overlay
 * Description:       Core domain, persistence, and editor services for Manga Overlay.
 * Version:           0.7.1
 * Requires at least: 7.1
 * Requires PHP:      8.4
 * Author:            Manga Overlay
 * Text Domain:       manga-overlay-core
 * Domain Path:       /languages
 *
 * @package MOL
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('MOL_PLUGIN_VERSION')) {
    define('MOL_PLUGIN_VERSION', '0.7.1');
}

if (! defined('MOL_PLUGIN_FILE')) {
    define('MOL_PLUGIN_FILE', __FILE__);
}

if (! defined('MOL_PLUGIN_DIR')) {
    define('MOL_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

$mol_autoloader = MOL_PLUGIN_DIR . 'vendor/autoload.php';

if (! is_readable($mol_autoloader)) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p>';
            esc_html_e(
                'Manga Overlay Core cannot start because its Composer autoloader is missing.',
                'manga-overlay-core'
            );
            echo '</p></div>';
        }
    );
    return;
}

require_once $mol_autoloader;
require_once MOL_PLUGIN_DIR . 'public-api.php';

register_activation_hook(MOL_PLUGIN_FILE, array(MOL\Activation\Activator::class, 'activate'));
add_action('plugins_loaded', array(MOL\Plugin::class, 'boot'), 5);
