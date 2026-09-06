<?php
declare(strict_types=1);

namespace MOL\Activation;

use MOL\Content\WorkType;
use MOL\Database\Migrator;
use MOL\Security\Roles;

final class Activator
{
	public static function activate(bool $network_wide = false): void
	{
		// This MVP targets the configured single WordPress application, not network-wide migration.
		if ($network_wide) {
			wp_die(esc_html__('فعّل Manga Overlay لكل موقع على حدة؛ ترقية الشبكة ليست ضمن هذه الدفعة.', 'manga-overlay-core'));
		}
		try {
			self::upgrade();
			Roles::install();
			WorkType::register();
			\MOL\Frontend\Routes::register();
			WorkType::seed_types();
			flush_rewrite_rules(false);
		} catch (\Throwable $error) {
			wp_die(esc_html__('لم يكتمل تفعيل Manga Overlay. يلزم WordPress 7.1 أو أحدث، وقاعدة تدعم InnoDB وutf8mb4 مع صلاحية إنشاء الجداول.', 'manga-overlay-core'));
		}
	}

	public static function upgrade(): void
	{
		global $wpdb, $wp_version;
		if (version_compare((string) $wp_version, '7.1', '<')) {
			throw new \RuntimeException('WordPress 7.1 or later is required.');
		}
		(new Migrator($wpdb))->migrate();
	}
}
