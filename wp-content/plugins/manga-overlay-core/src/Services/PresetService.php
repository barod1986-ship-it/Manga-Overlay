<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\{PresetRepository, WorkMutationLock};
use MOL\Domain\{ElementInput, Fault};
use MOL\Security\Access;

final class PresetService
{
	private readonly PresetRepository $presets;
	public function __construct(\wpdb $db) { $this->presets = new PresetRepository($db); }

	public function available(?int $work_id, ?string $type): array
	{
		Access::capability('mol_use_editor');
		return $this->presets->available($work_id, $type);
	}

	public function create(mixed $body): array
	{
		Access::capability('mol_use_editor');
		$data = ElementInput::validate('PresetCreate', $body);
		$scope = ['scope' => $data['scope'], 'element_type' => $data['element_type'], 'owner_user_id' => $data['scope'] === 'personal' ? get_current_user_id() : null, 'work_id' => $data['work_id'] ?? null];
		if (($scope['scope'] === 'work') !== ($scope['work_id'] !== null)) {
			throw Fault::invalid('نمط العمل يحتاج عملًا محددًا؛ النطاقان الشخصي والعام لا يقبلان عملًا.');
		}
		$this->authorize($scope);
		$data['style'] = ElementInput::style($scope['element_type'], $data['style']);
		return $this->with_work($scope, false, fn () => $this->presets->in_scope($scope, function () use ($scope, $data): array {
			$name = self::name($data['name']);
			if ($data['is_default'] ?? false) { $this->presets->clear_default($scope); }
			$now = current_time('mysql', true);
			return $this->presets->save(null, $scope + ['name' => $name, 'style_json' => wp_json_encode($data['style'], JSON_THROW_ON_ERROR), 'is_default' => (int) ($data['is_default'] ?? false), 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now]);
		}));
	}

	public function change(int $id, mixed $body, bool $delete = false): ?array
	{
		Access::capability('mol_use_editor');
		$data = $delete ? [] : ElementInput::validate('PresetPatch', $body);
		$scope = $this->presets->find($id);
		if (!$scope) { throw Fault::missing(); }
		$this->authorize($scope);
		return $this->with_work($scope, true, fn () => $this->presets->in_scope($scope, function (array $rows) use ($id, $scope, $data, $delete): ?array {
			$current = array_column($rows, null, 'id')[$id] ?? null;
			if (!$current) { throw Fault::missing(); }
			$this->authorize($current);
			if ($delete) { $this->presets->delete($id); return null; }
			$changes = ['updated_at' => current_time('mysql', true)];
			if (isset($data['name'])) { $changes['name'] = self::name($data['name']); }
			if (isset($data['style'])) {
				$style = ElementInput::style($current['element_type'], ElementStyles::merge(json_decode($current['style_json'], false, 512, JSON_THROW_ON_ERROR), $data['style']));
				$changes['style_json'] = wp_json_encode($style, JSON_THROW_ON_ERROR);
			}
			if (isset($data['is_default'])) {
				if ($data['is_default']) { $this->presets->clear_default($scope); }
				$changes['is_default'] = (int) $data['is_default'];
			}
			return $this->presets->save($id, $changes);
		}));
	}

	private function authorize(array $preset): void
	{
		Access::capability('mol_use_editor');
		if ($preset['scope'] === 'personal' && (int) $preset['owner_user_id'] !== get_current_user_id()) {
			throw new Fault('mol_forbidden', 'هذا النمط شخصي لمستخدم آخر.', 403);
		}
		if ($preset['scope'] !== 'personal') { Access::capability($preset['scope'] === 'work' ? 'mol_manage_work_presets' : 'mol_manage_global_presets'); }
	}

	private function with_work(array $scope, bool $existing, callable $operation): mixed
	{
		if ($scope['scope'] !== 'work') { return $operation(); }
		return WorkMutationLock::run((int) $scope['work_id'], function () use ($scope, $existing, $operation): mixed {
			if (get_post_type($scope['work_id']) !== 'mol_work' || !in_array(get_post_status($scope['work_id']), ['publish', 'draft', 'private', 'pending', 'future'], true)) {
				throw $existing ? Fault::missing() : Fault::invalid('اختر عملًا موجودًا.');
			}
			return $operation();
		});
	}

	private static function name(string $name): string
	{
		$name = sanitize_text_field($name);
		if ($name === '') { throw Fault::invalid('اكتب اسمًا للنمط.'); }
		return $name;
	}
}
