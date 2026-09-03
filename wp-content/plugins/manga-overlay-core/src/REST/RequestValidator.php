<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Domain\Validation\AllowedValues;

final class RequestValidator
{
    /**
     * @param mixed $payload
     * @return array{
     *   work_id: int,
     *   chapter_label: string,
     *   sort_order?: float|int,
     *   title?: string|null,
     *   translation_status?: string,
     *   source_lang_override?: string|null,
     *   reader_mode_override?: string|null,
     *   direction_override?: string|null,
     *   is_published?: bool
     * }
     */
    public static function chapterCreate(mixed $payload): array
    {
        $allowed = array(
            'work_id',
            'chapter_label',
            'sort_order',
            'title',
            'translation_status',
            'source_lang_override',
            'reader_mode_override',
            'direction_override',
            'is_published',
        );
        $data = self::object($payload, $allowed);
        if (! array_key_exists('work_id', $data) || ! array_key_exists('chapter_label', $data)) {
            throw ApiException::invalidParams('work_id and chapter_label are required.');
        }
        $data['work_id'] = self::positiveInteger($data['work_id'], 'work_id');

        return self::chapterFields($data, true);
    }

    /**
     * @param mixed $payload
     * @return array<string, mixed>
     */
    public static function chapterPatch(mixed $payload): array
    {
        $allowed = array(
            'chapter_label',
            'sort_order',
            'title',
            'translation_status',
            'source_lang_override',
            'reader_mode_override',
            'direction_override',
            'is_published',
        );
        $data = self::object($payload, $allowed);
        if (array() === $data) {
            throw ApiException::invalidParams('At least one chapter field is required.');
        }

        return self::chapterFields($data, false);
    }

    public static function chapterReview(mixed $payload): string
    {
        $data = self::object($payload, array('translation_status'));
        if (array('translation_status') !== array_keys($data)) {
            throw ApiException::invalidParams('translation_status is required.');
        }
        if (! is_string($data['translation_status'])
            || ! in_array($data['translation_status'], array('needs_review', 'completed'), true)
        ) {
            throw ApiException::invalidParams('translation_status must be needs_review or completed.');
        }

        return $data['translation_status'];
    }

    /** @param mixed $payload @return list<int> */
    public static function pageOrder(mixed $payload): array
    {
        $data = self::object($payload, array('page_ids'));
        if (array('page_ids') !== array_keys($data) || ! is_array($data['page_ids']) || array() === $data['page_ids']) {
            throw new ApiException(
                'mol_invalid_reorder',
                'page_ids must be a complete permutation of the chapter pages.',
                400
            );
        }

        $pageIds = array();
        foreach ($data['page_ids'] as $pageId) {
            if (! is_int($pageId) || $pageId < 1) {
                throw new ApiException(
                    'mol_invalid_reorder',
                    'page_ids must be a complete permutation of the chapter pages.',
                    400
                );
            }
            $pageIds[] = $pageId;
        }
        if (count($pageIds) !== count(array_unique($pageIds))) {
            throw new ApiException(
                'mol_invalid_reorder',
                'page_ids must be a complete permutation of the chapter pages.',
                400
            );
        }

        return $pageIds;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function chapterFields(array $data, bool $creating): array
    {
        if (array_key_exists('chapter_label', $data)) {
            $data['chapter_label'] = self::requiredText($data['chapter_label'], 'chapter_label', 64);
        } elseif ($creating) {
            throw ApiException::invalidParams('chapter_label is required.');
        }

        if (array_key_exists('sort_order', $data)) {
            if ((! is_int($data['sort_order']) && ! is_float($data['sort_order']))
                || ! is_finite((float) $data['sort_order'])
                || abs((float) $data['sort_order']) > 9999999999.9999
            ) {
                throw ApiException::invalidParams('sort_order must fit decimal(14,4).');
            }
        }

        foreach (array('title', 'source_lang_override') as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = self::nullableText($data[$field], $field, 255);
            }
        }

        if (array_key_exists('translation_status', $data)) {
            if (! is_string($data['translation_status'])
                || ! in_array($data['translation_status'], AllowedValues::CHAPTER_TRANSLATION_STATUSES, true)
            ) {
                throw ApiException::invalidParams('translation_status contains an unsupported value.');
            }
        }

        self::nullableEnum($data, 'reader_mode_override', AllowedValues::READER_MODES);
        self::nullableEnum($data, 'direction_override', AllowedValues::DIRECTIONS);

        if (array_key_exists('is_published', $data) && ! is_bool($data['is_published'])) {
            throw ApiException::invalidParams('is_published must be a boolean.');
        }

        return $data;
    }

    /**
     * @param mixed $payload
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    private static function object(mixed $payload, array $allowed): array
    {
        if (! is_array($payload)) {
            throw ApiException::invalidParams('The request body must be a JSON object.');
        }
        foreach (array_keys($payload) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw ApiException::invalidParams('The request contains an unsupported property.');
            }
        }

        return $payload;
    }

    private static function positiveInteger(mixed $value, string $field): int
    {
        if (! is_int($value) || $value < 1) {
            throw ApiException::invalidParams(sprintf('%s must be a positive integer.', $field));
        }

        return $value;
    }

    private static function requiredText(mixed $value, string $field, int $maxLength): string
    {
        if (! is_string($value)) {
            throw ApiException::invalidParams(sprintf('%s must be a string.', $field));
        }
        $value = trim(sanitize_text_field($value));
        if ('' === $value || self::length($value) > $maxLength) {
            throw ApiException::invalidParams(sprintf('%s has an invalid length.', $field));
        }

        return $value;
    }

    private static function nullableText(mixed $value, string $field, int $maxLength): ?string
    {
        if (null === $value) {
            return null;
        }
        if (! is_string($value)) {
            throw ApiException::invalidParams(sprintf('%s must be a string or null.', $field));
        }
        $value = sanitize_text_field($value);
        if (self::length($value) > $maxLength) {
            throw ApiException::invalidParams(sprintf('%s is too long.', $field));
        }

        return $value;
    }

    /** @param array<string, mixed> $data @param list<string> $allowed */
    private static function nullableEnum(array $data, string $field, array $allowed): void
    {
        if (! array_key_exists($field, $data) || null === $data[$field]) {
            return;
        }
        if (! is_string($data[$field]) || ! in_array($data[$field], $allowed, true)) {
            throw ApiException::invalidParams(sprintf('%s contains an unsupported value.', $field));
        }
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
