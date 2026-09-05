<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Execute an internal, read-only MOL REST request so the theme shares API DTOs and policies.
 *
 * @param array<string, mixed> $query
 * @return array{data: list<array<string, mixed>>|array<string, mixed>, meta: array<string, mixed>, error: array{code: string, message: string}|null, status: int}
 */
function mol_theme_rest_get(string $path, array $query = array()): array
{
    $empty = array('data' => array(), 'meta' => array(), 'error' => null, 'status' => 200);
    if (! class_exists('WP_REST_Request') || ! function_exists('rest_do_request')) {
        $empty['status'] = 503;
        $empty['error'] = array(
            'code' => 'mol_theme_core_unavailable',
            'message' => 'إضافة Manga Overlay Core غير متاحة حاليًا.',
        );

        return $empty;
    }

    $request = new WP_REST_Request('GET', $path);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    if (is_wp_error($response)) {
        $empty['status'] = 500;
        $empty['error'] = array(
            'code' => (string) $response->get_error_code(),
            'message' => $response->get_error_message(),
        );

        return $empty;
    }

    $status = (int) $response->get_status();
    $body = $response->get_data();
    if (! is_array($body)) {
        $body = array();
    }
    if ($status >= 400) {
        $empty['status'] = $status;
        $empty['error'] = array(
            'code' => is_string($body['code'] ?? null) ? $body['code'] : 'mol_theme_request_failed',
            'message' => is_string($body['message'] ?? null)
                ? $body['message']
                : 'تعذر تحميل البيانات المطلوبة.',
        );

        return $empty;
    }

    $data = $body['data'] ?? array();
    $meta = $body['meta'] ?? array();

    return array(
        'data' => is_array($data) ? $data : array(),
        'meta' => is_array($meta) || is_object($meta) ? (array) $meta : array(),
        'error' => null,
        'status' => $status,
    );
}

/** @param array<string, mixed> $query @return array<string, mixed> */
function mol_theme_library_data(array $query): array
{
    return mol_theme_rest_get('/mol/v1/library', mol_theme_library_query($query));
}

/** @return array<string, mixed> */
function mol_theme_work_data(int $workId): array
{
    return mol_theme_rest_get('/mol/v1/works/' . max(0, $workId));
}

/** @return array<string, mixed> */
function mol_theme_work_chapters_data(int $workId, int $page = 1, int $perPage = 50): array
{
    return mol_theme_rest_get(
        '/mol/v1/works/' . max(0, $workId) . '/chapters',
        array(
            'page' => max(1, $page),
            'per_page' => max(1, min(100, $perPage)),
        )
    );
}

/** @return array<string, mixed> */
function mol_theme_chapter_pages_data(int $chapterId): array
{
    return mol_theme_rest_get('/mol/v1/chapters/' . max(0, $chapterId) . '/pages');
}

/** @return array<string, mixed> */
function mol_theme_chapter_elements_data(int $chapterId, string $language = 'ar'): array
{
    return mol_theme_rest_get(
        '/mol/v1/chapters/' . max(0, $chapterId) . '/elements',
        array('lang' => $language)
    );
}

/** @return array<string, mixed> */
function mol_theme_chapter_contributors_data(int $chapterId): array
{
    return mol_theme_rest_get('/mol/v1/chapters/' . max(0, $chapterId) . '/contributors');
}

/** @return array<string, mixed> */
function mol_theme_all_work_chapters_data(int $workId): array
{
    if (function_exists('mol_get_work_chapters')) {
        return array(
            'data' => mol_get_work_chapters($workId),
            'meta' => array(),
            'error' => null,
            'status' => 200,
        );
    }

    $chapters = array();
    $page = 1;
    do {
        $result = mol_theme_work_chapters_data($workId, $page, 100);
        if (is_array($result['error'] ?? null)) {
            return $result;
        }
        foreach ((array) ($result['data'] ?? array()) as $chapter) {
            if (is_array($chapter)) {
                $chapters[] = $chapter;
            }
        }
        $totalPages = max(0, (int) ($result['meta']['total_pages'] ?? 0));
        $page++;
    } while ($page <= $totalPages);

    return array(
        'data' => $chapters,
        'meta' => array('total' => count($chapters)),
        'error' => null,
        'status' => 200,
    );
}

/**
 * Build the complete server-rendered context for a public reader route.
 *
 * @return array<string, mixed>
 */
