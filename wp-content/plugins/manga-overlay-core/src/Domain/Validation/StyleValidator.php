<?php

declare(strict_types=1);

namespace MOL\Domain\Validation;

final class StyleValidator
{
    /** @var list<string> */
    private const FIELDS = array(
        'fontId',
        'fontSizeUnit',
        'fontWeight',
        'lineHeight',
        'textAlign',
        'color',
        'backgroundColor',
        'backgroundOpacity',
        'borderColor',
        'borderWidthUnit',
        'borderRadiusUnit',
        'paddingUnit',
        'shape',
        'strokeColor',
        'strokeWidthUnit',
        'shadow',
        'tail',
        'burst',
        'scaleX',
        'scaleY',
        'autoFit',
        'minFontSizeUnit',
    );

    /** @param array<string, mixed> $style */
    public static function validate(string $elementType, array $style): void
    {
        AllowedValues::elementType($elementType);
        if ([] !== $style && array_is_list($style)) {
            throw new ValidationException('style', 'style must be an object.');
        }

        foreach ($style as $field => $value) {
            if (! in_array($field, self::FIELDS, true)) {
                throw new ValidationException($field, sprintf('Unknown style field: %s.', $field));
            }

            self::validateField($field, $value);
        }

        self::validateElementRestrictions($elementType, $style);
    }

    private static function validateField(string $field, mixed $value): void
    {
        switch ($field) {
            case 'fontId':
                self::stringOneOf($field, $value, array(
                    'noto-sans-arabic',
                    'cairo',
                    'tajawal',
                    'noto-kufi-arabic',
                    'sfx-display-1',
                ));
                break;
            case 'fontSizeUnit':
                self::integerRange($field, $value, 1000, 200000);
                break;
            case 'fontWeight':
                if (! is_int($value) || ! in_array($value, array(400, 500, 600, 700, 800, 900), true)) {
                    throw new ValidationException($field, 'fontWeight contains an unsupported value.');
                }
                break;
            case 'lineHeight':
                self::numberRange($field, $value, 1, 2.5);
                break;
            case 'textAlign':
                self::stringOneOf($field, $value, array('start', 'center', 'end'));
                break;
            case 'color':
            case 'backgroundColor':
            case 'borderColor':
            case 'strokeColor':
                self::color($field, $value);
                break;
            case 'backgroundOpacity':
                self::numberRange($field, $value, 0, 1);
                break;
            case 'borderWidthUnit':
            case 'strokeWidthUnit':
                self::integerRange($field, $value, 0, 50000);
                break;
            case 'borderRadiusUnit':
                self::integerRange($field, $value, 0, 500000);
                break;
            case 'paddingUnit':
                self::integerRange($field, $value, 0, 100000);
                break;
            case 'shape':
                self::stringOneOf($field, $value, array(
                    'ellipse',
                    'rounded_rect',
                    'rect',
                    'cloud',
                    'none',
                    'burst',
                    'impact',
                ));
                break;
            case 'shadow':
                self::shadow($value);
                break;
            case 'tail':
                self::tail($value);
                break;
            case 'burst':
                self::burst($value);
                break;
            case 'scaleX':
            case 'scaleY':
                self::numberRange($field, $value, 0.5, 2);
                break;
            case 'autoFit':
                if (! is_bool($value)) {
                    throw new ValidationException($field, 'autoFit must be a boolean.');
                }
                break;
            case 'minFontSizeUnit':
                self::integerRange($field, $value, 1000, 100000);
                break;
        }
    }

    /** @param array<string, mixed> $style */
    private static function validateElementRestrictions(string $elementType, array $style): void
    {
        $allowedShapes = array(
            'bubble' => array('ellipse', 'rounded_rect', 'rect', 'cloud'),
            'narration' => array('rect', 'rounded_rect'),
            'free_text' => array('none', 'rect', 'rounded_rect'),
            'sfx' => array('none', 'burst', 'impact'),
        );
        $forbiddenFields = array(
            'bubble' => array('burst', 'scaleX', 'scaleY'),
            'narration' => array('tail', 'burst', 'scaleX', 'scaleY'),
            'free_text' => array('tail', 'burst', 'scaleX', 'scaleY'),
            'sfx' => array('tail'),
        );

        if (array_key_exists('shape', $style) && ! in_array($style['shape'], $allowedShapes[$elementType], true)) {
            throw new ValidationException('shape', sprintf('shape is not valid for %s.', $elementType));
        }

        foreach ($forbiddenFields[$elementType] as $field) {
            if (array_key_exists($field, $style)) {
                throw new ValidationException($field, sprintf('%s is not valid for %s.', $field, $elementType));
            }
        }
    }

