<?php

declare(strict_types=1);

namespace MOL\Repositories;

final class ContributionRepository extends AbstractRepository
{
    public function deleteForElement(int $elementId): int
    {
        $this->positiveId($elementId, 'element_id');

        return $this->execute(
            $this->prepare("DELETE FROM {$this->tables->contributions} WHERE element_id = %d", $elementId),
            'Deleting element contributions'
        );
    }

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

    /** @return list<array{user_id: int, element_count: int}> */
    public function contributorsForChapter(int $chapterId): array
    {
        $this->positiveId($chapterId, 'chapter_id');
        $rows = $this->fetchAll($this->prepare(
            "SELECT user_id, COUNT(DISTINCT element_id) AS element_count
             FROM {$this->tables->contributions}
             WHERE chapter_id = %d
             GROUP BY user_id
             ORDER BY element_count DESC, user_id",
            $chapterId
        ));

        return array_map(
            static fn (array $row): array => array(
                'user_id' => (int) $row['user_id'],
                'element_count' => (int) $row['element_count'],
            ),
            $rows
        );
    }

    /**
     * @return array{
     *   stats: array{works: int, chapters: int, elements: int},
     *   recent: list<array<string, mixed>>
     * }
     */
    public function publicProfileSummary(int $userId, int $recentLimit = 10): array
    {
        $this->positiveId($userId, 'user_id');
        $recentLimit = max(1, min(50, $recentLimit));
        $postsTable = $this->database->posts;
        $joins = " INNER JOIN {$this->tables->chapters} AS chapters
                       ON chapters.id = contributions.chapter_id AND chapters.is_published = 1
                   INNER JOIN {$postsTable} AS works
                       ON works.ID = contributions.work_id
                       AND works.post_type = 'mol_work'
                       AND works.post_status = 'publish'";
        $statsRow = $this->fetchOne($this->prepare(
            "SELECT COUNT(DISTINCT contributions.work_id) AS works,
                    COUNT(DISTINCT contributions.chapter_id) AS chapters,
                    COUNT(DISTINCT contributions.element_id) AS elements
             FROM {$this->tables->contributions} AS contributions{$joins}
             WHERE contributions.user_id = %d",
            $userId
        ));
        $recentRows = $this->fetchAll($this->prepare(
            "SELECT contributions.work_id,
                    works.post_title AS work_title,
                    contributions.chapter_id,
                    chapters.chapter_label,
                    COUNT(DISTINCT contributions.element_id) AS element_count,
                    MAX(contributions.last_contributed_at) AS last_contributed_at
             FROM {$this->tables->contributions} AS contributions{$joins}
             WHERE contributions.user_id = %d
             GROUP BY contributions.work_id, works.post_title,
                      contributions.chapter_id, chapters.chapter_label
             ORDER BY last_contributed_at DESC, contributions.chapter_id DESC
             LIMIT %d",
            $userId,
            $recentLimit
        ));

        return array(
            'stats' => array(
                'works' => (int) ($statsRow['works'] ?? 0),
                'chapters' => (int) ($statsRow['chapters'] ?? 0),
                'elements' => (int) ($statsRow['elements'] ?? 0),
            ),
            'recent' => array_map(
                static fn (array $row): array => array(
                    'work_id' => (int) $row['work_id'],
                    'work_title' => null === $row['work_title'] ? null : (string) $row['work_title'],
                    'chapter_id' => (int) $row['chapter_id'],
                    'chapter_label' => null === $row['chapter_label'] ? null : (string) $row['chapter_label'],
                    'element_count' => (int) $row['element_count'],
                    'last_contributed_at' => (string) $row['last_contributed_at'],
                ),
                $recentRows
            ),
        );
    }
}
