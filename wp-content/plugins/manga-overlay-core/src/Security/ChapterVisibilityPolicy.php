<?php
declare(strict_types=1);

namespace MOL\Security;

use MOL\Domain\Fault;

final class ChapterVisibilityPolicy
{
	public static function require(?array $chapter, ?\WP_REST_Request $request = null): array
	{
		$public = $chapter && $chapter['is_published'] && get_post_type($chapter['work_id']) === 'mol_work' && get_post_status($chapter['work_id']) === 'publish';
		$editor = $request && Access::authenticated($request) && (current_user_can('mol_use_editor') || current_user_can('mol_manage_content'));
		if (!$chapter || (!$public && !$editor)) {
			throw Fault::missing();
		}
		return $chapter;
	}
}
