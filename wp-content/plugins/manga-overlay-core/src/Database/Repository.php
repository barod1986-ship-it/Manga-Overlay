<?php
declare(strict_types=1);

namespace MOL\Database;

abstract class Repository
{
	protected readonly Tables $tables;

	public function __construct(protected readonly \wpdb $db)
	{
		$this->tables = new Tables($db);
	}

	protected function rows(string $sql): array
	{
		$rows = $this->db->get_results($sql, ARRAY_A);
		$this->checked($rows);
		return $rows ?? [];
	}

	protected function checked(mixed $result): mixed
	{
		if ($result === false || $this->db->last_error !== '') {
			throw new \RuntimeException('Database operation failed.');
		}
		return $result;
	}
}
