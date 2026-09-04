<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Domain\Validation\AllowedValues;
use MOL\Domain\Validation\GeometryValidator;
use MOL\Domain\Validation\StyleValidator;
use MOL\Domain\Validation\ValidationException;

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
     * @param mixed $payload
     * @return array{chapter_id: int, page_index: int, progress_unit: int, reader_mode: string}
     */
    public static function readingProgress(mixed $payload): array
    {
        $data = self::object(
            $payload,
            array('chapter_id', 'page_index', 'progress_unit', 'reader_mode')
        );
        if (4 !== count($data)
            || ! array_key_exists('chapter_id', $data)
            || ! array_key_exists('page_index', $data)
            || ! array_key_exists('progress_unit', $data)
            || ! array_key_exists('reader_mode', $data)
        ) {
            throw ApiException::invalidParams(
                'chapter_id, page_index, progress_unit, and reader_mode are required.'
            );
        }

        $chapterId = self::positiveInteger($data['chapter_id'], 'chapter_id');
        if (! is_int($data['page_index']) || $data['page_index'] < 0) {
            throw ApiException::invalidParams('page_index must be a non-negative integer.');
        }
        if (! is_int($data['progress_unit'])
            || $data['progress_unit'] < 0
            || $data['progress_unit'] > 1_000_000
        ) {
            throw ApiException::invalidParams('progress_unit must be between 0 and 1000000.');
        }
        if (! is_string($data['reader_mode'])
            || ! in_array($data['reader_mode'], AllowedValues::READER_MODES, true)
        ) {
            throw ApiException::invalidParams('reader_mode contains an unsupported value.');
        }

        return array(
            'chapter_id' => $chapterId,
            'page_index' => $data['page_index'],
            'progress_unit' => $data['progress_unit'],
            'reader_mode' => $data['reader_mode'],
        );
    }

    /**
     * @param mixed $payload
     * @return array{
     *   page_id: int,
     *   target_lang: string,
     *   element_type: string,
     *   x_unit: int,
     *   y_unit: int,
     *   w_unit: int,
     *   h_unit: int,
     *   rotation_mdeg: int,
     *   z_index: int,
     *   content: string,
     *   style: array<string, mixed>,
     *   preset_id: int|null
     * }
     */
    public static function elementCreate(mixed $payload): array
    {
        $allowed = array(
            'page_id',
            'target_lang',
            'element_type',
            'x_unit',
            'y_unit',
            'w_unit',
            'h_unit',
            'rotation_mdeg',
            'z_index',
            'content',
            'style',
            'preset_id',
        );
        $data = self::object($payload, $allowed);
        foreach (array('page_id', 'element_type', 'x_unit', 'y_unit', 'w_unit', 'h_unit', 'content') as $field) {
            if (! array_key_exists($field, $data)) {
                throw ApiException::invalidParams(sprintf('%s is required.', $field));
            }
        }

        $pageId = self::positiveInteger($data['page_id'], 'page_id');
        $elementType = self::elementType($data['element_type']);
        $content = self::plainText($data['content'], 'content', 10_000);
        $targetLanguage = array_key_exists('target_lang', $data)
            ? self::language($data['target_lang'])
            : 'ar';
        $style = self::style($data['style'] ?? array(), $elementType);
        $presetId = null;
        if (array_key_exists('preset_id', $data) && null !== $data['preset_id']) {
            $presetId = self::positiveInteger($data['preset_id'], 'preset_id');
        }

        $geometry = array(
            'x_unit' => $data['x_unit'],
            'y_unit' => $data['y_unit'],
            'w_unit' => $data['w_unit'],
            'h_unit' => $data['h_unit'],
            'rotation_mdeg' => $data['rotation_mdeg'] ?? 0,
            'z_index' => $data['z_index'] ?? 0,
        );
        try {
            GeometryValidator::validate($geometry);
        } catch (ValidationException $error) {
            throw ApiException::invalidParams($error->getMessage());
        }

        return array_merge($geometry, array(
            'page_id' => $pageId,
            'target_lang' => $targetLanguage,
            'element_type' => $elementType,
            'content' => $content,
            'style' => $style,
            'preset_id' => $presetId,
        ));
    }

    /** @param mixed $payload @return array<string, mixed> */
    public static function elementPatch(mixed $payload): array
    {
        $allowed = array(
            'element_type',
            'x_unit',
            'y_unit',
            'w_unit',
            'h_unit',
            'rotation_mdeg',
            'z_index',
            'content',
            'style',
        );
        $data = self::object($payload, $allowed);
        if (array() === $data) {
            throw ApiException::invalidParams('At least one mutable element field is required.');
        }

        $hasType = array_key_exists('element_type', $data);
        $hasStyle = array_key_exists('style', $data);
        if ($hasType !== $hasStyle) {
            throw ApiException::invalidParams('element_type is required only as the discriminator for a style patch.');
        }
        if ($hasType) {
            $data['element_type'] = self::elementType($data['element_type']);
            $data['style'] = self::style($data['style'], $data['element_type']);
        }

        $ranges = array(
            'x_unit' => array(0, 1_000_000),
            'y_unit' => array(0, 1_000_000),
            'w_unit' => array(1, 1_000_000),
            'h_unit' => array(1, 1_000_000),
            'rotation_mdeg' => array(-360_000, 360_000),
            'z_index' => array(-1_000, 10_000),
        );
        foreach ($ranges as $field => [$minimum, $maximum]) {
            if (array_key_exists($field, $data)) {
                $data[$field] = self::integerRange($data[$field], $field, $minimum, $maximum);
            }
        }
        if (array_key_exists('content', $data)) {
            $data['content'] = self::plainText($data['content'], 'content', 10_000);
        }

        if (2 === count($data) && $hasType && $hasStyle && array() === $data['style']) {
            throw ApiException::invalidParams('A discriminator-only or empty style patch is not a change.');
        }

        return $data;
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

    private static function integerRange(mixed $value, string $field, int $minimum, int $maximum): int
    {
        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            throw ApiException::invalidParams(sprintf('%s is outside the allowed integer range.', $field));
        }

        return $value;
    }

    private static function elementType(mixed $value): string
    {
        if (! is_string($value)) {
            throw ApiException::invalidParams('element_type must be a string.');
        }
        try {
            AllowedValues::elementType($value);
        } catch (ValidationException $error) {
            throw ApiException::invalidParams($error->getMessage());
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function style(mixed $value, string $elementType): array
    {
        if (! is_array($value) || (array() !== $value && array_is_list($value))) {
            throw ApiException::invalidParams('style must be a JSON object.');
        }
        try {
            StyleValidator::validate($elementType, $value);
        } catch (ValidationException $error) {
            throw ApiException::invalidParams($error->getMessage());
        }

        return $value;
    }

    private static function language(mixed $value): string
    {
        if (! is_string($value)) {
            throw ApiException::invalidParams('target_lang must be a string.');
        }
        $value = trim($value);
        if ('' === $value || self::length($value) > 255) {
            throw ApiException::invalidParams('target_lang has an invalid length.');
        }

        return $value;
    }

    private static function plainText(mixed $value, string $field, int $maxLength): string
    {
        if (! is_string($value) || self::length($value) > $maxLength) {
            throw ApiException::invalidParams(sprintf('%s must be a string no longer than %d characters.', $field, $maxLength));
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
