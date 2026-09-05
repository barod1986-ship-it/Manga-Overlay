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

    /** @return array<string, mixed>|null */
    public function lockForUpdate(int $presetId): ?array
    {
        $this->positiveId($presetId, 'preset_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->stylePresets} WHERE id = %d FOR UPDATE",
            $presetId
        ));

        return null === $row ? null : $this->normalize($row);
    }

    /** @return list<array<string, mixed>> */
    public function availableToUser(int $userId, ?int $workId = null, ?string $elementType = null): array
    {
        $this->positiveId($userId, 'user_id');
        if (null !== $workId) {
            $this->positiveId($workId, 'work_id');
        }
        if (null !== $elementType) {
            AllowedValues::elementType($elementType);
        }

        $scopeSql = "(scope = 'personal' AND owner_user_id = %d AND work_id IS NULL)"
            . " OR (scope = 'global' AND owner_user_id IS NULL AND work_id IS NULL)";
        $arguments = array($userId);
        if (null !== $workId) {
            $scopeSql .= " OR (scope = 'work' AND owner_user_id IS NULL AND work_id = %d)";
            $arguments[] = $workId;
        }
        $typeSql = '';
        if (null !== $elementType) {
            $typeSql = ' AND element_type = %s';
            $arguments[] = $elementType;
        }
        $query = "SELECT * FROM {$this->tables->stylePresets}
                  WHERE ({$scopeSql}){$typeSql}
                  ORDER BY element_type,
                    CASE scope WHEN 'personal' THEN 1 WHEN 'work' THEN 2 ELSE 3 END,
                    is_default DESC, name, id";
        $rows = $this->fetchAll($this->prepare($query, ...$arguments));

        return array_map($this->normalize(...), $rows);
    }

    /** @param array<string, mixed> $patch */
    public function update(int $presetId, array $patch): bool
    {
        $this->positiveId($presetId, 'preset_id');
        $data = array();
        $formats = array();
        if (array_key_exists('name', $patch)) {
            $data['name'] = (string) $patch['name'];
            $formats[] = '%s';
        }
        if (array_key_exists('style', $patch) && is_array($patch['style'])) {
            $data['style_json'] = JsonDocument::encodeObject($patch['style']);
            $formats[] = '%s';
        }
        if (array_key_exists('is_default', $patch)) {
            $data['is_default'] = ! empty($patch['is_default']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (array() === $data) {
            return false;
        }
        $data['updated_at'] = $this->utcNow();
        $formats[] = '%s';

        return 0 < $this->updateRecord(
            $this->tables->stylePresets,
            $data,
            array('id' => $presetId),
            $formats,
            array('%d')
        );
    }

    public function delete(int $presetId): bool
    {
        $this->positiveId($presetId, 'preset_id');

        return 0 < $this->execute($this->prepare(
            "DELETE FROM {$this->tables->stylePresets} WHERE id = %d",
            $presetId
        ), 'Deleting style preset');
    }

    /** @param array<string, mixed> $preset */
    public function lockDefaultGroup(array $preset): void
    {
        $scope = (string) $preset['scope'];
        $elementType = (string) $preset['element_type'];
        PresetScopeValidator::validate(
            $scope,
            isset($preset['owner_user_id']) ? (int) $preset['owner_user_id'] : null,
            isset($preset['work_id']) ? (int) $preset['work_id'] : null
        );
        AllowedValues::elementType($elementType);

        [$ownerSql, $workSql, $arguments] = $this->groupPredicate($preset);
        $arguments = array_merge(array($scope), $arguments, array($elementType));
        $this->fetchAll($this->prepare(
            "SELECT id FROM {$this->tables->stylePresets}
             WHERE scope = %s AND {$ownerSql} AND {$workSql} AND element_type = %s
             FOR UPDATE",
            ...$arguments
        ));
    }

    /** @param array<string, mixed> $preset */
    public function clearDefaultGroup(array $preset): int
    {
        [$ownerSql, $workSql, $arguments] = $this->groupPredicate($preset);
        $arguments = array_merge(array(
            $this->utcNow(),
            (string) $preset['scope'],
        ), $arguments, array((string) $preset['element_type']));

        return $this->execute($this->prepare(
            "UPDATE {$this->tables->stylePresets}
             SET is_default = 0, updated_at = %s
             WHERE scope = %s AND {$ownerSql} AND {$workSql}
               AND element_type = %s AND is_default = 1",
            ...$arguments
        ), 'Clearing style preset defaults');
    }

    /** @param array<string, mixed> $preset @return array{0: string, 1: string, 2: list<int>} */
    private function groupPredicate(array $preset): array
    {
        $ownerId = isset($preset['owner_user_id']) ? (int) $preset['owner_user_id'] : null;
        $workId = isset($preset['work_id']) ? (int) $preset['work_id'] : null;
        $arguments = array();
        $ownerSql = 'owner_user_id IS NULL';
        $workSql = 'work_id IS NULL';
        if (null !== $ownerId) {
            $ownerSql = 'owner_user_id = %d';
            $arguments[] = $ownerId;
        }
        if (null !== $workId) {
            $workSql = 'work_id = %d';
            $arguments[] = $workId;
        }

        return array($ownerSql, $workSql, $arguments);
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
