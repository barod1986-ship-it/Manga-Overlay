<?php

// WP-CLI eval-file injects bootstrap code before this file, so strict_types
// cannot legally be the first evaluated statement here.

use MOL\Content\WorkContent;
use MOL\Content\WorkMeta;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ContributionRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\PageRepository;

function molThemeIntegrationAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function molThemeIntegrationTerm(string $taxonomy, string $slug, string $name): int
{
    $existing = term_exists($slug, $taxonomy);
    if (is_array($existing)) {
        return (int) $existing['term_id'];
    }
    if (is_int($existing)) {
        return $existing;
    }
    $created = wp_insert_term($name, $taxonomy, array('slug' => $slug));
    molThemeIntegrationAssert(! is_wp_error($created), sprintf('Could not create term %s.', $slug));

    return (int) $created['term_id'];
}

global $wpdb;
molThemeIntegrationAssert($wpdb instanceof wpdb, 'WordPress did not expose wpdb.');
molThemeIntegrationAssert('manga-overlay-theme' === get_stylesheet(), 'The Manga Overlay theme is not active.');
molThemeIntegrationAssert(current_theme_supports('title-tag'), 'Title-tag support is missing.');
molThemeIntegrationAssert(current_theme_supports('post-thumbnails'), 'Thumbnail support is missing.');
molThemeIntegrationAssert(has_image_size('mol-cover-card'), 'Cover image size is missing.');
molThemeIntegrationAssert(str_contains(mol_theme_language_attributes(), 'dir="rtl"'), 'The public UI is not RTL.');

foreach (array(
    'front-page.php',
    'archive-mol_work.php',
    'single-mol_work.php',
    'chapter-reader.php',
    'header-reader.php',
    'footer-reader.php',
    'assets/css/reader.css',
    'assets/js/reader.js',
    'header.php',
    'footer.php',
    'theme.json',
) as $requiredFile) {
    molThemeIntegrationAssert(is_file(get_template_directory() . '/' . $requiredFile), sprintf('%s is missing.', $requiredFile));
}

molThemeIntegrationTerm(WorkContent::GENRE_TAXONOMY, 't08-adventure', 'مغامرة');
molThemeIntegrationTerm(WorkContent::SOURCE_LANGUAGE_TAXONOMY, 'ja', 'اليابانية');
molThemeIntegrationTerm(WorkContent::WORK_STATUS_TAXONOMY, 'ongoing', 'مستمر');

$workId = wp_insert_post(array(
    'post_type' => WorkContent::POST_TYPE,
    'post_status' => 'publish',
    'post_title' => 'T08 Theme Work',
    'post_content' => '<p>وصف آمن للعمل.</p>',
), true);
molThemeIntegrationAssert(is_int($workId) && $workId > 0, 'Could not create the theme work.');
foreach (array(
    WorkContent::WORK_TYPE_TAXONOMY => 'manga',
    WorkContent::GENRE_TAXONOMY => 't08-adventure',
    WorkContent::SOURCE_LANGUAGE_TAXONOMY => 'ja',
    WorkContent::WORK_STATUS_TAXONOMY => 'ongoing',
) as $taxonomy => $term) {
    molThemeIntegrationAssert(! is_wp_error(wp_set_object_terms($workId, $term, $taxonomy)), sprintf('Could not assign %s.', $taxonomy));
}
update_post_meta($workId, WorkMeta::ALT_TITLES, array('Theme Alias'));
update_post_meta($workId, WorkMeta::DEFAULT_READER_MODE, 'paged');
update_post_meta($workId, WorkMeta::READING_DIRECTION, 'rtl');

$chapters = new ChapterRepository($wpdb);
$publishedChapter = $chapters->insert(array(
    'work_id' => $workId,
    'chapter_label' => '1',
    'sort_order' => 1,
    'title' => 'Theme Chapter',
    'slug' => 'theme-chapter',
    'translation_status' => 'completed',
    'is_published' => true,
    'published_at' => gmdate('Y-m-d H:i:s'),
    'created_by' => get_current_user_id(),
));
$chapters->insert(array(
    'work_id' => $workId,
    'chapter_label' => 'draft',
    'sort_order' => 2,
    'title' => 'Hidden Theme Chapter',
    'slug' => 'hidden-theme-chapter',
    'translation_status' => 'in_progress',
    'is_published' => false,
    'created_by' => get_current_user_id(),
));

$attachmentId = wp_insert_attachment(array(
    'post_title' => 'T09 Reader Page',
    'post_status' => 'inherit',
    'post_mime_type' => 'image/png',
), '', 0, true);
molThemeIntegrationAssert(is_int($attachmentId) && $attachmentId > 0, 'Could not create the reader attachment.');
update_post_meta($attachmentId, '_wp_attached_file', '2026/09/t09-reader-page.png');
$pages = new PageRepository($wpdb);
$pageId = $pages->insert($publishedChapter, 0, $attachmentId, 800, 1200);
$elements = new ElementRepository($wpdb);
$elementId = $elements->insert(array(
    'page_id' => $pageId,
    'target_lang' => 'ar',
    'element_type' => 'bubble',
    'x_unit' => 250000,
    'y_unit' => 100000,
    'w_unit' => 500000,
    'h_unit' => 200000,
    'content' => 'ترجمة القارئ',
    'style' => array(
        'fontId' => 'cairo',
        'fontSizeUnit' => 30000,
        'fontWeight' => 700,
        'lineHeight' => 1.3,
        'textAlign' => 'center',
        'color' => '#111111',
        'backgroundColor' => '#FFFFFF',
        'backgroundOpacity' => 1,
        'borderColor' => '#111111',
        'borderWidthUnit' => 1000,
        'paddingUnit' => 10000,
        'shape' => 'ellipse',
    ),
    'created_by' => get_current_user_id(),
));
$contributions = new ContributionRepository($wpdb);
$contributions->upsert(
    $elementId,
    get_current_user_id(),
    $workId,
    $publishedChapter,
    true
);

