<?php
/**
 * Plugin Name: Manga Overlay Core
 * Description: البنية الأساسية لمنصة القصص المصورة والترجمة العربية فوق الصور.
 * Version: 0.10.0
 * Requires at least: 7.1
 * Requires PHP: 8.4
 * Text Domain: manga-overlay-core
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$mol_autoloader = __DIR__ . '/vendor/autoload.php';
if (!is_readable($mol_autoloader)) {
	add_action('admin_notices', static function (): void {
		if (current_user_can('activate_plugins')) {
			echo '<div class="notice notice-error"><p>' . esc_html__('Manga Overlay: ملفات البناء غير مكتملة. شغّل composer dump-autoload داخل مجلد الإضافة.', 'manga-overlay-core') . '</p></div>';
		}
	});
	register_activation_hook(__FILE__, static function (): void {
		wp_die(esc_html__('Manga Overlay: يجب بناء Composer autoloader قبل التفعيل.', 'manga-overlay-core'));
	});
	return;
}
require_once $mol_autoloader;
require_once __DIR__ . '/src/Support/public-api.php';
unset($mol_autoloader);

register_activation_hook(__FILE__, [MOL\Activation\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, static function (): void {
	wp_clear_scheduled_hook('mol_cleanup_temporary_data');
	flush_rewrite_rules(false);
});
add_action('plugins_loaded', [MOL\Plugin::class, 'boot']);
