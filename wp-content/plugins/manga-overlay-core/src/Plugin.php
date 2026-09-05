<?php
declare(strict_types=1);

namespace MOL;

use MOL\Activation\Activator;
use MOL\Content\WorkType;
use MOL\Database\Migrator;
use MOL\Security\Roles;
use MOL\Security\WorkDeletionPolicy;

final class Plugin
{
	public const VERSION = '0.2.0';

	public static function boot(): void
	{
		try {
			if (get_option('mol_db_version') !== Migrator::VERSION) {
				Activator::upgrade();
			}
			Roles::install();
		} catch (\Throwable $error) {
			add_action('admin_notices', static function (): void {
				if (current_user_can('manage_options')) {
					echo '<div class="notice notice-error"><p>' . esc_html__('تعذر تهيئة Manga Overlay. تحقق من توافق قاعدة البيانات وإعدادات التثبيت.', 'manga-overlay-core') . '</p></div>';
				}
			});
			return;
		}
		add_action('init', [WorkType::class, 'register']);
		add_filter('pre_insert_term', [WorkType::class, 'validate_type_term'], 10, 3);
		add_filter('pre_delete_post', [WorkDeletionPolicy::class, 'guard'], 10, 2);
		add_filter('pre_trash_post', [WorkDeletionPolicy::class, 'guard'], 10, 2);
	}
}