wp_set_current_user(0);
$library = mol_theme_library_data(array(
    'search' => 'T08 Theme Work',
    'genre' => array('t08-adventure'),
    'type' => 'manga',
    'source_lang' => 'ja',
    'work_status' => 'ongoing',
    'translation_status' => 'completed',
    'sort' => 'latest_work',
    'page' => 1,
    'per_page' => 12,
));
molThemeIntegrationAssert(200 === $library['status'], 'Theme library request failed.');
molThemeIntegrationAssert(1 === (int) ($library['meta']['total'] ?? 0), 'Theme library filtering drifted.');
molThemeIntegrationAssert($workId === (int) ($library['data'][0]['id'] ?? 0), 'Theme library returned the wrong work.');

$work = mol_theme_work_data($workId);
molThemeIntegrationAssert(200 === $work['status'], 'Theme work detail failed.');
molThemeIntegrationAssert(array('Theme Alias') === ($work['data']['alt_titles'] ?? null), 'Theme alt titles drifted.');
molThemeIntegrationAssert('paged' === ($work['data']['default_reader_mode'] ?? null), 'Theme reader mode drifted.');

$chapterResult = mol_theme_work_chapters_data($workId, 1, 50);
molThemeIntegrationAssert(200 === $chapterResult['status'], 'Theme chapter list failed.');
molThemeIntegrationAssert(1 === (int) ($chapterResult['meta']['total'] ?? 0), 'Draft chapter leaked into theme data.');
molThemeIntegrationAssert($publishedChapter === (int) ($chapterResult['data'][0]['id'] ?? 0), 'Theme chapter list drifted.');

$chapterUrl = mol_theme_chapter_url($workId, 'theme-chapter');
molThemeIntegrationAssert(str_ends_with($chapterUrl, '/chapter/theme-chapter/'), 'Theme chapter permalink drifted.');

$reader = mol_theme_reader_context($workId, 'theme-chapter');
molThemeIntegrationAssert(200 === $reader['status'], 'Theme reader context failed.');
molThemeIntegrationAssert(1 === count($reader['data']['pages']), 'Reader context omitted pages.');
molThemeIntegrationAssert(1 === (int) $reader['data']['element_count'], 'Reader context omitted overlays.');
molThemeIntegrationAssert(1 === count($reader['data']['contributors']), 'Reader context omitted contributors.');
molThemeIntegrationAssert('paged' === $reader['data']['reader_mode'], 'Reader mode priority drifted.');
molThemeIntegrationAssert('rtl' === $reader['data']['direction'], 'Reader direction drifted.');
$missingReader = mol_theme_reader_context($workId, 'missing-chapter');
molThemeIntegrationAssert(404 === $missingReader['status'], 'Missing reader route did not resolve to 404.');

do_action('wp_enqueue_scripts');
molThemeIntegrationAssert(wp_style_is('manga-overlay-theme', 'enqueued'), 'Theme stylesheet was not enqueued.');
molThemeIntegrationAssert(wp_script_is('manga-overlay-theme', 'enqueued'), 'Theme script was not enqueued.');

ob_start();
get_template_part('template-parts/work-card', null, array(
    'work' => $library['data'][0],
    'priority' => true,
));
$cardMarkup = (string) ob_get_clean();
molThemeIntegrationAssert(str_contains($cardMarkup, 'T08 Theme Work'), 'Work card omitted the title.');
molThemeIntegrationAssert(str_contains($cardMarkup, 'loading="eager"'), 'Priority work card did not eagerly load its cover.');
molThemeIntegrationAssert(str_contains($cardMarkup, 'width="800"'), 'Fallback cover dimensions are missing.');

$unsafeQuery = mol_theme_library_query(array('search' => '<script>alert(1)</script>'));
ob_start();
get_template_part('template-parts/library-filters', null, array(
    'query' => $unsafeQuery,
    'options' => mol_theme_filter_options(),
));
$filterMarkup = (string) ob_get_clean();
molThemeIntegrationAssert(! str_contains($filterMarkup, '<script>alert(1)</script>'), 'Filter search was not escaped.');
molThemeIntegrationAssert(str_contains($filterMarkup, 'name="genre[]"'), 'Genre filter is missing.');
molThemeIntegrationAssert(str_contains($filterMarkup, 'name="translation_status"'), 'Translation-status filter is missing.');

echo "Manga Overlay theme integration passed.\n";
