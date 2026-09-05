<?php
declare(strict_types=1);

namespace MOL\Domain;

final class Geometry
{
	public const UNIT = 1000000;

	/** Validate persisted geometry. Unlike pointer clamping, server validation rejects invalid input. */
	public static function validate(array $input): array
	{
		$limits = [
			'x_unit' => [0, self::UNIT], 'y_unit' => [0, self::UNIT],
			'w_unit' => [1, self::UNIT], 'h_unit' => [1, self::UNIT],
			'rotation_mdeg' => [-360000, 360000], 'z_index' => [-1000, 10000],
		];
		if (array_diff(array_keys($input), array_keys($limits))) {
			throw new \InvalidArgumentException('Unknown geometry field.');
		}
		$geometry = $input + ['rotation_mdeg' => 0, 'z_index' => 0];
		foreach ($limits as $key => [$minimum, $maximum]) {
			if (!isset($geometry[$key]) || !is_int($geometry[$key]) || $geometry[$key] < $minimum || $geometry[$key] > $maximum) {
				throw new \InvalidArgumentException('Invalid normalized geometry.');
			}
		}
		if ($geometry['x_unit'] + $geometry['w_unit'] > self::UNIT || $geometry['y_unit'] + $geometry['h_unit'] > self::UNIT) {
			throw new \InvalidArgumentException('Element box must be inside the image.');
		}
		return $geometry;
	}
}
