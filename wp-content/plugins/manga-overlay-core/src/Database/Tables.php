<?php
declare(strict_types=1);

namespace MOL\Database;

final class Tables
{
	public const NAMES = ['chapters', 'pages', 'elements', 'element_locks', 'contributions', 'reports', 'reading_progress', 'style_presets', 'idempotency_keys'];

	public function __construct(private readonly \wpdb $db)
	{
		if (!preg_match('/^[a-zA-Z0-9_]+$/D', $db->prefix)) {
			throw new \InvalidArgumentException('Invalid WordPress table prefix.');
		}
	}

	public function name(string $name): string
	{
		if (!in_array($name, self::NAMES, true)) {
			throw new \InvalidArgumentException('Unknown MOL table.');
		}
		return $this->db->prefix . 'mol_' . $name;
	}
}
