<?php

declare(strict_types=1);

namespace MOL\Repositories;

final class ContributionRepository extends AbstractRepository
{
    public function upsert(
        int $elementId,
        int $userId,
        int $workId,
        int $chapterId,
        bool $createdElement,
        ?string $contributedAt = null
    ): void {
        $this->positiveId($elementId, 'element_id');
        $this->positiveId($userId, 'user_id');
        $this->positiveId($workId, 'work_id');
        $this->positiveId($chapterId, 'chapter_id');
        $timestamp = $contributedAt ?? $this->utcNow();
        $query = $this->prepare(
            "INSERT INTO {$this->tables->contributions}
                (element_id, user_id, work_id, chapter_id, created_element, first_contributed_at, last_contributed_at)
             VALUES (%d, %d, %d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
                work_id = VALUES(work_id),
                chapter_id = VALUES(chapter_id),
                created_element = GREATEST(created_element, VALUES(created_element)),
                last_contributed_at = VALUES(last_contributed_at)",
            $elementId,
            $userId,
            $workId,
            $chapterId,
            $createdElement ? 1 : 0,
            $timestamp,
            $timestamp
        );
        $this->execute($query, 'Upserting an element contribution');
    }

    /** @return list<array<string, mixed>> */
    public function forChapter(int $chapterId): array
    {
        $this->positiveId($chapterId, 'chapter_id');
        $rows = $this->fetchAll($this->prepare(
            "SELECT * FROM {$this->tables->contributions} WHERE chapter_id = %d ORDER BY id",
            $chapterId
        ));

        return array_map(
            static function (array $row): array {
                foreach (array('id', 'element_id', 'user_id', 'work_id', 'chapter_id') as $field) {
                    $row[$field] = (int) $row[$field];
                }
                $row['created_element'] = 1 === (int) $row['created_element'];

                return $row;
            },
            $rows
        );
    }
}
