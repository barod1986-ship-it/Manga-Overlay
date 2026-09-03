<?php

declare(strict_types=1);

namespace MOL\Repositories;

use MOL\Database\JsonDocument;
use MOL\Domain\Validation\AllowedValues;
use MOL\Domain\Validation\PresetScopeValidator;
use MOL\Domain\Validation\StyleValidator;

final class StylePresetRepository extends AbstractRepository
{
    /**
     * @param array{
     *   scope: string,
     *   owner_user_id?: int|null,
     *   work_id?: int|null,
     *   name: string,
     *   element_type: string,
     *   style: array<string, mixed>,
     *   is_default?: bool,
     *   created_by: int,
     *   created_at?: string,
     *   updated_at?: string
     * } $preset
     */
    public function insert(array $preset): int
    {
        $ownerUserId = $preset['owner_user_id'] ?? null;
        $workId = $preset['work_id'] ?? null;
        PresetScopeValidator::validate($preset['scope'], $ownerUserId, $workId);
        AllowedValues::elementType($preset['element_type']);
        StyleValidator::validate($preset['element_type'], $preset['style']);
        $this->positiveId($preset['created_by'], 'created_by');
        $now = $this->utcNow();

        return $this->insertRow(
            $this->tables->stylePresets,
            array(
                'scope' => $preset['scope'],
                'owner_user_id' => $ownerUserId,
                'work_id' => $workId,
                'name' => $preset['name'],
                'element_type' => $preset['element_type'],
                'style_json' => JsonDocument::encodeObject($preset['style']),
                'is_default' => ! empty($preset['is_default']) ? 1 : 0,
                'created_by' => $preset['created_by'],
                'created_at' => $preset['created_at'] ?? $now,
                'updated_at' => $preset['updated_at'] ?? $now,
            ),
            array('%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $presetId): ?array
    {
        $this->positiveId($presetId, 'preset_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->stylePresets} WHERE id = %d",
            $presetId
        ));

        return null === $row ? null : $this->normalize($row);
    }

    /** @return list<array<string, mixed>> */
    public function defaultCandidates(int $userId, int $workId, string $elementType): array
    {
        $this->positiveId($userId, 'user_id');
        $this->positiveId($workId, 'work_id');
        AllowedValues::elementType($elementType);
        $rows = $this->fetchAll($this->prepare(
            "SELECT * FROM {$this->tables->stylePresets}
             WHERE element_type = %s AND is_default = 1
               AND (
                    (scope = 'personal' AND owner_user_id = %d AND work_id IS NULL)
                 OR (scope = 'work' AND owner_user_id IS NULL AND work_id = %d)
                 OR (scope = 'global' AND owner_user_id IS NULL AND work_id IS NULL)
               )
             ORDER BY CASE scope WHEN 'personal' THEN 1 WHEN 'work' THEN 2 ELSE 3 END, id",
            $elementType,
            $userId,
            $workId
        ));

        return array_map($this->normalize(...), $rows);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function normalize(array $row): array
    {
        foreach (array('id', 'created_by') as $field) {
            $row[$field] = (int) $row[$field];
        }
        foreach (array('owner_user_id', 'work_id') as $field) {
            $row[$field] = null === $row[$field] ? null : (int) $row[$field];
        }
        $row['is_default'] = 1 === (int) $row['is_default'];
        $row['style'] = JsonDocument::decodeObject((string) $row['style_json']);
        unset($row['style_json']);

        return $row;
    }
}
