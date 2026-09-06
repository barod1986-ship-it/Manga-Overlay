<?php
declare(strict_types=1);

namespace MOL\Frontend;

use MOL\Domain\Fault;

final class Routes
{
	public static ?array $chapter = null;
	public static ?array $profile = null;
	private static bool $custom = false;

	public static function register(): void
	{
		add_rewrite_rule('^series/([^/]+)/chapter/([^/]+)/edit/?$', 'index.php?post_type=mol_work&name=$matches[1]&mol_chapter_slug=$matches[2]&mol_editor=1', 'top');
		add_rewrite_rule('^series/([^/]+)/chapter/([^/]+)/?$', 'index.php?post_type=mol_work&name=$matches[1]&mol_chapter_slug=$matches[2]', 'top');
		add_rewrite_rule('^u/([^/]+)/?$', 'index.php?mol_profile=$matches[1]', 'top');
	}

	public static function boot(): void
	{
		add_action('init', static function (): void {
			self::register();
			if (get_option('mol_public_routes_version') !== '2') {
				flush_rewrite_rules(false);
				update_option('mol_public_routes_version', '2');
			}
		}, 20);
		add_filter('query_vars', static fn (array $vars): array => array_merge($vars, ['mol_chapter_slug', 'mol_profile', 'mol_editor']));
		add_action('template_redirect', [self::class, 'resolve'], 0);
		add_filter('redirect_canonical', static fn ($url) => (self::$custom || is_post_type_archive('mol_work')) ? false : $url);
		add_filter('template_include', [self::class, 'template']);
		add_action('wp_enqueue_scripts', [self::class, 'assets']);
	}

	public static function resolve(): void
	{
		if ((string) get_query_var('mol_editor') === '1') {
			self::$custom = true;
			EditorSite::resolve();
			return;
		}
		$slug = get_query_var('mol_chapter_slug');
		$username = get_query_var('mol_profile');
		self::$custom = (bool) ($slug || $username);
		if (!self::$custom) {
			return;
		}
		// Reader pages can include account progress and a REST nonce. Never share-cache them.
		nocache_headers();
		try {
			if ($username) {
				self::$profile = PublicSite::profile((string) $username);
				return;
			}
			$work_id = get_queried_object_id();
			foreach (mol_get_work_chapters($work_id) as $chapter) {
				if (rawurldecode($chapter['slug']) === rawurldecode((string) $slug)) {
					self::$chapter = $chapter;
					return;
				}
			}
			throw Fault::missing();
		} catch (Fault $error) {
			global $wp_query;
			$wp_query->set_404();
			status_header(404);
		}
	}

	public static function template(string $template): string
	{
		if (EditorSite::$context) {
			return dirname(__DIR__, 2) . '/templates/editor.php';
		}
		$name = self::$chapter ? 'templates/reader.php' : (self::$profile ? 'author.php' : (self::$custom ? '404.php' : ''));
		return $name ? (locate_template($name) ?: $template) : $template;
	}

	public static function assets(): void
	{
		if (EditorSite::$context) {
			EditorSite::assets();
			return;
		}
		if (!self::$chapter) {
			return;
		}
		$url = plugin_dir_url(dirname(__DIR__, 2) . '/manga-overlay-core.php') . 'assets/dist/reader/';
		wp_enqueue_style('mol-reader', $url . 'reader.css', [], \MOL\Plugin::VERSION);
		wp_enqueue_script_module('mol-reader', $url . 'reader.js', [], \MOL\Plugin::VERSION);
	}
}
