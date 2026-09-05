<?php

declare(strict_types=1);

namespace MOL\Domain;

use MOL\Domain\Validation\AllowedValues;
use MOL\Domain\Validation\StyleValidator;

final class ElementStyles
{
    /** @return array<string, mixed> */
    public static function base(string $elementType): array
    {
        AllowedValues::elementType($elementType);

        return match ($elementType) {
            'bubble' => array(
                'fontId' => 'cairo',
                'fontSizeUnit' => 26_000,
                'fontWeight' => 700,
                'lineHeight' => 1.35,
                'textAlign' => 'center',
                'color' => '#111111',
                'backgroundColor' => '#FFFFFF',
                'backgroundOpacity' => 0.96,
                'borderColor' => '#111111',
                'borderWidthUnit' => 1_800,
                'borderRadiusUnit' => 50_000,
                'paddingUnit' => 9_000,
                'shape' => 'ellipse',
                'shadow' => null,
                'tail' => array(
                    'enabled' => true,
                    'angleMdeg' => 25_000,
                    'lengthUnit' => 80_000,
                    'widthUnit' => 55_000,
                ),
                'autoFit' => true,
                'minFontSizeUnit' => 16_000,
            ),
            'narration' => array(
                'fontId' => 'noto-sans-arabic',
                'fontSizeUnit' => 24_000,
                'fontWeight' => 600,
                'lineHeight' => 1.4,
                'textAlign' => 'center',
                'color' => '#111111',
                'backgroundColor' => '#FFFFFF',
                'backgroundOpacity' => 0.94,
                'borderColor' => '#111111',
                'borderWidthUnit' => 1_500,
                'borderRadiusUnit' => 18_000,
                'paddingUnit' => 10_000,
                'shape' => 'rounded_rect',
                'shadow' => null,
                'autoFit' => true,
                'minFontSizeUnit' => 15_000,
            ),
            'free_text' => array(
                'fontId' => 'cairo',
                'fontSizeUnit' => 26_000,
                'fontWeight' => 700,
                'lineHeight' => 1.3,
                'textAlign' => 'center',
                'color' => '#111111',
                'backgroundColor' => '#FFFFFF',
                'backgroundOpacity' => 0,
                'borderColor' => '#111111',
                'borderWidthUnit' => 0,
                'borderRadiusUnit' => 0,
                'paddingUnit' => 0,
                'shape' => 'none',
                'shadow' => null,
                'autoFit' => false,
            ),
            'sfx' => array(
                'fontId' => 'sfx-display-1',
                'fontSizeUnit' => 52_000,
                'fontWeight' => 900,
                'lineHeight' => 1.1,
                'textAlign' => 'center',
                'color' => '#FFFFFF',
                'backgroundColor' => '#B5231C',
                'backgroundOpacity' => 0,
                'borderColor' => '#111111',
                'borderWidthUnit' => 0,
                'borderRadiusUnit' => 0,
                'paddingUnit' => 0,
                'shape' => 'none',
                'strokeColor' => '#111111',
                'strokeWidthUnit' => 3_500,
                'shadow' => null,
                'burst' => null,
                'scaleX' => 1,
                'scaleY' => 1,
                'autoFit' => false,
            ),
        };
    }

    /**
     * @param array<string, mixed> ...$layers
     * @return array<string, mixed>
     */
    public static function resolve(string $elementType, array ...$layers): array
    {
        $resolved = self::base($elementType);
        foreach ($layers as $layer) {
            StyleValidator::validate($elementType, $layer);
            $resolved = self::merge($resolved, $layer);
        }
        $resolved['shadow'] = self::resolvedOptionalObject(
            $resolved['shadow'] ?? null,
            array('xUnit' => 0, 'yUnit' => 0, 'blurUnit' => 0, 'color' => '#000000', 'opacity' => 0.75)
        );
        if ('sfx' === $elementType) {
            $resolved['burst'] = self::resolvedOptionalObject(
                $resolved['burst'] ?? null,
                array('points' => 12, 'depth' => 0.35)
            );
        }
        StyleValidator::validate($elementType, $resolved);

        return $resolved;
    }

    /**
     * Merge a partial style without discarding sibling defaults inside tail,
     * shadow, or burst. An explicit null still disables the nested style.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $field => $value) {
            if (is_array($value)
                && ! array_is_list($value)
                && isset($base[$field])
                && is_array($base[$field])
                && ! array_is_list($base[$field])
            ) {
                /** @var array<string, mixed> $nestedBase */
                $nestedBase = $base[$field];
                /** @var array<string, mixed> $nestedOverride */
                $nestedOverride = $value;
                $base[$field] = self::merge($nestedBase, $nestedOverride);
                continue;
            }

            $base[$field] = $value;
        }

        return $base;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>|null
     */
    private static function resolvedOptionalObject(mixed $value, array $defaults): ?array
    {
        if (! is_array($value) || array() === $value) {
            return null;
        }

        return self::merge($defaults, $value);
    }
}
