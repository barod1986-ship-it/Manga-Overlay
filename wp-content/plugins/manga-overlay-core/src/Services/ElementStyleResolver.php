<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Domain\ElementStyles;
use MOL\Repositories\StylePresetRepository;
use MOL\REST\ApiException;

final class ElementStyleResolver
{
    public function __construct(private readonly StylePresetRepository $presets)
    {
    }

    /**
     * @param array<string, mixed> $chapter
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function resolve(
        int $userId,
        array $chapter,
        string $elementType,
        ?int $presetId,
        array $overrides
    ): array {
        $presetStyle = array();
        if (null !== $presetId) {
            $preset = $this->presets->find($presetId);
            if (null === $preset) {
                throw ApiException::invalidParams('preset_id does not identify an available preset.');
            }
            if ($elementType !== $preset['element_type']) {
                throw ApiException::invalidParams('preset_id does not match element_type.');
            }
            if (! $this->availableToUser($preset, $userId, (int) $chapter['work_id'])) {
                throw ApiException::forbidden('The selected preset is not available for this element.');
            }
            $presetStyle = is_array($preset['style']) ? $preset['style'] : array();
        } else {
            $candidates = $this->presets->defaultCandidates($userId, (int) $chapter['work_id'], $elementType);
            if (isset($candidates[0]['style']) && is_array($candidates[0]['style'])) {
                $presetStyle = $candidates[0]['style'];
            }
        }

        return ElementStyles::resolve($elementType, $presetStyle, $overrides);
    }

    /** @param array<string, mixed> $preset */
    private function availableToUser(array $preset, int $userId, int $workId): bool
    {
        return match ((string) $preset['scope']) {
            'personal' => $userId === (int) ($preset['owner_user_id'] ?? 0),
            'work' => $workId === (int) ($preset['work_id'] ?? 0),
            'global' => null === ($preset['owner_user_id'] ?? null) && null === ($preset['work_id'] ?? null),
            default => false,
        };
    }
}
