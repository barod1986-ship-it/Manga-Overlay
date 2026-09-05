<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** @return array<string, mixed> */
function mol_theme_library_query_defaults(): array
{
    return array(
        'search' => '',
        'type' => '',
        'genre' => array(),
        'source_lang' => '',
        'work_status' => '',
        'translation_status' => '',
        'sort' => 'latest_chapter',
        'page' => 1,
        'per_page' => 12,
    );
}

/**
 * Normalize public library query values before passing them to the frozen REST route.
 *
 * @param array<string, mixed> $source
 * @return array{
 *   search: string,
 *   type: string,
 *   genre: list<string>,
 *   source_lang: string,
 *   work_status: string,
 *   translation_status: string,
 *   sort: string,
 *   page: int,
 *   per_page: int
 * }
 */
function mol_theme_library_query(array $source): array
{
    $defaults = mol_theme_library_query_defaults();
    $sorts = array('latest_chapter', 'latest_work', 'title_asc', 'most_read');
    $translationStatuses = array('untranslated', 'in_progress', 'completed', 'needs_review');
    $sort = mol_theme_query_scalar($source['sort'] ?? $defaults['sort']);
    $translationStatus = sanitize_key(mol_theme_query_scalar($source['translation_status'] ?? ''));

    if (! in_array($sort, $sorts, true)) {
        $sort = (string) $defaults['sort'];
    }
    if (! in_array($translationStatus, $translationStatuses, true)) {
        $translationStatus = '';
    }

    $genres = array();
    $rawGenres = $source['genre'] ?? array();
    foreach (is_array($rawGenres) ? $rawGenres : array($rawGenres) as $rawGenre) {
        if (! is_scalar($rawGenre)) {
            continue;
        }
        $genre = sanitize_title((string) $rawGenre);
        if ('' !== $genre && ! in_array($genre, $genres, true)) {
            $genres[] = $genre;
        }
    }

    return array(
        'search' => sanitize_text_field(mol_theme_query_scalar($source['search'] ?? '')),
        'type' => sanitize_title(mol_theme_query_scalar($source['type'] ?? '')),
        'genre' => $genres,
        'source_lang' => sanitize_title(mol_theme_query_scalar($source['source_lang'] ?? '')),
        'work_status' => sanitize_title(mol_theme_query_scalar($source['work_status'] ?? '')),
        'translation_status' => $translationStatus,
        'sort' => $sort,
        'page' => mol_theme_query_positive_integer($source['library_page'] ?? $source['page'] ?? 1, 1, 100000),
        'per_page' => mol_theme_query_positive_integer($source['per_page'] ?? 12, 12, 100),
    );
}

/** @param array<string, mixed> $query @return array<string, mixed> */
function mol_theme_compact_library_query(array $query, bool $includePage = true): array
{
    $normalized = mol_theme_library_query($query);
    $compact = array_filter(
        $normalized,
        static fn (mixed $value): bool => ! ('' === $value || array() === $value)
    );

    if (! $includePage || 1 === $normalized['page']) {
        unset($compact['page']);
    }

    return $compact;
}

/** @return array<string, mixed> */
function mol_theme_current_library_query(): array
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filters.
    $source = isset($_GET) && is_array($_GET) ? wp_unslash($_GET) : array();

    return mol_theme_library_query($source);
}

function mol_theme_query_scalar(mixed $value): string
{
    return is_scalar($value) ? trim((string) $value) : '';
}

function mol_theme_query_positive_integer(mixed $value, int $default, int $maximum): int
{
    if (is_int($value)) {
        $integer = $value;
    } elseif (is_string($value) && 1 === preg_match('/^[0-9]+$/', $value)) {
        $integer = (int) $value;
    } else {
        return $default;
    }

    return max(1, min($maximum, $integer));
}
