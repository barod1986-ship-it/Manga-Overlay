<?php
declare(strict_types=1);

namespace MOL\Database;

/** Atomic, database-backed counters in WordPress options; not evictable transients. */
final class RateLimitRepository extends Repository
{
	public function increment(string $action, int $user_id, int $seconds): array
	{
		$expires = (intdiv(time(), $seconds) + 1) * $seconds;
		$name = 'mol_rate_' . $expires . '_' . hash('sha256', $action . ':' . $user_id);
		$affected = $this->checked($this->db->query($this->db->prepare(
			'INSERT INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + 1)',
			$this->db->options, $name, '1', 'off'
		)));
		$count = $affected === 1 ? 1 : (int) $this->db->get_var('SELECT LAST_INSERT_ID()');
		$this->checked($count);
		return ['count' => $count, 'retry_after' => max(1, $expires - time())];
	}

	public function cleanup(): void
	{
		$this->checked($this->db->query($this->db->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(SUBSTRING(option_name, 10), %s, 1) AS UNSIGNED) < %d',
			$this->db->options, $this->db->esc_like('mol_rate_') . '%', '_', time()
		)));
	}
}
