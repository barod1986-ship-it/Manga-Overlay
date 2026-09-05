<?php

declare(strict_types=1);

namespace MOL\REST;

final class PresetPresenter
{
    /** @param array<string, mixed> $preset @return array<string, mixed> */
    public static function one(array $preset): array
    {
        return array(
            'id' => (int) $preset['id'],
            'scope' => (string) $preset['scope'],
            'owner_user_id' => null === $preset['owner_user_id'] ? null : (int) $preset['owner_user_id'],
            'work_id' => null === $preset['work_id'] ? null : (int) $preset['work_id'],
            'name' => (string) $preset['name'],
            'element_type' => (string) $preset['element_type'],
            'style' => is_array($preset['style']) ? $preset['style'] : array(),
            'is_default' => (bool) $preset['is_default'],
            'created_by' => (int) $preset['created_by'],
            'created_at' => PresenterSupport::dateTime($preset['created_at']),
            'updated_at' => PresenterSupport::dateTime($preset['updated_at']),
        );
    }

    /** @param list<array<string, mixed>> $presets @return list<array<string, mixed>> */
    public static function many(array $presets): array
    {
        return array_map(self::one(...), $presets);
    }
}
