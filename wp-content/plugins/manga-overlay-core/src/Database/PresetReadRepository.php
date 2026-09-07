<?php
declare(strict_types=1);

namespace MOL\Database;

use MOL\Domain\Fault;

final class PresetReadRepository extends Repository
{
	public function resolve(string $type, int $work_id, ?int $preset_id): ?array
	{
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
		return $rows[0] ?? null;
	}
}
