<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\PresetReadRepository;
use MOL\Domain\ElementInput;

final class ElementStyles
{
	private readonly PresetReadRepository $presets;

	public function __construct(\wpdb $db)
	{
		$this->presets = new PresetReadRepository($db);
	}

	public function resolve(string $type, int $work_id, ?int $preset_id, \stdClass $override): \stdClass
	{
		$base = json_decode(file_get_contents(dirname(__DIR__, 2) . '/database/base-styles.json'), false, 512, JSON_THROW_ON_ERROR)->$type;
		$preset = $this->presets->resolve($type, $work_id, $preset_id);
		if ($preset) {
			$preset = ElementInput::style($type, json_decode($preset['style_json'], false, 512, JSON_THROW_ON_ERROR));
			$base = self::merge($base, $preset);
		}
		return ElementInput::style($type, self::merge($base, $override));
	}

	/** Nested partial style updates preserve sibling properties; explicit null clears a group. */
	public static function merge(\stdClass $base, \stdClass $patch): \stdClass
	{
		$result = clone $base;
		foreach (get_object_vars($patch) as $key => $value) {
			$result->$key = $value instanceof \stdClass && ($result->$key ?? null) instanceof \stdClass ? self::merge($result->$key, $value) : $value;
		}
		return $result;
	}
}
