<?php

declare(strict_types=1);

namespace MOL\Domain\Validation;

final class GeometryValidator
{
    public const MOL_UNIT = 1000000;

    /**
     * @param array{x_unit: mixed, y_unit: mixed, w_unit: mixed, h_unit: mixed, rotation_mdeg?: mixed, z_index?: mixed} $geometry
     */
    public static function validate(array $geometry): void
    {
        foreach (array('x_unit', 'y_unit', 'w_unit', 'h_unit') as $field) {
            if (! array_key_exists($field, $geometry) || ! is_int($geometry[$field])) {
                throw new ValidationException($field, sprintf('%s must be an integer.', $field));
            }
        }

        $x = $geometry['x_unit'];
        $y = $geometry['y_unit'];
        $width = $geometry['w_unit'];
        $height = $geometry['h_unit'];

        self::range('x_unit', $x, 0, self::MOL_UNIT);
        self::range('y_unit', $y, 0, self::MOL_UNIT);
        self::range('w_unit', $width, 1, self::MOL_UNIT);
        self::range('h_unit', $height, 1, self::MOL_UNIT);

        if ($x + $width > self::MOL_UNIT) {
            throw new ValidationException('w_unit', 'x_unit + w_unit exceeds the image width.');
        }
        if ($y + $height > self::MOL_UNIT) {
            throw new ValidationException('h_unit', 'y_unit + h_unit exceeds the image height.');
        }

        if (array_key_exists('rotation_mdeg', $geometry)) {
            if (! is_int($geometry['rotation_mdeg'])) {
                throw new ValidationException('rotation_mdeg', 'rotation_mdeg must be an integer.');
            }
            self::range('rotation_mdeg', $geometry['rotation_mdeg'], -360000, 360000);
        }

        if (array_key_exists('z_index', $geometry)) {
            if (! is_int($geometry['z_index'])) {
                throw new ValidationException('z_index', 'z_index must be an integer.');
            }
            self::range('z_index', $geometry['z_index'], -1000, 10000);
        }
    }

    /**
     * @return array{x_unit: int, y_unit: int, w_unit: int, h_unit: int, rotation_mdeg: int, z_index: int}
     */
    public static function clamp(
        int $x,
        int $y,
        int $width,
        int $height,
        int $rotationMdeg = 0,
        int $zIndex = 0
    ): array {
        $clampedX = self::clampInteger($x, 0, self::MOL_UNIT - 1);
        $clampedY = self::clampInteger($y, 0, self::MOL_UNIT - 1);

        return array(
            'x_unit' => $clampedX,
            'y_unit' => $clampedY,
            'w_unit' => self::clampInteger($width, 1, self::MOL_UNIT - $clampedX),
            'h_unit' => self::clampInteger($height, 1, self::MOL_UNIT - $clampedY),
            'rotation_mdeg' => self::clampInteger($rotationMdeg, -360000, 360000),
            'z_index' => self::clampInteger($zIndex, -1000, 10000),
        );
    }

    private static function range(string $field, int $value, int $minimum, int $maximum): void
    {
        if ($value < $minimum || $value > $maximum) {
            throw new ValidationException($field, sprintf('%s is outside the allowed range.', $field));
        }
    }

    private static function clampInteger(int $value, int $minimum, int $maximum): int
    {
        return min(max($value, $minimum), $maximum);
    }
}
