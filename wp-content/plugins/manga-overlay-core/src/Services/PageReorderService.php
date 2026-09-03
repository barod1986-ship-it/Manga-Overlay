<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\TransactionManager;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\PageRepository;
use MOL\REST\ApiException;

final class PageReorderService
{
    public function __construct(
        private readonly ChapterRepository $chapters,
        private readonly PageRepository $pages,
        private readonly TransactionManager $transactions
    ) {
    }

    /** @param list<int> $orderedPageIds @return list<array<string, mixed>> */
    public function reorder(int $chapterId, array $orderedPageIds): array
    {
        $this->transactions->run(function () use ($chapterId, $orderedPageIds): void {
            if (null === $this->chapters->lockForUpdate($chapterId)) {
                throw ApiException::notFound('Chapter not found.');
            }
            $current = $this->pages->lockForChapter($chapterId);
            $currentIds = array_column($current, 'id');
            $expected = $currentIds;
            $submitted = $orderedPageIds;
            sort($expected, SORT_NUMERIC);
            sort($submitted, SORT_NUMERIC);
            if ($expected !== $submitted || count($orderedPageIds) !== count(array_unique($orderedPageIds))) {
                throw $this->invalidOrder();
            }

            $maxIndex = max(array_column($current, 'page_index'));
            $offset = $maxIndex + count($current) + 2;
            if ($maxIndex + $offset > 4294967295) {
                throw $this->invalidOrder();
            }

            $this->pages->moveToTemporaryRange($chapterId, $offset);
            $this->pages->assignFinalOrder($chapterId, $orderedPageIds);
        });

        return $this->pages->forChapter($chapterId);
    }

    private function invalidOrder(): ApiException
    {
        return new ApiException(
            'mol_invalid_reorder',
            'page_ids must be a complete permutation of the chapter pages.',
            400
        );
    }
}
