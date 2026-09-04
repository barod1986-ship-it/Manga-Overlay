<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @return array<string, string> */
function mol_theme_work_type_labels(): array
{
    return array(
        'manga' => 'مانجا',
        'manhwa' => 'مانهوا',
        'manhua' => 'مانهوا صينية',
        'comic' => 'كوميك',
        'webtoon' => 'ويب تون',
        'other' => 'أخرى',
    );
}

/** @return array<string, string> */
function mol_theme_translation_status_labels(): array
{
    return array(
        'untranslated' => 'غير مترجم',
        'in_progress' => 'قيد الترجمة',
        'completed' => 'مكتمل',
        'needs_review' => 'يحتاج مراجعة',
    );
}

function mol_theme_work_type_label(string $slug): string
{
    return mol_theme_work_type_labels()[$slug] ?? mol_theme_fallback_label($slug);
}

function mol_theme_translation_status_label(string $status): string
{
    return mol_theme_translation_status_labels()[$status] ?? mol_theme_fallback_label($status);
}

function mol_theme_reader_mode_label(string $mode): string
{
    return match ($mode) {
        'paged' => 'صفحات',
        'webtoon' => 'شريط عمودي',
        default => mol_theme_fallback_label($mode),
    };
}

function mol_theme_direction_label(string $direction): string
{
    return match ($direction) {
        'ltr' => 'من اليسار إلى اليمين',
        'rtl' => 'من اليمين إلى اليسار',
        default => mol_theme_fallback_label($direction),
    };
}

function mol_theme_taxonomy_label(string $taxonomy, string $slug): string
{
    if ('' === $slug) {
        return '';
    }
    $term = get_term_by('slug', $slug, $taxonomy);

    return $term instanceof WP_Term ? (string) $term->name : mol_theme_fallback_label($slug);
}

function mol_theme_fallback_label(string $slug): string
{
    return ucwords(str_replace(array('-', '_'), ' ', $slug));
}

/** @param array<string, mixed> $summary */
function mol_theme_translation_percent(array $summary): int
{
    $total = max(0, (int) ($summary['total'] ?? 0));
    $completed = max(0, min($total, (int) ($summary['completed'] ?? 0)));

    return $total > 0 ? (int) round(($completed / $total) * 100) : 0;
}

/** @param array<string, mixed> $cover */
function mol_theme_cover_markup(array $cover, bool $priority = false, string $className = ''): string
{
    $url = is_string($cover['url'] ?? null) ? $cover['url'] : '';
    if ('' === $url) {
        return '';
    }
    $width = max(1, (int) ($cover['width'] ?? 800));
    $height = max(1, (int) ($cover['height'] ?? 1200));
    $alt = is_string($cover['alt'] ?? null) ? $cover['alt'] : '';
    $srcset = is_string($cover['srcset'] ?? null) ? trim($cover['srcset']) : '';
    $sizes = is_string($cover['sizes'] ?? null) ? trim($cover['sizes']) : '';
    $attributes = array(
        'class="' . esc_attr(trim('mol-cover-image ' . $className)) . '"',
        'src="' . esc_url($url) . '"',
        'width="' . esc_attr((string) $width) . '"',
        'height="' . esc_attr((string) $height) . '"',
        'alt="' . esc_attr($alt) . '"',
        'decoding="async"',
        'loading="' . ($priority ? 'eager' : 'lazy') . '"',
    );
    if ($priority) {
        $attributes[] = 'fetchpriority="high"';
    }
    if ('' !== $srcset) {
        $attributes[] = 'srcset="' . esc_attr($srcset) . '"';
    }
    if ('' !== $sizes) {
        $attributes[] = 'sizes="' . esc_attr($sizes) . '"';
    }

    return '<img ' . implode(' ', $attributes) . '>';
}

function mol_theme_work_url(array $work): string
{
    $workId = max(0, (int) ($work['id'] ?? 0));
    $permalink = $workId > 0 ? get_permalink($workId) : false;

    return is_string($permalink) ? $permalink : home_url('/library/');
}

function mol_theme_chapter_url(int $workId, string $chapterSlug, bool $editor = false): string
{
    $workUrl = get_permalink($workId);
    if (! is_string($workUrl) || '' === $chapterSlug) {
        return home_url('/library/');
    }
    $url = trailingslashit($workUrl) . 'chapter/' . rawurlencode($chapterSlug) . '/';

    return $editor ? trailingslashit($url) . 'edit/' : $url;
}

