<?php
declare(strict_types=1);

namespace MOL\Domain;

/** Only the simple, closed content request schemas exported from the frozen contract. */
final class ContentInput
{
	public static function validate(string $name, mixed $input): array
	{
		static $schemas;
		$schemas ??= json_decode(file_get_contents(dirname(__DIR__, 2) . '/database/request-contracts.json'), true, 512, JSON_THROW_ON_ERROR);
		if (!isset($schemas[$name])) {
			throw new \LogicException('Unsupported content request schema.');
		}
		self::check($schemas[$name], $input);
		$result = (array) $input;
		// DECIMAL(14,4) has ten integer digits. Do not silently truncate at persistence.
		if (isset($result['sort_order']) && abs($result['sort_order']) > 9999999999.9999) {
			throw Fault::invalid('قيمة ترتيب الفصل أكبر من الحد المسموح.');
		}
		foreach (['chapter_label', 'title', 'source_lang_override'] as $field) {
			if (isset($result[$field])) {
				$result[$field] = sanitize_text_field($result[$field]);
			}
		}
		if (isset($result['chapter_label']) && trim($result['chapter_label']) === '') {
			throw Fault::invalid('أدخل تسمية الفصل.');
		}
		return $result;
	}

	private static function check(array $schema, mixed $value): void
	{
		$valid = !isset($schema['type']);
		foreach ((array) ($schema['type'] ?? []) as $type) {
			$valid = $valid || match ($type) {
				'object' => $value instanceof \stdClass,
				'array' => is_array($value) && array_is_list($value),
				'integer' => is_int($value),
				'number' => (is_int($value) || is_float($value)) && is_finite((float) $value),
				'string' => is_string($value),
				'boolean' => is_bool($value),
				'null' => $value === null,
				default => throw new \LogicException('Unsupported contract type.'),
			};
		}
		if (!$valid || (isset($schema['enum']) && !in_array($value, $schema['enum'], true))) {
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
			foreach ($properties as $key => $item) {
				if (isset($schema['properties'][$key])) {
					self::check($schema['properties'][$key], $item);
				}
			}
		}
		if (is_array($value)) {
			if (count($value) < ($schema['minItems'] ?? 0) || (($schema['uniqueItems'] ?? false) && count(array_unique($value, SORT_REGULAR)) !== count($value))) {
				throw Fault::invalid();
			}
			foreach ($value as $item) {
				self::check($schema['items'], $item);
			}
		}
		if (is_string($value) && (mb_strlen($value) < ($schema['minLength'] ?? 0) || mb_strlen($value) > ($schema['maxLength'] ?? PHP_INT_MAX))) {
			throw Fault::invalid();
		}
		if ((is_int($value) || is_float($value)) && ($value < ($schema['minimum'] ?? -INF) || $value > ($schema['maximum'] ?? INF))) {
			throw Fault::invalid();
		}
	}
}
