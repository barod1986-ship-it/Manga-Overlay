<?php

declare(strict_types=1);

namespace MOL\Repositories;

use MOL\Domain\Validation\AllowedValues;
use MOL\Domain\Validation\GeometryValidator;

final class ReadingProgressRepository extends AbstractRepository
{
    public function upsert(
        int $userId,
        int $chapterId,
        int $pageIndex,
        int $progressUnit,
        string $readerMode,
        ?string $updatedAt = null
    ): void {
        $this->positiveId($userId, 'user_id');
        $this->positiveId($chapterId, 'chapter_id');
        AllowedValues::readerMode($readerMode);
        if ($pageIndex < 0 || $progressUnit < 0 || $progressUnit > GeometryValidator::MOL_UNIT) {
            throw new \InvalidArgumentException('Reading progress is outside the allowed range.');
        }
        $query = $this->prepare(
            "INSERT INTO {$this->tables->readingProgress}
                (user_id, chapter_id, page_index, progress_unit, reader_mode, updated_at)
             VALUES (%d, %d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
                page_index = VALUES(page_index),
                progress_unit = VALUES(progress_unit),
                reader_mode = VALUES(reader_mode),
                updated_at = VALUES(updated_at)",
            $userId,
            $chapterId,
            $pageIndex,
            $progressUnit,
            $readerMode,
            $updatedAt ?? $this->utcNow()
        );
        $this->execute($query, 'Upserting reading progress');
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, int $chapterId): ?array
    {
        $this->positiveId($userId, 'user_id');
        $this->positiveId($chapterId, 'chapter_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->readingProgress} WHERE user_id = %d AND chapter_id = %d",
            $userId,
            $chapterId
        ));

        if (null === $row) {
            return null;
        }
        foreach (array('user_id', 'chapter_id', 'page_index', 'progress_unit') as $field) {
            $row[$field] = (int) $row[$field];
        }

        return $row;
    }
}
