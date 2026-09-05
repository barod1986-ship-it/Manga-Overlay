<?php

declare(strict_types=1);

namespace MOL\Domain\Policy;

use MOL\REST\ApiException;

final class ChapterVisibilityPolicy
{
    /**
     * Assert that the current REST caller can read a chapter resource.
     *
     * @param array<string, mixed> $chapter
     * @return bool True for public/cacheable responses; false for a private editor response.
     */
    public function assertVisible(array $chapter, bool $workIsPublished = true): bool
    {
        if ($workIsPublished && ! empty($chapter['is_published'])) {
            return true;
        }

        if (get_current_user_id() > 0
            && (current_user_can('mol_use_editor') || current_user_can('mol_manage_content'))
        ) {
            return false;
        }

        throw ApiException::notFound();
    }

    public function userCanEdit(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }

        return user_can($userId, 'mol_use_editor') || user_can($userId, 'mol_manage_content');
    }
}
