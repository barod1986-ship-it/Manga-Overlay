<?php

declare(strict_types=1);

namespace MOL\Repositories;

use MOL\Database\JsonDocument;
use MOL\Domain\Validation\AllowedValues;
use MOL\Domain\Validation\GeometryValidator;
use MOL\Domain\Validation\StyleValidator;

final class ElementRepository extends AbstractRepository
{
    /**
     * @param array{
     *   page_id: int,
     *   target_lang?: string,
     *   element_type: string,
     *   x_unit: int,
     *   y_unit: int,
     *   w_unit: int,
     *   h_unit: int,
     *   rotation_mdeg?: int,
     *   z_index?: int,
     *   content: string,
     *   style: array<string, mixed>,
     *   version?: int,
     *   created_by: int,
     *   updated_by?: int,
     *   created_at?: string,
     *   updated_at?: string
     * } $element
     */
    public function insert(array $element): int
    {
        $this->positiveId($element['page_id'], 'page_id');
        $this->positiveId($element['created_by'], 'created_by');
        $updatedBy = $element['updated_by'] ?? $element['created_by'];
        $this->positiveId($updatedBy, 'updated_by');
        AllowedValues::elementType($element['element_type']);
        GeometryValidator::validate($element);
        StyleValidator::validate($element['element_type'], $element['style']);
        $now = $this->utcNow();

        return $this->insertRow(
            $this->tables->elements,
            array(
                'page_id' => $element['page_id'],
                'target_lang' => $element['target_lang'] ?? 'ar',
                'element_type' => $element['element_type'],
                'x_unit' => $element['x_unit'],
                'y_unit' => $element['y_unit'],
                'w_unit' => $element['w_unit'],
                'h_unit' => $element['h_unit'],
                'rotation_mdeg' => $element['rotation_mdeg'] ?? 0,
                'z_index' => $element['z_index'] ?? 0,
                'content' => $element['content'],
                'style_json' => JsonDocument::encodeObject($element['style']),
                'version' => $element['version'] ?? 1,
                'created_by' => $element['created_by'],
                'updated_by' => $updatedBy,
                'created_at' => $element['created_at'] ?? $now,
                'updated_at' => $element['updated_at'] ?? $now,
            ),
            array('%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s')
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $elementId): ?array
    {
        $this->positiveId($elementId, 'element_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->elements} WHERE id = %d",
            $elementId
        ));

        return null === $row ? null : $this->normalize($row);
    }

    /** @return array<string, mixed>|null */
    public function lockForUpdate(int $elementId): ?array
    {
        $this->positiveId($elementId, 'element_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->elements} WHERE id = %d FOR UPDATE",
            $elementId
        ));

        return null === $row ? null : $this->normalize($row);
    }

    /** @param array<string, mixed> $changes */
    public function update(int $elementId, array $changes): void
    {
        $this->positiveId($elementId, 'element_id');
        $formatsByField = array(
            'x_unit' => '%d',
            'y_unit' => '%d',
            'w_unit' => '%d',
            'h_unit' => '%d',
            'rotation_mdeg' => '%d',
            'z_index' => '%d',
            'content' => '%s',
            'style' => '%s',
            'version' => '%d',
            'updated_by' => '%d',
            'updated_at' => '%s',
        );
        $data = array();
        $formats = array();
        foreach ($formatsByField as $field => $format) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }
            $databaseField = 'style' === $field ? 'style_json' : $field;
            $data[$databaseField] = 'style' === $field
                ? JsonDocument::encodeObject($changes[$field])
                : $changes[$field];
            $formats[] = $format;
        }
        if (array() === $data) {
            throw new \InvalidArgumentException('At least one element field is required.');
        }

        $this->updateRecord(
            $this->tables->elements,
            $data,
            array('id' => $elementId),
            $formats,
            array('%d')
        );
    }

    public function delete(int $elementId): bool
    {
        $this->positiveId($elementId, 'element_id');

        return 0 < $this->execute(
            $this->prepare("DELETE FROM {$this->tables->elements} WHERE id = %d", $elementId),
            'Deleting an element'
        );
    }

    /** @return list<array<string, mixed>> */
    public function forPage(int $pageId, string $targetLang = 'ar'): array
    {
        $this->positiveId($pageId, 'page_id');
        $rows = $this->fetchAll($this->prepare(
            "SELECT * FROM {$this->tables->elements} WHERE page_id = %d AND target_lang = %s ORDER BY z_index, id",
            $pageId,
            $targetLang
        ));

        return array_map($this->normalize(...), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function forChapter(int $chapterId, string $targetLang = 'ar'): array
    {
        $this->positiveId($chapterId, 'chapter_id');
        $rows = $this->fetchAll($this->prepare(
            "SELECT elements.*, pages.page_index
             FROM {$this->tables->elements} AS elements
             INNER JOIN {$this->tables->pages} AS pages ON pages.id = elements.page_id
             WHERE pages.chapter_id = %d AND elements.target_lang = %s
             ORDER BY pages.page_index, elements.z_index, elements.id",
            $chapterId,
            $targetLang
        ));

        return array_map($this->normalize(...), $rows);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalize(array $row): array
    {
        foreach (array(
            'id',
            'page_id',
            'x_unit',
            'y_unit',
            'w_unit',
            'h_unit',
            'rotation_mdeg',
            'z_index',
            'version',
            'created_by',
            'updated_by',
        ) as $field) {
            $row[$field] = (int) $row[$field];
        }
        if (array_key_exists('page_index', $row)) {
            $row['page_index'] = (int) $row['page_index'];
        }
        $row['style'] = JsonDocument::decodeObject((string) $row['style_json']);
        unset($row['style_json']);

        return $row;
    }
}
