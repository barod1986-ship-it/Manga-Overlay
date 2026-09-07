<?php
declare(strict_types=1);

namespace MOL\Frontend;

use MOL\Database\ChapterRepository;
use MOL\Domain\Fault;

/** The plugin owns the editor shell; chapter content is loaded through authenticated REST. */
final class EditorSite
{
	public static ?array $context = null;

	public static function context(string $work_slug, string $chapter_slug): array
	{
		// Check access before looking up either slug, including for nonexistent chapters.
		if (!is_user_logged_in()) {
			throw new Fault('mol_not_authenticated', 'سجّل الدخول لفتح مساحة الترجمة.', 401);
		}
		if (!current_user_can('mol_use_editor')) {
			throw new Fault('mol_forbidden', 'لا تملك صلاحية دخول محرر الترجمة.', 403);
		}
		$work = get_page_by_path($work_slug, OBJECT, 'mol_work');
		if (!$work || $work->post_type !== 'mol_work' || !in_array($work->post_status, ['publish', 'draft', 'private', 'pending', 'future'], true)) {
			throw Fault::missing();
		}
		global $wpdb;
		foreach ((new ChapterRepository($wpdb))->for_work((int) $work->ID, false) as $chapter) {
			if (rawurldecode($chapter['slug']) === rawurldecode($chapter_slug)) {
				return [
					'chapterId' => $chapter['id'], 'workTitle' => $work->post_title,
					'api' => rest_url('mol/v1/'), 'nonce' => wp_create_nonce('wp_rest'),
					'userId' => get_current_user_id(), 'canManageWorkPresets' => current_user_can('mol_manage_work_presets'), 'canManageGlobalPresets' => current_user_can('mol_manage_global_presets'),
					'canEdit' => current_user_can('mol_edit_translations'),
					'canDelete' => current_user_can('mol_edit_translations') && current_user_can('mol_delete_translation_elements'),
					'backUrl' => $chapter['is_published'] && $work->post_status === 'publish' ? PublicSite::chapter_url($chapter) : home_url('/library/'),
					'backLabel' => $chapter['is_published'] && $work->post_status === 'publish' ? 'العودة إلى القارئ' : 'العودة إلى المكتبة',
				];
			}
		}
		throw Fault::missing();
	}

	public static function resolve(): void
	{
		nocache_headers();
		header('X-Content-Type-Options: nosniff');
		try {
			self::$context = self::context((string) get_query_var('name'), (string) get_query_var('mol_chapter_slug'));
			global $wp_query;
			$wp_query->is_404 = false;
			status_header(200);
			show_admin_bar(false);
			add_filter('pre_get_document_title', static fn (): string => 'محرر الترجمة — Manga Overlay');
		} catch (Fault $error) {
			if ($error->status === 401) {
				$path = '/series/' . rawurlencode(rawurldecode((string) get_query_var('name'))) . '/chapter/' . rawurlencode(rawurldecode((string) get_query_var('mol_chapter_slug'))) . '/edit/';
				wp_safe_redirect(wp_login_url(home_url($path)));
				exit;
			}
			wp_die(esc_html($error->getMessage()), esc_html('محرر الترجمة'), ['response' => $error->status, 'back_link' => false]);
		} catch (\Throwable $error) {
			wp_die(esc_html('تعذر فتح مساحة الترجمة. أعد المحاولة.'), esc_html('محرر الترجمة'), ['response' => 500]);
		}
	}

	public static function assets(): void
	{
		$url = plugin_dir_url(dirname(__DIR__, 2) . '/manga-overlay-core.php') . 'assets/dist/editor/';
		wp_enqueue_style('mol-editor', $url . 'editor.css', [], \MOL\Plugin::VERSION);
		wp_enqueue_script_module('mol-editor', $url . 'editor.js', [], \MOL\Plugin::VERSION);
	}
}
