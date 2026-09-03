<?php

declare(strict_types=1);

namespace MOL\REST;

final class PublicRequestValidator
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 24;
    private const MAX_PER_PAGE = 100;

    /** @var list<string> */
    private const TRANSLATION_STATUSES = array('untranslated', 'in_progress', 'completed', 'needs_review');

    /** @var list<string> */
    private const LIBRARY_SORTS = array('latest_chapter', 'latest_work', 'title_asc', 'most_read');

    /**
     * @param array<string, mixed> $parameters
     * @return array{
     *   search: string,
     *   type: string,
     *   genres: list<string>,
     *   source_lang: string,
     *   work_status: string,
     *   translation_status: string,
     *   sort: string,
     *   page: int,
     *   per_page: int
     * }
     */
    public static function library(array $parameters): array
    {
        $search = self::text($parameters['search'] ?? '', 'search', 200, true);
        $type = self::text($parameters['type'] ?? '', 'type', 255, true);
        $sourceLanguage = self::text($parameters['source_lang'] ?? '', 'source_lang', 255, true);
        $workStatus = self::text($parameters['work_status'] ?? '', 'work_status', 255, true);
        $translationStatus = self::text(
            $parameters['translation_status'] ?? '',
            'translation_status',
            24,
            true
        );
        if ('' !== $translationStatus && ! in_array($translationStatus, self::TRANSLATION_STATUSES, true)) {
            throw ApiException::invalidParams('translation_status is invalid.');
        }

        $sort = self::text($parameters['sort'] ?? 'latest_chapter', 'sort', 32);
        if (! in_array($sort, self::LIBRARY_SORTS, true)) {
            throw ApiException::invalidParams('sort is invalid.');
        }
        if ('most_read' === $sort) {
            throw ApiException::sortUnavailable(
                'The most_read sort is unavailable until a read-counter backend is enabled.'
            );
        }

        return array(
            'search' => $search,
            'type' => $type,
            'genres' => self::genres($parameters['genre'] ?? array()),
            'source_lang' => $sourceLanguage,
            'work_status' => $workStatus,
            'translation_status' => $translationStatus,
            'sort' => $sort,
            'page' => self::positiveInteger($parameters['page'] ?? self::DEFAULT_PAGE, 'page'),
            'per_page' => self::positiveInteger(
                $parameters['per_page'] ?? self::DEFAULT_PER_PAGE,
                'per_page',
                self::MAX_PER_PAGE
            ),
        );
    }

    /** @param array<string, mixed> $parameters @return array{page: int, per_page: int} */
    public static function pagination(array $parameters): array
    {
        return array(
            'page' => self::positiveInteger($parameters['page'] ?? self::DEFAULT_PAGE, 'page'),
            'per_page' => self::positiveInteger(
                $parameters['per_page'] ?? self::DEFAULT_PER_PAGE,
                'per_page',
                self::MAX_PER_PAGE
            ),
        );
    }

    public static function id(mixed $value, string $field = 'id'): int
    {
        return self::positiveInteger($value, $field);
    }

    public static function language(mixed $value): string
    {
        return self::text(null === $value ? 'ar' : $value, 'lang', 255);
    }

    public static function username(mixed $value): string
    {
        return self::text($value, 'username', 60);
    }

    /** @return list<string> */
    private static function genres(mixed $value): array
    {
        if (null === $value || '' === $value) {
            return array();
        }
        $values = is_array($value) ? $value : array($value);
        $genres = array();
        foreach ($values as $candidate) {
            $genre = self::text($candidate, 'genre', 255);
            if (! in_array($genre, $genres, true)) {
                $genres[] = $genre;
            }
        }

        return $genres;
    }

    private static function text(
        mixed $value,
        string $field,
        int $maxLength,
        bool $allowEmpty = false
    ): string {
        if (! is_scalar($value)) {
            throw ApiException::invalidParams(sprintf('%s must be a string.', $field));
        }

        $text = sanitize_text_field((string) $value);
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ((! $allowEmpty && '' === $text) || $length > $maxLength) {
            throw ApiException::invalidParams(sprintf('%s is invalid.', $field));
        }

        return $text;
    }

    private static function positiveInteger(mixed $value, string $field, ?int $maximum = null): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && 1 === preg_match('/^[0-9]+$/', $value)) {
            $integer = (int) $value;
        } else {
            throw ApiException::invalidParams(sprintf('%s must be a positive integer.', $field));
        }

        if ($integer < 1 || (null !== $maximum && $integer > $maximum)) {
            throw ApiException::invalidParams(sprintf('%s is outside the supported range.', $field));
        }

        return $integer;
    }
}
