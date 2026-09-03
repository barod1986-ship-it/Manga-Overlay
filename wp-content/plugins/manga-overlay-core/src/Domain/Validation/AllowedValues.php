<?php

declare(strict_types=1);

namespace MOL\Domain\Validation;

final class AllowedValues
{
    public const CHAPTER_TRANSLATION_STATUSES = array('untranslated', 'in_progress', 'completed', 'needs_review');
    public const READER_MODES = array('webtoon', 'paged');
    public const DIRECTIONS = array('rtl', 'ltr');
    public const ELEMENT_TYPES = array('bubble', 'narration', 'free_text', 'sfx');
    public const REPORT_TYPES = array('translation', 'placement', 'style', 'missing', 'other');
    public const REPORT_STATUSES = array('open', 'in_review', 'resolved', 'rejected');
    public const PRESET_SCOPES = array('personal', 'work', 'global');

    public static function chapterTranslationStatus(string $value): void
    {
        self::oneOf('translation_status', $value, self::CHAPTER_TRANSLATION_STATUSES);
    }

    public static function readerMode(?string $value, bool $allowNull = false): void
    {
        self::nullableOneOf('reader_mode', $value, self::READER_MODES, $allowNull);
    }

    public static function direction(?string $value, bool $allowNull = false): void
    {
        self::nullableOneOf('direction', $value, self::DIRECTIONS, $allowNull);
    }

    public static function elementType(string $value): void
    {
        self::oneOf('element_type', $value, self::ELEMENT_TYPES);
    }

    public static function reportType(string $value): void
    {
        self::oneOf('report_type', $value, self::REPORT_TYPES);
    }

    public static function reportStatus(string $value): void
    {
        self::oneOf('status', $value, self::REPORT_STATUSES);
    }

    public static function presetScope(string $value): void
    {
        self::oneOf('scope', $value, self::PRESET_SCOPES);
    }

    /** @param list<string> $allowed */
    private static function nullableOneOf(string $field, ?string $value, array $allowed, bool $allowNull): void
    {
        if (null === $value && $allowNull) {
            return;
        }

        if (null === $value) {
            throw new ValidationException($field, sprintf('%s cannot be null.', $field));
        }

        self::oneOf($field, $value, $allowed);
    }

    /** @param list<string> $allowed */
    private static function oneOf(string $field, string $value, array $allowed): void
    {
        if (! in_array($value, $allowed, true)) {
            throw new ValidationException($field, sprintf('%s contains an unsupported value.', $field));
        }
    }
}
