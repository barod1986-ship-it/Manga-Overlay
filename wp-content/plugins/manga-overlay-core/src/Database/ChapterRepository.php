<?php
declare(strict_types=1);

namespace MOL\Database;

/** Internal persistence access. Public REST/PHP readers must apply chapter visibility separately. */
final class ChapterRepository
{
	private readonly Tables $tables;

	public function __construct(private readonly \wpdb $db)
	{
		$this->tables = new Tables($db);
	}

	public function has_for_work(int $work_id): bool
	{
		$exists = $this->db->get_var($this->db->prepare(
			'SELECT 1 FROM %i WHERE work_id = %d LIMIT 1', $this->tables->name('chapters'), $work_id
		));
		$this->assert_query_succeeded();
		return (bool) $exists;
	}

	public function find(int $chapter_id): ?array
	{
		$row = $this->db->get_row($this->db->prepare(
			'SELECT * FROM %i WHERE id = %d', $this->tables->name('chapters'), $chapter_id
		), ARRAY_A);
		$this->assert_query_succeeded();
		return $row ? self::to_dto($row) : null;
	}

	private function assert_query_succeeded(): void
	{
		if ($this->db->last_error !== '') {
			throw new \RuntimeException('Could not read chapter data.');
		}
	}

	public static function to_dto(array $row): array
	{
		return [
			'id' => (int) $row['id'], 'work_id' => (int) $row['work_id'],
			'chapter_label' => (string) $row['chapter_label'], 'sort_order' => (float) $row['sort_order'],
			'title' => $row['title'], 'slug' => (string) $row['slug'],
			'translation_status' => (string) $row['translation_status'],
			'source_lang_override' => $row['source_lang_override'], 'reader_mode_override' => $row['reader_mode_override'],
			'direction_override' => $row['direction_override'], 'is_published' => (bool) $row['is_published'],
			'published_at' => self::utc_date($row['published_at']),
			'created_at' => self::utc_date($row['created_at']), 'updated_at' => self::utc_date($row['updated_at']),
		];
	}

	private static function utc_date(?string $date): ?string
	{
		return $date === null ? null : (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
	}
}
