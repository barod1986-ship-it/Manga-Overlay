<?php
declare(strict_types=1);

namespace MOL\Database;

final class Transaction
{
	/** One transaction owner per connection. Nested service transactions are rejected. */
	private static ?\WeakMap $active = null;

	public function __construct(private readonly \wpdb $db)
	{
	}

	public function run(callable $operation): mixed
	{
		self::$active ??= new \WeakMap();
		if (isset(self::$active[$this->db])) {
			throw new \LogicException('Nested MOL transactions are not supported.');
		}
		if ($this->db->query('START TRANSACTION') === false) {
			throw new \RuntimeException('Could not start transaction.');
		}
		self::$active[$this->db] = true;
		try {
			$result = $operation();
			if ($this->db->query('COMMIT') === false) {
				throw new \RuntimeException('Could not commit transaction.');
			}
			return $result;
		} catch (\Throwable $error) {
			$this->db->query('ROLLBACK');
			throw $error;
		} finally {
			unset(self::$active[$this->db]);
		}
	}
}
