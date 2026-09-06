<?php
declare(strict_types=1);

namespace MOL\Security;

use MOL\Domain\Fault;

final class ChapterVisibilityPolicy
{
	public static function require(?array $chapter, ?\WP_REST_Request $request = null): array
	{
		if (!$chapter || (!$chapter['is_published'] && !($request && Access::authenticated($request) && (current_user_can('mol_use_editor') || current_user_can('mol_manage_content'))))) {
			throw Fault::missing();
		}
		return $chapter;
	}
}
