<?php

declare(strict_types=1);

namespace MOL\Repositories;

use MOL\Domain\Validation\AllowedValues;

final class ChapterRepository extends AbstractRepository
{
    /**
     * @param array{
     *   work_id: int,
     *   chapter_label: string,
     *   sort_order?: float|int,
     *   title?: string|null,
     *   slug: string,
     *   translation_status?: string,
     *   source_lang_override?: string|null,
     *   reader_mode_override?: string|null,
     *   direction_override?: string|null,
     *   is_published?: bool,
     *   published_at?: string|null,
     *   created_by: int,
     *   created_at?: string,
     *   updated_at?: string
     * } $chapter
     */
    public function insert(array $chapter): int
    {
        $this->positiveId($chapter['work_id'], 'work_id');
        $this->positiveId($chapter['created_by'], 'created_by');
        $status = $chapter['translation_status'] ?? 'untranslated';
        $readerMode = $chapter['reader_mode_override'] ?? null;
        $direction = $chapter['direction_override'] ?? null;
        AllowedValues::chapterTranslationStatus($status);
        AllowedValues::readerMode($readerMode, true, 'reader_mode_override');
        AllowedValues::direction($direction, true, 'direction_override');
        $now = $this->utcNow();

        return $this->insertRow(
            $this->tables->chapters,
            array(
                'work_id' => $chapter['work_id'],
                'chapter_label' => $chapter['chapter_label'],
                'sort_order' => $chapter['sort_order'] ?? 0,
                'title' => $chapter['title'] ?? null,
                'slug' => $chapter['slug'],
                'translation_status' => $status,
                'source_lang_override' => $chapter['source_lang_override'] ?? null,
                'reader_mode_override' => $readerMode,
                'direction_override' => $direction,
                'is_published' => ! empty($chapter['is_published']) ? 1 : 0,
                'published_at' => $chapter['published_at'] ?? null,
                'created_by' => $chapter['created_by'],
                'created_at' => $chapter['created_at'] ?? $now,
                'updated_at' => $chapter['updated_at'] ?? $now,
            ),
            array('%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $chapterId): ?array
    {
        $this->positiveId($chapterId, 'chapter_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->chapters} WHERE id = %d",
            $chapterId
        ));

        return null === $row ? null : $this->normalize($row);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(int $workId, string $slug): ?array
    {
        $this->positiveId($workId, 'work_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->chapters} WHERE work_id = %d AND slug = %s",
            $workId,
            $slug
        ));

        return null === $row ? null : $this->normalize($row);
    }

    /** @return array<string, mixed>|null */
    public function lockForUpdate(int $chapterId): ?array
    {
        $this->positiveId($chapterId, 'chapter_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->chapters} WHERE id = %d FOR UPDATE",
            $chapterId
        ));

        return null === $row ? null : $this->normalize($row);
    }

    /**
     * @param array{
     *   chapter_label?: string,
     *   sort_order?: float|int,
     *   title?: string|null,
     *   translation_status?: string,
     *   source_lang_override?: string|null,
     *   reader_mode_override?: string|null,
     *   direction_override?: string|null,
     *   is_published?: bool,
     *   published_at?: string|null,
     *   updated_at?: string
     * } $changes
     */
    public function update(int $chapterId, array $changes): void
    {
        $this->positiveId($chapterId, 'chapter_id');
        if (array_key_exists('translation_status', $changes)) {
            AllowedValues::chapterTranslationStatus($changes['translation_status']);
        }
        if (array_key_exists('reader_mode_override', $changes)) {
            AllowedValues::readerMode($changes['reader_mode_override'], true, 'reader_mode_override');
        }
        if (array_key_exists('direction_override', $changes)) {
            AllowedValues::direction($changes['direction_override'], true, 'direction_override');
        }

        $formatsByField = array(
            'chapter_label' => '%s',
            'sort_order' => '%f',
            'title' => '%s',
            'translation_status' => '%s',
            'source_lang_override' => '%s',
            'reader_mode_override' => '%s',
            'direction_override' => '%s',
            'is_published' => '%d',
            'published_at' => '%s',
            'updated_at' => '%s',
        );
        $data = array();
        $formats = array();
        foreach ($formatsByField as $field => $format) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }

            $value = $changes[$field];
            if ('is_published' === $field) {
                $value = $value ? 1 : 0;
            }
            $data[$field] = $value;
            $formats[] = $format;
        }

        if (! array_key_exists('updated_at', $data)) {
            $data['updated_at'] = $this->utcNow();
            $formats[] = '%s';
        }

        $this->updateRecord(
            $this->tables->chapters,
            $data,
            array('id' => $chapterId),
            $formats,
            array('%d')
        );
    }

    /** @return list<array<string, mixed>> */
    public function forWork(int $workId, bool $publishedOnly = false): array
    {
        $this->positiveId($workId, 'work_id');
        $publishedClause = $publishedOnly ? ' AND is_published = 1' : '';
        $rows = $this->fetchAll($this->prepare(
            "SELECT * FROM {$this->tables->chapters} WHERE work_id = %d{$publishedClause} ORDER BY sort_order, id",
            $workId
        ));

        return array_map($this->normalize(...), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $rows = $this->fetchAll($this->prepare(
            "SELECT * FROM {$this->tables->chapters} ORDER BY id DESC LIMIT %d",
            $limit
        ));

        return array_map($this->normalize(...), $rows);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalize(array $row): array
    {
        foreach (array('id', 'work_id', 'created_by') as $field) {
            $row[$field] = (int) $row[$field];
        }
        $row['sort_order'] = (float) $row['sort_order'];
        $row['is_published'] = 1 === (int) $row['is_published'];

        return $row;
    }
}
