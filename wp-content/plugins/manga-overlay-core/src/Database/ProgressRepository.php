<?php
declare(strict_types=1);

namespace MOL\Database;

final class ProgressRepository extends Repository
{
	public function find(int $user_id, int $chapter_id): ?array
	{
		$rows = $this->rows($this->db->prepare('SELECT chapter_id, page_index, progress_unit, reader_mode, updated_at FROM %i WHERE user_id = %d AND chapter_id = %d', $this->tables->name('reading_progress'), $user_id, $chapter_id));
		return $rows ? self::to_dto($rows[0]) : null;
	}

	public function save(int $user_id, array $data): array
	{
		$this->checked($this->db->query($this->db->prepare(
			'INSERT INTO %i (user_id, chapter_id, page_index, progress_unit, reader_mode, updated_at) VALUES (%d, %d, %d, %d, %s, %s) ON DUPLICATE KEY UPDATE page_index = VALUES(page_index), progress_unit = VALUES(progress_unit), reader_mode = VALUES(reader_mode), updated_at = VALUES(updated_at)',
			$this->tables->name('reading_progress'), $user_id, $data['chapter_id'], $data['page_index'], $data['progress_unit'], $data['reader_mode'], current_time('mysql', true)
		)));
		return $this->find($user_id, $data['chapter_id']);
	}

	private static function to_dto(array $row): array
	{
		foreach (['chapter_id', 'page_index', 'progress_unit'] as $key) {
			$row[$key] = (int) $row[$key];
		}
		$row['updated_at'] = (new \DateTimeImmutable($row['updated_at'], new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
		return $row;
	}
}
