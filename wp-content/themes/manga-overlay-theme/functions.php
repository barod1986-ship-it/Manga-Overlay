<?php
declare(strict_types=1);

add_action('after_setup_theme', static function (): void {
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
});
add_action('wp_enqueue_scripts', static function (): void {
	wp_enqueue_style('mol-site', get_stylesheet_uri(), [], '0.1.1');
});
add_filter('body_class', static function (array $classes): array {
	if (class_exists('MOL\\Frontend\\Routes') && MOL\Frontend\Routes::$chapter) {
		$classes[] = 'mol-reading';
	}
	return $classes;
});

function mol_theme_status(string $status): string
{
	return ['untranslated' => 'لم يُترجم', 'in_progress' => 'قيد الترجمة', 'needs_review' => 'يحتاج مراجعة', 'completed' => 'مكتمل'][$status] ?? $status;
}

function mol_theme_cover(array $work, bool $eager = false): void
{
	$image = $work['cover'];
	if (!$image['url']) {
		echo '<div class="mol-cover mol-cover-empty"><span>الغلاف غير متاح</span></div>';
		return;
	}
	?>
	<img class="mol-cover" src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt'] ?? $work['title']); ?>" width="<?php echo (int) $image['width']; ?>" height="<?php echo (int) $image['height']; ?>" loading="<?php echo $eager ? 'eager' : 'lazy'; ?>" decoding="async" <?php if ($image['srcset']) : ?>srcset="<?php echo esc_attr($image['srcset']); ?>" sizes="(max-width: 600px) 42vw, 200px"<?php endif; ?>>
	<?php
}

function mol_theme_work_url(array $work): string
{
	return (string) get_permalink($work['id']);
}

function mol_theme_chapter_link(array $chapter): string
{
	return MOL\Frontend\PublicSite::chapter_url($chapter);
}
