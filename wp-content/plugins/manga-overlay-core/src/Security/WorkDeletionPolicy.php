<?php
declare(strict_types=1);

namespace MOL\Security;

use MOL\Database\ChapterRepository;
use MOL\Database\WorkMutationLock;

final class WorkDeletionPolicy
{
	/** Keep the work lock until core finishes deletion/trashing, or request shutdown. */
	public static function guard(mixed $decision, \WP_Post $post): mixed
	{
		if ($post->post_type !== 'mol_work' || $decision !== null) {
			return $decision;
		}
		global $wpdb;
		if (!WorkMutationLock::acquire($post->ID)) {
			return false;
		}
		try {
			if (!(new ChapterRepository($wpdb))->has_for_work($post->ID)) {
				return null;
			}
		} catch (\RuntimeException $error) {
			// A failed lookup is never evidence that deleting the parent is safe.
		}
		WorkMutationLock::release($post->ID);
		return false;
	}
}
