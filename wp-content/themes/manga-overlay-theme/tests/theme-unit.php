<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function sanitize_text_field(string $value): string
{
    return trim(strip_tags($value));
}

function sanitize_key(string $value): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? '';
}

function sanitize_title(string $value): string
{
    $value = strtolower(trim($value));

    return trim(preg_replace('/[^a-z0-9]+/', '-', $value) ?? '', '-');
}

function esc_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr(string $value): string
{
    return esc_html($value);
}

function esc_url(string $value): string
{
    return esc_html($value);
}

function esc_html__(string $value, string $domain = ''): string
{
    unset($domain);

    return esc_html($value);
}

function esc_attr__(string $value, string $domain = ''): string
{
    unset($domain);

    return esc_attr($value);
}

function __(string $value, string $domain = ''): string
{
    unset($domain);

    return $value;
}

function get_post_type_archive_link(string $postType): string
{
    unset($postType);

    return 'https://example.test/library/';
}

function home_url(string $path = ''): string
{
    return 'https://example.test' . $path;
}

/** @param array<string, mixed> $arguments */
function add_query_arg(array $arguments, string $url): string
{
    if (empty($arguments)) {
        return $url;
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($arguments);
}

function get_permalink(int $postId): string
{
    return 'https://example.test/series/work-' . $postId . '/';
}

function trailingslashit(string $value): string
{
    return rtrim($value, '/') . '/';
}

function get_language_attributes(string $doctype = 'html'): string
{
    unset($doctype);

    return 'lang="en-US" dir="ltr"';
}

function molThemeUnitAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

require dirname(__DIR__) . '/inc/query.php';
require dirname(__DIR__) . '/inc/template-tags.php';

$query = mol_theme_library_query(array(
    'search' => '  <b>Alpha</b>  ',
    'type' => 'Manga',
    'genre' => array('Action', 'action', '', array('invalid')),
    'source_lang' => 'JA',
    'work_status' => 'On Going',
    'translation_status' => 'completed',
    'sort' => 'title_asc',
    'library_page' => '3',
    'per_page' => '48',
));
molThemeUnitAssert('Alpha' === $query['search'], 'Search sanitization drifted.');
molThemeUnitAssert('manga' === $query['type'], 'Work type sanitization drifted.');
molThemeUnitAssert(array('action') === $query['genre'], 'Genre normalization drifted.');
molThemeUnitAssert('ja' === $query['source_lang'], 'Source language normalization drifted.');
molThemeUnitAssert('on-going' === $query['work_status'], 'Work status normalization drifted.');
molThemeUnitAssert('completed' === $query['translation_status'], 'Translation status drifted.');
molThemeUnitAssert('title_asc' === $query['sort'], 'Sort drifted.');
molThemeUnitAssert(3 === $query['page'] && 48 === $query['per_page'], 'Pagination drifted.');

$bounded = mol_theme_library_query(array(
    'translation_status' => 'private',
    'sort' => 'invented',
    'page' => '0',
    'per_page' => '999',
));
molThemeUnitAssert('' === $bounded['translation_status'], 'Invalid translation status was retained.');
molThemeUnitAssert('latest_chapter' === $bounded['sort'], 'Invalid sort was retained.');
molThemeUnitAssert(1 === $bounded['page'] && 100 === $bounded['per_page'], 'Pagination bounds drifted.');

$compact = mol_theme_compact_library_query(array('genre' => array('action'), 'page' => 1), false);
molThemeUnitAssert(! isset($compact['page']), 'Compact query retained page unexpectedly.');
molThemeUnitAssert(array('action') === $compact['genre'], 'Compact query dropped genres.');

molThemeUnitAssert(50 === mol_theme_translation_percent(array('total' => 4, 'completed' => 2)), 'Translation percent drifted.');
molThemeUnitAssert(0 === mol_theme_translation_percent(array('total' => 0)), 'Empty translation percent drifted.');
molThemeUnitAssert('مانجا' === mol_theme_work_type_label('manga'), 'Arabic work-type label drifted.');
molThemeUnitAssert('يحتاج مراجعة' === mol_theme_translation_status_label('needs_review'), 'Arabic status label drifted.');
molThemeUnitAssert('م' === mol_theme_initial('مانجا'), 'Arabic initial drifted.');

$chapterUrl = mol_theme_chapter_url(42, 'chapter-one');
molThemeUnitAssert('https://example.test/series/work-42/chapter/chapter-one/' === $chapterUrl, 'Chapter URL drifted.');
$editorUrl = mol_theme_chapter_url(42, 'chapter-one', true);
molThemeUnitAssert(str_ends_with($editorUrl, '/chapter/chapter-one/edit/'), 'Editor URL drifted.');

$pagination = mol_theme_pagination_markup(
    3,
    8,
    'https://example.test/library/',
    array('genre' => array('action')),
    'library_page'
);
molThemeUnitAssert(str_contains($pagination, 'aria-current="page">3</span>'), 'Current pagination page is missing.');
molThemeUnitAssert(str_contains($pagination, 'genre%5B0%5D=action'), 'Pagination did not preserve the genre filter.');
molThemeUnitAssert(str_contains($pagination, 'library_page=4'), 'Pagination next link drifted.');

$cover = mol_theme_cover_markup(array(
    'url' => 'https://example.test/cover.png',
    'width' => 800,
    'height' => 1200,
    'alt' => '"Cover"',
    'srcset' => null,
    'sizes' => null,
), true);
molThemeUnitAssert(str_contains($cover, 'width="800" height="1200"'), 'Cover dimensions are missing.');
molThemeUnitAssert(str_contains($cover, 'loading="eager" fetchpriority="high"'), 'Priority cover attributes drifted.');
molThemeUnitAssert(! str_contains($cover, 'alt=""Cover""'), 'Cover alt text was not escaped.');

$readerImage = mol_theme_reader_image_markup(array(
    'page_index' => 0,
    'natural_width' => 800,
    'natural_height' => 1200,
    'image' => array(
        'url' => 'https://example.test/page.png',
        'width' => 800,
        'height' => 1200,
        'alt' => null,
        'srcset' => null,
    ),
), true);
molThemeUnitAssert(str_contains($readerImage, 'width="800" height="1200"'), 'Reader image dimensions are missing.');
molThemeUnitAssert(str_contains($readerImage, 'loading="eager"'), 'First reader image was not prioritized.');
molThemeUnitAssert(str_contains($readerImage, 'alt="صفحة 1"'), 'Reader image fallback alt drifted.');
molThemeUnitAssert(
    'https://example.test/u/reader-name/' === mol_theme_profile_url('reader-name'),
    'Reader contributor profile URL drifted.'
);

$languageAttributes = mol_theme_language_attributes();
molThemeUnitAssert(str_contains($languageAttributes, 'lang="en-US"'), 'Language attribute was removed.');
molThemeUnitAssert(1 === substr_count($languageAttributes, 'dir="rtl"'), 'RTL direction was not forced exactly once.');

echo "Manga Overlay theme unit checks passed.\n";