function mol_theme_reader_context(int $workId, string $chapterSlug): array
{
    $workResult = mol_theme_work_data($workId);
    if (is_array($workResult['error'] ?? null)) {
        return $workResult;
    }

    $chaptersResult = mol_theme_all_work_chapters_data($workId);
    if (is_array($chaptersResult['error'] ?? null)) {
        return $chaptersResult;
    }
    $chapters = array_values(array_filter(
        (array) ($chaptersResult['data'] ?? array()),
        static fn (mixed $chapter): bool => is_array($chapter)
    ));
    $currentIndex = null;
    foreach ($chapters as $index => $chapter) {
        if ($chapterSlug === (string) ($chapter['slug'] ?? '')) {
            $currentIndex = $index;
            break;
        }
    }
    if (null === $currentIndex) {
        return array(
            'data' => array(),
            'meta' => array(),
            'error' => array(
                'code' => 'mol_not_found',
                'message' => 'الفصل المطلوب غير موجود أو غير منشور.',
            ),
            'status' => 404,
        );
    }

    $chapter = $chapters[$currentIndex];
    $chapterId = (int) ($chapter['id'] ?? 0);
    $pagesResult = mol_theme_chapter_pages_data($chapterId);
    $elementsResult = mol_theme_chapter_elements_data($chapterId, 'ar');
    $contributorsResult = mol_theme_chapter_contributors_data($chapterId);
    foreach (array($pagesResult, $elementsResult, $contributorsResult) as $result) {
        if (is_array($result['error'] ?? null)) {
            return $result;
        }
    }

    $work = is_array($workResult['data'] ?? null) ? $workResult['data'] : array();
    $progress = null;
    $userId = get_current_user_id();
    if ($userId > 0 && function_exists('mol_get_reading_progress')) {
        $progress = mol_get_reading_progress($userId, $chapterId);
    }

    $readerMode = is_array($progress) ? (string) ($progress['reader_mode'] ?? '') : '';
    if (! in_array($readerMode, array('webtoon', 'paged'), true)) {
        $readerMode = (string) ($chapter['reader_mode_override'] ?? '');
    }
    if (! in_array($readerMode, array('webtoon', 'paged'), true)) {
        $readerMode = (string) ($work['default_reader_mode'] ?? 'webtoon');
    }
    if (! in_array($readerMode, array('webtoon', 'paged'), true)) {
        $readerMode = 'webtoon';
    }

    $direction = (string) ($chapter['direction_override'] ?? '');
    if (! in_array($direction, array('rtl', 'ltr'), true)) {
        $direction = (string) ($work['reading_direction'] ?? 'rtl');
    }
    if (! in_array($direction, array('rtl', 'ltr'), true)) {
        $direction = 'rtl';
    }

    return array(
        'data' => array(
            'work' => $work,
            'chapter' => $chapter,
            'chapters' => $chapters,
            'pages' => array_values((array) ($pagesResult['data'] ?? array())),
            'elements' => array_values((array) ($elementsResult['data'] ?? array())),
            'contributors' => array_values((array) ($contributorsResult['data'] ?? array())),
            'element_count' => max(0, (int) ($elementsResult['meta']['element_count'] ?? 0)),
            'previous_chapter' => $currentIndex > 0 ? $chapters[$currentIndex - 1] : null,
            'next_chapter' => $currentIndex + 1 < count($chapters) ? $chapters[$currentIndex + 1] : null,
            'reader_mode' => $readerMode,
            'direction' => $direction,
            'progress' => $progress,
        ),
        'meta' => array(),
        'error' => null,
        'status' => 200,
    );
}

/**
 * @return array{
 *   work_types: list<array{slug: string, name: string}>,
 *   genres: list<array{slug: string, name: string}>,
 *   source_languages: list<array{slug: string, name: string}>,
 *   work_statuses: list<array{slug: string, name: string}>,
 *   most_read_available: bool
 * }
 */
function mol_theme_filter_options(): array
{
    $capabilities = mol_theme_rest_get('/mol/v1/capabilities');
    $capabilityData = is_array($capabilities['data']) ? $capabilities['data'] : array();

    return array(
        'work_types' => mol_theme_term_options('mol_work_type'),
        'genres' => mol_theme_term_options('mol_genre'),
        'source_languages' => mol_theme_term_options('mol_source_language'),
        'work_statuses' => mol_theme_term_options('mol_work_status'),
        'most_read_available' => true === ($capabilityData['most_read_available'] ?? false),
    );
}

/** @return list<array{slug: string, name: string}> */
function mol_theme_term_options(string $taxonomy): array
{
    if (! taxonomy_exists($taxonomy)) {
        return array();
    }
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'ASC',
    ));
    if (is_wp_error($terms) || ! is_array($terms)) {
        return array();
    }

    return array_values(array_map(
        static fn (WP_Term $term): array => array(
            'slug' => (string) $term->slug,
            'name' => (string) $term->name,
        ),
        array_filter($terms, static fn (mixed $term): bool => $term instanceof WP_Term)
    ));
}
