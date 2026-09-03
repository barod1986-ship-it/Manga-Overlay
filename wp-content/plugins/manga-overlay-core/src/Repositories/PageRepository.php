<?php

declare(strict_types=1);

namespace MOL\Repositories;

final class PageRepository extends AbstractRepository
{
    public function insert(
        int $chapterId,
        int $pageIndex,
        int $attachmentId,
        int $naturalWidth,
        int $naturalHeight,
        ?string $createdAt = null
    ): int {
        $this->positiveId($chapterId, 'chapter_id');
        $this->positiveId($attachmentId, 'attachment_id');
        if ($pageIndex < 0 || $naturalWidth < 1 || $naturalHeight < 1) {
            throw new \InvalidArgumentException('Page index and natural dimensions are invalid.');
        }

        return $this->insertRow(
            $this->tables->pages,
            array(
                'chapter_id' => $chapterId,
                'page_index' => $pageIndex,
                'attachment_id' => $attachmentId,
                'natural_width' => $naturalWidth,
                'natural_height' => $naturalHeight,
                'created_at' => $createdAt ?? $this->utcNow(),
            ),
            array('%d', '%d', '%d', '%d', '%d', '%s')
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $pageId): ?array
    {
        $this->positiveId($pageId, 'page_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->pages} WHERE id = %d",
            $pageId
        ));

        return null === $row ? null : $this->normalize($row);
    }

    /** @return list<array<string, mixed>> */
    public function forChapter(int $chapterId): array
    {
        $this->positiveId($chapterId, 'chapter_id');
        $rows = $this->fetchAll($this->prepare(
            "SELECT * FROM {$this->tables->pages} WHERE chapter_id = %d ORDER BY page_index, id",
            $chapterId
        ));

        return array_map($this->normalize(...), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function lockForChapter(int $chapterId): array
    {
        $this->positiveId($chapterId, 'chapter_id');
        $rows = $this->fetchAll($this->prepare(
            "SELECT * FROM {$this->tables->pages} WHERE chapter_id = %d ORDER BY page_index, id FOR UPDATE",
            $chapterId
        ));

        return array_map($this->normalize(...), $rows);
    }

    public function moveToTemporaryRange(int $chapterId, int $offset): void
    {
        $this->positiveId($chapterId, 'chapter_id');
        if ($offset < 1) {
            throw new \InvalidArgumentException('Temporary page offset must be positive.');
        }

        $this->execute(
            $this->prepare(
                "UPDATE {$this->tables->pages} SET page_index = page_index + %d WHERE chapter_id = %d",
                $offset,
                $chapterId
            ),
            'Moving chapter pages into a temporary index range'
        );
    }

    /** @param list<int> $orderedPageIds */
    public function assignFinalOrder(int $chapterId, array $orderedPageIds): void
    {
        $this->positiveId($chapterId, 'chapter_id');
        if (array() === $orderedPageIds) {
            return;
        }

        $caseParts = array();
        $arguments = array();
        $idPlaceholders = array();
        foreach ($orderedPageIds as $index => $pageId) {
            $this->positiveId($pageId, 'page_id');
            $caseParts[] = 'WHEN %d THEN %d';
            $arguments[] = $pageId;
            $arguments[] = $index;
            $idPlaceholders[] = '%d';
        }
        $arguments[] = $chapterId;
        array_push($arguments, ...$orderedPageIds);

        $query = sprintf(
            'UPDATE %s SET page_index = CASE id %s END WHERE chapter_id = %%d AND id IN (%s)',
            $this->tables->pages,
            implode(' ', $caseParts),
            implode(', ', $idPlaceholders)
        );
        $this->execute(
            $this->prepare($query, ...$arguments),
            'Assigning final chapter page order'
        );
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalize(array $row): array
    {
        foreach (array('id', 'chapter_id', 'page_index', 'attachment_id', 'natural_width', 'natural_height') as $field) {
            $row[$field] = (int) $row[$field];
        }

        return $row;
    }
}
