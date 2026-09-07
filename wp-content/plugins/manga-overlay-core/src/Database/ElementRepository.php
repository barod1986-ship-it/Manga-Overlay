<?php
declare(strict_types=1);

namespace MOL\Database;

final class ElementRepository extends Repository
{
	public function find(int $id, bool $lock = false): ?array
	{
		$rows = $this->rows($this->db->prepare('SELECT * FROM %i WHERE id = %d', $this->tables->name('elements'), $id) . ($lock ? ' FOR UPDATE' : ''));
		return isset($rows[0]) ? OverlayReadRepository::to_dto($rows[0]) : null;
	}

	public function insert(array $data): array
	{
		$this->checked($this->db->insert($this->tables->name('elements'), $data));
		return $this->find((int) $this->db->insert_id);
	}

	public function update(int $id, array $data): array
	{
		$this->checked($this->db->update($this->tables->name('elements'), $data, ['id' => $id]));
		return $this->find($id);
	}

	public function contribute(array $element, array $chapter, bool $created): void
	{
		$this->checked($this->db->query($this->db->prepare(
			'INSERT INTO %i (element_id,user_id,work_id,chapter_id,created_element,first_contributed_at,last_contributed_at) VALUES (%d,%d,%d,%d,%d,%s,%s) ON DUPLICATE KEY UPDATE last_contributed_at = VALUES(last_contributed_at)',
			$this->tables->name('contributions'), $element['id'], get_current_user_id(), $chapter['work_id'], $chapter['id'], (int) $created, current_time('mysql', true), current_time('mysql', true)
		)));
	}

	public function delete(int $id): void
	{
		$this->checked($this->db->update($this->tables->name('reports'), ['element_id' => null], ['element_id' => $id]));
		foreach (['element_locks', 'contributions'] as $table) {
			$this->checked($this->db->delete($this->tables->name($table), ['element_id' => $id]));
		}
		$this->checked($this->db->delete($this->tables->name('elements'), ['id' => $id]));
	}
}