/** @param array<string, mixed> $query */
function mol_theme_library_url(array $query = array()): string
{
    $archive = get_post_type_archive_link('mol_work');
    $base = is_string($archive) ? $archive : home_url('/library/');
    if (array() === $query) {
        return $base;
    }
    $compact = mol_theme_compact_library_query($query);
    if (isset($compact['page'])) {
        $compact['library_page'] = $compact['page'];
        unset($compact['page']);
    }

    return empty($compact) ? $base : add_query_arg($compact, $base);
}

/**
 * Build compact accessible pagination without mutating the global WordPress query.
 *
 * @param array<string, mixed> $query
 */
function mol_theme_pagination_markup(
    int $current,
    int $totalPages,
    string $baseUrl,
    array $query,
    string $pageKey = 'page'
): string {
    $current = max(1, $current);
    $totalPages = max(0, $totalPages);
    if ($totalPages <= 1) {
        return '';
    }

    $pageNumbers = array(1, $totalPages);
    for ($page = max(1, $current - 2); $page <= min($totalPages, $current + 2); $page++) {
        $pageNumbers[] = $page;
    }
    $pageNumbers = array_values(array_unique($pageNumbers));
    sort($pageNumbers);
    $items = array();
    $previous = null;
    foreach ($pageNumbers as $page) {
        if (null !== $previous && $page > $previous + 1) {
            $items[] = '<span class="mol-pagination__ellipsis" aria-hidden="true">…</span>';
        }
        if ($page === $current) {
            $items[] = sprintf(
                '<span class="mol-pagination__page is-current" aria-current="page">%s</span>',
                esc_html((string) $page)
            );
        } else {
            $pageQuery = $query;
            $pageQuery[$pageKey] = $page;
            $items[] = sprintf(
                '<a class="mol-pagination__page" href="%s">%s</a>',
                esc_url(add_query_arg($pageQuery, $baseUrl)),
                esc_html((string) $page)
            );
        }
        $previous = $page;
    }

    $previousLink = '';
    if ($current > 1) {
        $previousQuery = $query;
        $previousQuery[$pageKey] = $current - 1;
        $previousLink = sprintf(
            '<a class="mol-pagination__step" rel="prev" href="%s">%s %s</a>',
            esc_url(add_query_arg($previousQuery, $baseUrl)),
            mol_theme_icon('arrow-start'),
            esc_html__('السابق', 'manga-overlay-theme')
        );
    }
    $nextLink = '';
    if ($current < $totalPages) {
        $nextQuery = $query;
        $nextQuery[$pageKey] = $current + 1;
        $nextLink = sprintf(
            '<a class="mol-pagination__step" rel="next" href="%s">%s %s</a>',
            esc_url(add_query_arg($nextQuery, $baseUrl)),
            esc_html__('التالي', 'manga-overlay-theme'),
            mol_theme_icon('arrow-end')
        );
    }

    return sprintf(
        '<nav class="mol-pagination" aria-label="%s"><div>%s</div><div class="mol-pagination__numbers">%s</div><div>%s</div></nav>',
        esc_attr__('صفحات النتائج', 'manga-overlay-theme'),
        $previousLink,
        implode('', $items),
        $nextLink
    );
}

function mol_theme_format_date(?string $date): string
{
    if (null === $date || '' === $date) {
        return '';
    }
    $timestamp = strtotime($date);

    return false === $timestamp ? '' : wp_date(get_option('date_format'), $timestamp);
}

function mol_theme_language_attributes(): string
{
    $attributes = get_language_attributes('html');
    $attributes = preg_replace('/\sdir=("|\').*?\1/i', '', $attributes) ?? $attributes;

    return trim($attributes) . ' dir="rtl"';
}

function mol_theme_icon(string $name): string
{
    return match ($name) {
        'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>',
        'filter' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"></path></svg>',
        'close' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"></path></svg>',
        'paged' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM9 4v16"></path></svg>',
        'webtoon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v5H6zM6 10h12v5H6zM6 17h12v4H6z"></path></svg>',
        'arrow-start' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6-6 6 6 6"></path></svg>',
        'arrow-end' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 6 6 6-6 6"></path></svg>',
        'book' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A3.5 3.5 0 0 1 7.5 2H11v17H7.5A3.5 3.5 0 0 0 4 22zM20 5.5A3.5 3.5 0 0 0 16.5 2H13v17h3.5A3.5 3.5 0 0 1 20 22z"></path></svg>',
        default => '',
    };
}
