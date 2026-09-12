<?php
declare(strict_types=1);

namespace MOL\Database;

final class PresetRepository extends Repository
{
	public function find(int $id): ?array
	{
		return $this->rows($this->db->prepare('SELECT * FROM %i WHERE id = %d', $this->tables->name('style_presets'), $id))[0] ?? null;
	}

	public function available(?int $work_id, ?string $type): array
	{
		$sql = $this->db->prepare("SELECT p.* FROM %i p WHERE (p.scope = 'global' OR (p.scope = 'personal' AND p.owner_user_id = %d)", $this->tables->name('style_presets'), get_current_user_id());
		if ($work_id !== null) {
			$sql .= $this->db->prepare(" OR (p.scope = 'work' AND p.work_id = %d AND EXISTS (SELECT 1 FROM %i w WHERE w.ID = p.work_id AND w.post_type = 'mol_work' AND w.post_status IN ('publish','draft','private','pending','future')))", $work_id, $this->db->posts);
		}
		$sql .= ')';
		if ($type !== null) {
			$sql .= $this->db->prepare(' AND p.element_type = %s', $type);
		}
		return array_map([self::class, 'dto'], $this->rows($sql . " ORDER BY CASE p.scope WHEN 'personal' THEN 0 WHEN 'work' THEN 1 ELSE 2 END, p.is_default DESC, p.id"));
	}

	/** The advisory lock serializes even an empty scope on both supported DB engines. */
	public function in_scope(array $scope, callable $operation): mixed
	{
		$identity = [$this->db->prefix, $scope['scope'], (int) $scope['owner_user_id'], (int) $scope['work_id'], $scope['element_type']];
		$key = 'mol_preset_' . substr(hash('sha256', DB_NAME . ':' . wp_json_encode($identity)), 0, 48);
		if ((string) $this->db->get_var($this->db->prepare('SELECT GET_LOCK(%s, 30)', $key)) !== '1') {
			throw new \RuntimeException('Could not serialize preset scope.');
		}
		try {
			return (new Transaction($this->db))->run(function () use ($scope, $operation): mixed {
				$rows = $this->rows('SELECT * FROM ' . $this->db->prepare('%i', $this->tables->name('style_presets')) . ' WHERE ' . $this->condition($scope) . ' ORDER BY id FOR UPDATE');
				return $operation($rows);
			});
		} finally {
			$this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)', $key));
		}
	}

	private function condition(array $scope): string
	{
		$sql = $this->db->prepare('scope = %s AND element_type = %s', $scope['scope'], $scope['element_type']);
		return $sql . match ($scope['scope']) {
			'personal' => $this->db->prepare(' AND owner_user_id = %d', $scope['owner_user_id']),
			'work' => $this->db->prepare(' AND work_id = %d', $scope['work_id']),
			'global' => '',
		};
	}

	public function clear_default(array $scope): void
	{
		$this->checked($this->db->query($this->db->prepare('UPDATE %i SET is_default = 0 WHERE ', $this->tables->name('style_presets')) . $this->condition($scope)));
	}

	public function save(?int $id, array $data): array
	{
		if ($id === null) {
			$this->checked($this->db->insert($this->tables->name('style_presets'), $data));
			$id = (int) $this->db->insert_id;
		} else {
			$this->checked($this->db->update($this->tables->name('style_presets'), $data, ['id' => $id]));
		}
		return self::dto($this->find($id));
	}

	public function delete(int $id): void
	{
		$this->checked($this->db->delete($this->tables->name('style_presets'), ['id' => $id]));
	}

	public static function deleted_work(int $id, \WP_Post $post): void
	{
		if ($post->post_type === 'mol_work') {
			global $wpdb;
			$repository = new self($wpdb);
			$repository->checked($wpdb->delete($repository->tables->name('style_presets'), ['scope' => 'work', 'work_id' => $id]));
		}
	}

	public static function dto(array $row): array
	{
		return [
			'id' => (int) $row['id'], 'scope' => $row['scope'], 'owner_user_id' => $row['owner_user_id'] === null ? null : (int) $row['owner_user_id'],
			'work_id' => $row['work_id'] === null ? null : (int) $row['work_id'], 'name' => $row['name'], 'element_type' => $row['element_type'],
			'style' => json_decode($row['style_json'], false, 512, JSON_THROW_ON_ERROR), 'is_default' => (bool) $row['is_default'], 'created_by' => (int) $row['created_by'],
			'created_at' => str_replace(' ', 'T', $row['created_at']) . 'Z', 'updated_at' => str_replace(' ', 'T', $row['updated_at']) . 'Z',
		];
	}
}