    private static function shadow(mixed $value): void
    {
        if (null === $value) {
            return;
        }
        self::objectValue('shadow', $value, array('xUnit', 'yUnit', 'blurUnit', 'color', 'opacity'));
        if (array_key_exists('xUnit', $value)) {
            self::integerRange('shadow.xUnit', $value['xUnit'], -50000, 50000);
        }
        if (array_key_exists('yUnit', $value)) {
            self::integerRange('shadow.yUnit', $value['yUnit'], -50000, 50000);
        }
        if (array_key_exists('blurUnit', $value)) {
            self::integerRange('shadow.blurUnit', $value['blurUnit'], 0, 50000);
        }
        if (array_key_exists('color', $value)) {
            self::color('shadow.color', $value['color']);
        }
        if (array_key_exists('opacity', $value)) {
            self::numberRange('shadow.opacity', $value['opacity'], 0, 1);
        }
    }

    private static function tail(mixed $value): void
    {
        if (null === $value) {
            return;
        }
        self::objectValue('tail', $value, array('enabled', 'angleMdeg', 'lengthUnit', 'widthUnit'));
        if (array_key_exists('enabled', $value) && ! is_bool($value['enabled'])) {
            throw new ValidationException('tail.enabled', 'tail.enabled must be a boolean.');
        }
        if (array_key_exists('angleMdeg', $value)) {
            self::integerRange('tail.angleMdeg', $value['angleMdeg'], -360000, 360000);
        }
        if (array_key_exists('lengthUnit', $value)) {
            self::integerRange('tail.lengthUnit', $value['lengthUnit'], 0, 300000);
        }
        if (array_key_exists('widthUnit', $value)) {
            self::integerRange('tail.widthUnit', $value['widthUnit'], 0, 200000);
        }
    }

    private static function burst(mixed $value): void
    {
        if (null === $value) {
            return;
        }
        self::objectValue('burst', $value, array('points', 'depth'));
        if (array_key_exists('points', $value)
            && (! is_int($value['points']) || ! in_array($value['points'], array(8, 12, 16, 24), true))
        ) {
            throw new ValidationException('burst.points', 'burst.points contains an unsupported value.');
        }
        if (array_key_exists('depth', $value)) {
            self::numberRange('burst.depth', $value['depth'], 0, 1);
        }
    }

    /** @param list<string> $allowedFields */
    private static function objectValue(string $field, mixed $value, array $allowedFields): void
    {
        if (! is_array($value) || ([] !== $value && array_is_list($value))) {
            throw new ValidationException($field, sprintf('%s must be an object.', $field));
        }

        foreach (array_keys($value) as $nestedField) {
            if (! in_array($nestedField, $allowedFields, true)) {
                throw new ValidationException(
                    $field . '.' . $nestedField,
                    sprintf('Unknown %s field: %s.', $field, $nestedField)
                );
            }
        }
    }

    /** @param list<string> $allowed */
    private static function stringOneOf(string $field, mixed $value, array $allowed): void
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new ValidationException($field, sprintf('%s contains an unsupported value.', $field));
        }
    }

    private static function integerRange(string $field, mixed $value, int $minimum, int $maximum): void
    {
        if (! is_int($value) || $value < $minimum || $value > $maximum) {
            throw new ValidationException($field, sprintf('%s is outside the allowed integer range.', $field));
        }
    }

    private static function numberRange(string $field, mixed $value, float $minimum, float $maximum): void
    {
        if ((! is_int($value) && ! is_float($value))
            || ! is_finite((float) $value)
            || $value < $minimum
            || $value > $maximum
        ) {
            throw new ValidationException($field, sprintf('%s is outside the allowed numeric range.', $field));
        }
    }

    private static function color(string $field, mixed $value): void
    {
        if (! is_string($value) || 1 !== preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            throw new ValidationException($field, sprintf('%s must be a six-digit hex color.', $field));
        }
    }
}
