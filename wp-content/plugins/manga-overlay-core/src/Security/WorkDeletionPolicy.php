<?php
declare(strict_types=1);

namespace MOL\Security;

use MOL\Database\ChapterRepository;

final class WorkDeletionPolicy
{
	/** Core deletion cannot bypass the future cascading content service. */
	public static function guard(mixed $decision, \WP_Post $post): mixed
	{
		if ($post->post_type !== 'mol_work' || $decision !== null) {
			return $decision;
		}
		global $wpdb;
		try {
			return (new ChapterRepository($wpdb))->has_for_work($post->ID) ? false : null;
		} catch (\RuntimeException $error) {
			// A failed lookup is never evidence that deleting the parent is safe.
			return false;
		}
	}
}
