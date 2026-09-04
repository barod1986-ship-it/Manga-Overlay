<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Repositories\ReadingProgressRepository;
use MOL\REST\ApiException;

final class ReadingProgressService
{
    public function __construct(
        private readonly ReadingProgressRepository $progress,
        private readonly PublicReadService $reads
    ) {
    }

    /**
     * @param array{chapter_id: int, page_index: int, progress_unit: int, reader_mode: string} $payload
     * @return array<string, mixed>
     */
    public function save(int $userId, array $payload): array
    {
        if ($userId < 1) {
            throw ApiException::forbidden('Authentication is required.');
        }

        $chapter = $this->reads->chapterPages($payload['chapter_id']);
        $pageCount = count($chapter['pages']);
        if ((0 === $pageCount && 0 !== $payload['page_index'])
            || ($pageCount > 0 && $payload['page_index'] >= $pageCount)
        ) {
            throw ApiException::invalidParams('page_index is outside the chapter page range.');
        }

        $this->progress->upsert(
            $userId,
            $payload['chapter_id'],
            $payload['page_index'],
            $payload['progress_unit'],
            $payload['reader_mode']
        );
        $saved = $this->progress->find($userId, $payload['chapter_id']);
        if (null === $saved) {
            throw new \RuntimeException('Saved reading progress could not be loaded.');
        }

        return $saved;
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, int $chapterId): ?array
    {
        if ($userId < 1 || $chapterId < 1) {
            return null;
        }
        $this->reads->chapter($chapterId);

        return $this->progress->find($userId, $chapterId);
    }
}
