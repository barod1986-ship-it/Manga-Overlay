<?php
declare(strict_types=1);

namespace MOL\Security;

use MOL\Database\PageRepository;

final class AttachmentDeletionPolicy
{
	public static function guard(mixed $decision, \WP_Post $post): mixed
	{
		if ($decision !== null) {
			return $decision;
		}
		global $wpdb;
		try {
			return (new PageRepository($wpdb))->has_attachment($post->ID) ? false : null;
		} catch (\RuntimeException $error) {
			return false;
		}
	}
}
