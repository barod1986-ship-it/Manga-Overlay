<?php

declare(strict_types=1);

namespace MOL\Content;

final class WorkMeta
{
    public const ALT_TITLES = '_mol_alt_titles';
    public const DEFAULT_READER_MODE = '_mol_default_reader_mode';
    public const READING_DIRECTION = '_mol_reading_direction';

    public const DEFAULT_READER_MODE_VALUE = 'webtoon';
    public const DEFAULT_READING_DIRECTION_VALUE = 'rtl';

    /** @var list<string> */
    private const READER_MODES = array('webtoon', 'paged');

    /** @var list<string> */
    private const READING_DIRECTIONS = array('rtl', 'ltr');

    /**
     * @param mixed $value
     * @return list<string>
     */
    public static function sanitizeAltTitles(mixed $value): array
    {
        if (! is_array($value)) {
            return array();
        }

        $titles = array();
        foreach ($value as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $title = sanitize_text_field((string) $candidate);
            if ('' === $title || in_array($title, $titles, true)) {
                continue;
            }

            $titles[] = $title;
        }

        return $titles;
    }

    public static function sanitizeReaderMode(mixed $value): string
    {
        $value = is_string($value) ? sanitize_key($value) : '';

        return in_array($value, self::READER_MODES, true)
            ? $value
            : self::DEFAULT_READER_MODE_VALUE;
    }

    public static function sanitizeReadingDirection(mixed $value): string
    {
        $value = is_string($value) ? sanitize_key($value) : '';

        return in_array($value, self::READING_DIRECTIONS, true)
            ? $value
            : self::DEFAULT_READING_DIRECTION_VALUE;
    }

    /**
     * Registered meta is publicly readable through Core REST. This callback
     * governs edit/add/delete capability checks for the protected keys.
     */
    public static function authorizeMutation(): bool
    {
        return current_user_can('mol_manage_content');
    }

    /** @return list<string> */
    public static function readerModes(): array
    {
        return self::READER_MODES;
    }

    /** @return list<string> */
    public static function readingDirections(): array
    {
        return self::READING_DIRECTIONS;
    }
}
