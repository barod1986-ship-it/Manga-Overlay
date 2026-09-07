<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\Repository;
use MOL\Domain\ElementInput;
use MOL\Domain\Fault;

final class ElementStyles extends Repository
{
	public function resolve(string $type, int $work_id, ?int $preset_id, \stdClass $override): \stdClass
	{
		$base = json_decode(file_get_contents(dirname(__DIR__, 2) . '/database/base-styles.json'), false, 512, JSON_THROW_ON_ERROR)->$type;
		$presets = $this->tables->name('style_presets');
		$scope = $this->db->prepare("((scope = 'personal' AND owner_user_id = %d) OR (scope = 'work' AND work_id = %d) OR scope = 'global')", get_current_user_id(), $work_id);
		if ($preset_id !== null) {
			$rows = $this->rows($this->db->prepare('SELECT * FROM %i WHERE id = %d AND element_type = %s AND ', $presets, $preset_id, $type) . $scope);
			if (!$rows) {
				throw Fault::invalid('النمط المختار غير متاح لهذا العنصر.');
			}
		} else {
			$rows = $this->rows($this->db->prepare('SELECT * FROM %i WHERE is_default = 1 AND element_type = %s AND ', $presets, $type) . $scope . " ORDER BY CASE scope WHEN 'personal' THEN 0 WHEN 'work' THEN 1 ELSE 2 END, id DESC LIMIT 1");
		}
		if ($rows) {
			$preset = ElementInput::style($type, json_decode($rows[0]['style_json'], false, 512, JSON_THROW_ON_ERROR));
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
