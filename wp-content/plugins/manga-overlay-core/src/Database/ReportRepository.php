<?php
declare(strict_types=1);

namespace MOL\Database;

final class ReportRepository extends Repository
{
	public function find(int $id, bool $lock = false): ?array
	{
		$rows = $this->rows($this->db->prepare('SELECT * FROM %i WHERE id = %d' . ($lock ? ' FOR UPDATE' : ''), $this->tables->name('reports'), $id));
		return isset($rows[0]) ? self::to_dto($rows[0]) : null;
	}

	public function save(?int $id, array $data): array
	{
		if ($id === null) {
			$this->checked($this->db->insert($this->tables->name('reports'), $data));
			$id = (int) $this->db->insert_id;
		} else { $this->checked($this->db->update($this->tables->name('reports'), $data, ['id' => $id])); }
		return $this->find($id) ?? throw new \RuntimeException('Report disappeared during mutation.');
	}

	public function listing(array $filters): array
	{
		$where = '1=1';
		foreach (['status' => '%s', 'chapter_id' => '%d'] as $key => $format) {
			if (isset($filters[$key])) { $where .= $this->db->prepare(' AND %i = ' . $format, $key, $filters[$key]); }
		}
		$total = (int) $this->checked($this->db->get_var($this->db->prepare('SELECT COUNT(*) FROM %i WHERE ' . $where, $this->tables->name('reports'))));
		$page = $filters['page']; $size = $filters['per_page'];
		$rows = $this->rows($this->db->prepare('SELECT * FROM %i WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', $this->tables->name('reports'), $size, ($page - 1) * $size));
		return ['data' => array_map([self::class, 'to_dto'], $rows), 'meta' => ['page' => $page, 'per_page' => $size, 'total' => $total, 'total_pages' => (int) ceil($total / $size)]];
	}

	private static function to_dto(array $row): array
	{
		foreach (['id', 'chapter_id', 'page_id', 'element_id', 'reporter_id', 'resolved_by'] as $key) { $row[$key] = $row[$key] === null ? null : (int) $row[$key]; }
		foreach (['created_at', 'resolved_at'] as $key) { $row[$key] = $row[$key] === null ? null : (new \DateTimeImmutable($row[$key], new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'); }
		return $row;
	}
}
