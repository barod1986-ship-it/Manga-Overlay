<?php
declare(strict_types=1);

namespace MOL\Domain;

/** Evaluates only the frozen element contract vocabulary; never resolves external schemas. */
final class ElementInput
{
	public static function validate(string $name, mixed $input): array
	{
		static $schemas;
		$schemas ??= json_decode(file_get_contents(dirname(__DIR__, 2) . '/database/element-contracts.json'), true, 512, JSON_THROW_ON_ERROR);
		if (!isset($schemas[$name])) {
			throw new \LogicException('Unknown element contract.');
		}
		self::check($schemas[$name], $input, $schemas);
		return (array) $input;
	}

	/** Return evaluated properties at this instance location, for unevaluatedProperties. */
	private static function check(array $schema, mixed $value, array $schemas): array
	{
		$seen = [];
		if (isset($schema['$ref'])) {
			$name = str_replace('#/components/schemas/', '', $schema['$ref']);
			if (!isset($schemas[$name])) {
				throw new \LogicException('Unsupported contract reference.');
			}
			$seen = self::check($schemas[$name], $value, $schemas);
		}
		foreach ($schema['allOf'] ?? [] as $part) {
			$seen = array_merge($seen, self::check($part, $value, $schemas));
		}
		if (isset($schema['anyOf'])) {
			$matches = 0;
			foreach ($schema['anyOf'] as $part) {
				try {
					$seen = array_merge($seen, self::check($part, $value, $schemas));
					++$matches;
				} catch (Fault $error) {
					// Only successful alternatives contribute property annotations.
				}
			}
			if (!$matches) {
				throw Fault::invalid();
			}
		}
		if (isset($schema['not']) && self::matches($schema['not'], $value, $schemas)) {
			throw Fault::invalid('خاصية النمط غير متاحة لهذا النوع.');
		}
		if (isset($schema['if']) && self::matches($schema['if'], $value, $schemas)) {
			$seen = array_merge($seen, self::check($schema['if'], $value, $schemas), self::check($schema['then'] ?? [], $value, $schemas));
		}
		$numeric = (is_int($value) || is_float($value)) && is_finite((float) $value);
		$valid = !isset($schema['type']);
		foreach ((array) ($schema['type'] ?? []) as $type) {
			$valid = $valid || match ($type) {
				'object' => $value instanceof \stdClass,
				'integer' => $numeric && floor($value) == $value,
				'number' => $numeric,
				'string' => is_string($value),
				'boolean' => is_bool($value),
				'null' => $value === null,
				default => throw new \LogicException('Unsupported element contract type.'),
			};
		}
		if (!$valid || (isset($schema['enum']) && !self::enumerated($value, $schema['enum'])) || (array_key_exists('const', $schema) && !self::enumerated($value, [$schema['const']]))) {
			throw Fault::invalid();
		}
		if ($value instanceof \stdClass) {
			$properties = get_object_vars($value);
			if (count($properties) < ($schema['minProperties'] ?? 0) || array_diff($schema['required'] ?? [], array_keys($properties))) {
				throw Fault::invalid();
			}
			if (($schema['additionalProperties'] ?? true) === false && array_diff(array_keys($properties), array_keys($schema['properties'] ?? []))) {
				throw Fault::invalid('يتضمن الطلب حقولًا غير مدعومة.');
			}
			foreach ($schema['dependentRequired'] ?? [] as $key => $required) {
				if (array_key_exists($key, $properties) && array_diff($required, array_keys($properties))) {
					throw Fault::invalid();
				}
			}
			foreach ($properties as $key => $item) {
				if (isset($schema['properties'][$key])) {
					self::check($schema['properties'][$key], $item, $schemas);
					$seen[] = $key;
				}
			}
			if (($schema['unevaluatedProperties'] ?? true) === false && array_diff(array_keys($properties), $seen)) {
				throw Fault::invalid('يتضمن الطلب حقولًا غير مدعومة.');
			}
		}
		if (is_string($value) && (mb_strlen($value) < ($schema['minLength'] ?? 0) || mb_strlen($value) > ($schema['maxLength'] ?? PHP_INT_MAX) || (isset($schema['pattern']) && preg_match('~' . $schema['pattern'] . '~u', $value) !== 1))) {
			throw Fault::invalid();
		}
		if ($numeric && ($value < ($schema['minimum'] ?? -INF) || $value > ($schema['maximum'] ?? INF))) {
			throw Fault::invalid();
		}
		return $seen;
	}

	private static function matches(array $schema, mixed $value, array $schemas): bool
	{
		try {
			self::check($schema, $value, $schemas);
			return true;
		} catch (Fault $error) {
			return false;
		}
	}

	private static function enumerated(mixed $value, array $values): bool
	{
		foreach ($values as $candidate) {
			if ($value === $candidate || ((is_int($value) || is_float($value)) && (is_int($candidate) || is_float($candidate)) && $value == $candidate)) {
				return true;
			}
		}
		return false;
	}

	public static function geometry(array $data): array
	{
		$keys = ['x_unit', 'y_unit', 'w_unit', 'h_unit', 'rotation_mdeg', 'z_index'];
		$geometry = array_map('intval', array_intersect_key($data, array_flip($keys)));
		try {
			return Geometry::validate($geometry);
		} catch (\InvalidArgumentException $error) {
			throw Fault::invalid('يجب أن يبقى صندوق العنصر داخل الصورة.');
		}
	}

	public static function style(string $type, mixed $style): \stdClass
	{
		$name = match ($type) { 'bubble' => 'BubbleStyle', 'narration' => 'NarrationStyle', 'free_text' => 'FreeTextStyle', 'sfx' => 'SfxStyle' };
		self::validate($name, $style);
		return $style;
	}
}
