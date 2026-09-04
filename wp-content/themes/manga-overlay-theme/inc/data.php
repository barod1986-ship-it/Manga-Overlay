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

