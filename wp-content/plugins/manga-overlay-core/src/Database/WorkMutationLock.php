<?php
declare(strict_types=1);

namespace MOL\Database;

/** Coordinates chapter creation with WordPress's non-transactional parent deletion. */
final class WorkMutationLock
{
	private static array $held = [];

	public static function acquire(int $id): bool
	{
		global $wpdb;
		$key = 'mol_work_' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix . ':' . $id), 0, 48);
		if (isset(self::$held[$key])) {
			++self::$held[$key];
			return true;
		}
		if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $key)) !== '1') {
			return false;
		}
		self::$held[$key] = 1;
		return true;
	}

	public static function release(int $id): void
	{
		global $wpdb;
		$key = 'mol_work_' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix . ':' . $id), 0, 48);
		if (isset(self::$held[$key]) && --self::$held[$key] === 0) {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
			unset(self::$held[$key]);
		}
	}

	public static function cleanup(): void
	{
		global $wpdb;
		foreach (array_keys(self::$held) as $key) {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
		}
		self::$held = [];
	}

	public static function run(int $id, callable $operation): mixed
	{
		if (!self::acquire($id)) {
			throw new \RuntimeException('Could not lock work mutation.');
		}
		try {
			// Another request may have deleted/trashed the cached parent while we waited.
			clean_post_cache($id);
			return $operation();
		} finally {
			self::release($id);
		}
	}
}
