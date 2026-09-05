<?php
declare(strict_types=1);

namespace MOL\Content;

final class WorkType
{
	public const TYPES = ['manga' => 'مانجا', 'manhwa' => 'مانهوا', 'manhua' => 'مانها', 'comic' => 'قصص مصورة', 'webtoon' => 'ويب تون', 'other' => 'أخرى'];
	public const TAXONOMIES = ['mol_genre' => 'التصنيفات', 'mol_work_type' => 'نوع العمل', 'mol_source_language' => 'لغة المصدر', 'mol_work_status' => 'حالة العمل'];

	public static function register(): void
	{
		$capabilities = array_fill_keys([
			'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts',
			'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts',
			'edit_published_posts', 'create_posts',
		], 'mol_manage_content');
		// Keep edit_post/read_post/delete_post as core meta capabilities so WP maps by object ID.
		register_post_type('mol_work', [
			'labels' => ['name' => 'الأعمال', 'singular_name' => 'عمل', 'add_new_item' => 'إضافة عمل', 'edit_item' => 'تعديل العمل', 'search_items' => 'البحث في الأعمال'],
			'public' => true, 'show_in_rest' => true, 'has_archive' => 'library',
			'rewrite' => ['slug' => 'series', 'with_front' => false],
			'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
			'map_meta_cap' => true, 'capability_type' => 'post', 'capabilities' => $capabilities,
			'taxonomies' => array_keys(self::TAXONOMIES), 'menu_icon' => 'dashicons-book-alt',
		]);
		foreach (self::TAXONOMIES as $taxonomy => $label) {
			register_taxonomy($taxonomy, ['mol_work'], [
				'label' => $label, 'public' => true, 'show_in_rest' => true,
				'hierarchical' => $taxonomy === 'mol_genre',
				'capabilities' => array_fill_keys(['manage_terms', 'edit_terms', 'delete_terms', 'assign_terms'], 'mol_manage_content'),
			]);
		}
		$shared = ['single' => true, 'auth_callback' => [self::class, 'authorize_meta']];
		register_post_meta('mol_work', '_mol_alt_titles', $shared + [
			'type' => 'array', 'default' => [], 'sanitize_callback' => [self::class, 'sanitize_titles'],
			'show_in_rest' => ['schema' => ['type' => 'array', 'items' => ['type' => 'string']]],
		]);
		register_post_meta('mol_work', '_mol_default_reader_mode', $shared + [
			'type' => 'string', 'default' => 'webtoon',
			'sanitize_callback' => static fn ($value): string => $value === 'paged' ? 'paged' : 'webtoon',
			'show_in_rest' => ['schema' => ['type' => 'string', 'enum' => ['webtoon', 'paged']]],
		]);
		register_post_meta('mol_work', '_mol_reading_direction', $shared + [
			'type' => 'string', 'default' => 'rtl',
			'sanitize_callback' => static fn ($value): string => $value === 'ltr' ? 'ltr' : 'rtl',
			'show_in_rest' => ['schema' => ['type' => 'string', 'enum' => ['rtl', 'ltr']]],
		]);
	}

	public static function authorize_meta(bool $allowed, string $key, int $post_id, int $user_id): bool
	{
		return get_post_type($post_id) === 'mol_work' && user_can($user_id, 'mol_manage_content');
	}

	public static function sanitize_titles(mixed $value): array
	{
		if (!is_array($value)) {
			return [];
		}
		return array_values(array_unique(array_filter(array_map('sanitize_text_field', array_filter($value, 'is_string')))));
	}

	public static function validate_type_term(mixed $term, string $taxonomy, array $args): mixed
	{
		if ($taxonomy !== 'mol_work_type' || is_wp_error($term)) {
			return $term;
		}
		$slug = sanitize_title($args['slug'] ?? (string) $term);
		return isset(self::TYPES[$slug]) ? $term : new \WP_Error('mol_invalid_params', 'نوع العمل غير مدعوم.');
	}

	public static function seed_types(): void
	{
		foreach (self::TYPES as $slug => $label) {
			if (!term_exists($slug, 'mol_work_type')) {
				$result = wp_insert_term($label, 'mol_work_type', ['slug' => $slug]);
				if (is_wp_error($result)) {
					throw new \RuntimeException('Could not register work types.');
				}
			}
		}
	}
}
