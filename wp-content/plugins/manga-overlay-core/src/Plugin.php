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
	public const VERSION = '0.9.0';

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
		Frontend\Routes::boot();
		add_filter('pre_insert_term', [WorkType::class, 'validate_type_term'], 10, 3);
		add_filter('pre_delete_post', [WorkDeletionPolicy::class, 'guard'], 10, 2);
		add_filter('pre_trash_post', [WorkDeletionPolicy::class, 'guard'], 10, 2);
		add_action('after_delete_post', [Database\PresetRepository::class, 'deleted_work'], 9, 2);
		add_action('after_delete_post', [Database\WorkMutationLock::class, 'release']);
		add_action('trashed_post', [Database\WorkMutationLock::class, 'release']);
		add_action('shutdown', [Database\WorkMutationLock::class, 'cleanup']);
		add_filter('pre_delete_attachment', [Security\AttachmentDeletionPolicy::class, 'guard'], 10, 2);
		global $wpdb;
		$runtime = new Services\ContentRuntime($wpdb);
		add_action('rest_api_init', [$runtime->controller, 'register']);
		$library = new REST\LibraryController(new Database\WorkRepository($wpdb), new Database\ProfileRepository($wpdb));
		add_action('rest_api_init', [$library, 'register']);
		$presets = new REST\PresetsController(new Services\PresetService($wpdb));
		add_action('rest_api_init', [$presets, 'register']);
		$elements = new REST\ElementsController(new Services\ElementService($wpdb));
		add_action('rest_api_init', [$elements, 'register']);
		$progress = new REST\ProgressController(new Services\ProgressService($wpdb));
		add_action('rest_api_init', [$progress, 'register']);
		add_filter('rest_post_dispatch', [REST\ContentController::class, 'response_headers'], 10, 3);
		new Admin\ContentScreen($runtime->chapters);
		add_action('add_meta_boxes_mol_work', [Admin\WorkMetadata::class, 'register']);
		add_action('save_post_mol_work', [Admin\WorkMetadata::class, 'save']);
		add_action('mol_cleanup_temporary_data', [Services\ContentRuntime::class, 'cleanup']);
		if (!wp_next_scheduled('mol_cleanup_temporary_data')) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'mol_cleanup_temporary_data');
		}
	}
}
