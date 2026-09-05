<?php
declare(strict_types=1);

namespace MOL\Database;

final class Migrator
{
	public const VERSION = '1.1.3';

	public function __construct(private readonly \wpdb $db)
	{
	}

	/** @return array<string, string> dbDelta changes; empty on a repeated migration. */
	public function migrate(): array
	{
		$tables = new Tables($this->db);
		$collation = $this->db->get_charset_collate();
		if (!str_contains(strtolower($collation), 'utf8mb4')) {
			throw new \RuntimeException('MOL tables require utf8mb4.');
		}
		$lock = 'mol_schema_' . substr(hash('sha256', DB_NAME . ':' . $this->db->prefix), 0, 40);
		$acquired = $this->db->get_var($this->db->prepare('SELECT GET_LOCK(%s, 15)', $lock));
		if ((string) $acquired !== '1') {
			throw new \RuntimeException('Could not acquire the migration lock.');
		}
		try {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$path = dirname(__DIR__, 2) . '/database/schema.sql';
			$sql = file_get_contents($path);
			if ($sql === false) {
				throw new \RuntimeException('Missing database schema.');
			}
			$sql = str_replace(['{prefix}', '{charset_collate}'], [$this->db->prefix, $collation], $sql);
			$changes = [];
			foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
				$changes = array_merge($changes, dbDelta($statement . ';'));
				if ($this->db->last_error !== '') {
					throw new \RuntimeException('Database migration failed.');
				}
			}
			foreach (Tables::NAMES as $name) {
				$engine = $this->db->get_var($this->db->prepare(
					'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
					$tables->name($name)
				));
				if (strtoupper((string) $engine) !== 'INNODB') {
					throw new \RuntimeException('Every MOL table must use InnoDB.');
				}
			}
			update_option('mol_db_version', self::VERSION, false);
			return $changes;
		} finally {
			$this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)', $lock));
		}
	}
}
