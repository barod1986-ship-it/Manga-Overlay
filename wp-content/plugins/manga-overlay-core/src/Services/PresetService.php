<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\TransactionManager;
use MOL\Domain\Validation\StyleValidator;
use MOL\Domain\Validation\ValidationException;
use MOL\Repositories\StylePresetRepository;
use MOL\Repositories\WorkRepository;
use MOL\REST\ApiException;

final class PresetService
{
    public function __construct(
        private readonly StylePresetRepository $presets,
        private readonly WorkRepository $works,
        private readonly TransactionManager $transactions
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function available(int $userId, ?int $workId, ?string $elementType): array
    {
        $this->assertEditor($userId);
        if (null !== $workId) {
            $this->assertWorkEditor($userId, $workId);
        }

        return $this->presets->availableToUser($userId, $workId, $elementType);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function create(int $userId, array $input): array
    {
        $this->assertEditor($userId);
        $preset = $this->ownedInput($userId, $input);
        $this->assertCanManage($userId, $preset);

        return $this->transactions->run(function () use ($preset): array {
            if (! empty($preset['is_default'])) {
                $this->presets->lockDefaultGroup($preset);
                $this->presets->clearDefaultGroup($preset);
            }
            $presetId = $this->presets->insert($preset);

            return $this->presets->find($presetId)
                ?? throw new \RuntimeException('The created preset could not be loaded.');
        });
    }

    /** @param array<string, mixed> $patch @return array<string, mixed> */
    public function update(int $presetId, int $userId, array $patch): array
    {
        $this->assertEditor($userId);

        return $this->transactions->run(function () use ($presetId, $userId, $patch): array {
            $preset = $this->presets->lockForUpdate($presetId);
            if (null === $preset) {
                throw ApiException::notFound('Preset not found.');
            }
            $this->assertCanManage($userId, $preset);
            if (isset($patch['style']) && is_array($patch['style'])) {
                try {
                    StyleValidator::validate((string) $preset['element_type'], $patch['style']);
                } catch (ValidationException $error) {
                    throw ApiException::invalidParams($error->getMessage());
                }
            }
            if (true === ($patch['is_default'] ?? false)) {
                $this->presets->lockDefaultGroup($preset);
                $this->presets->clearDefaultGroup($preset);
            }
            $this->presets->update($presetId, $patch);

            return $this->presets->find($presetId)
                ?? throw new \RuntimeException('The updated preset could not be loaded.');
        });
    }

    public function delete(int $presetId, int $userId): void
    {
        $this->assertEditor($userId);
        $this->transactions->run(function () use ($presetId, $userId): void {
            $preset = $this->presets->lockForUpdate($presetId);
            if (null === $preset) {
                throw ApiException::notFound('Preset not found.');
            }
            $this->assertCanManage($userId, $preset);
            if (! $this->presets->delete($presetId)) {
                throw ApiException::notFound('Preset not found.');
            }
        });
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function ownedInput(int $userId, array $input): array
    {
        $scope = (string) $input['scope'];
        $workId = isset($input['work_id']) ? (int) $input['work_id'] : null;
        if ('work' === $scope && null === $workId) {
            throw ApiException::invalidParams('work_id is required for a work preset.');
        }
        if ('work' !== $scope && null !== $workId) {
            throw ApiException::invalidParams('work_id is only allowed for a work preset.');
        }

        return array_merge($input, array(
            'owner_user_id' => 'personal' === $scope ? $userId : null,
            'work_id' => 'work' === $scope ? $workId : null,
            'created_by' => $userId,
        ));
    }

    /** @param array<string, mixed> $preset */
    private function assertCanManage(int $userId, array $preset): void
    {
        $scope = (string) $preset['scope'];
        if ('personal' === $scope) {
            if ($userId !== (int) ($preset['owner_user_id'] ?? 0)) {
                throw ApiException::forbidden();
            }
            return;
        }
        if ('work' === $scope) {
            if (! user_can($userId, 'mol_manage_work_presets')) {
                throw ApiException::forbidden();
            }
            $this->assertWorkEditor($userId, (int) ($preset['work_id'] ?? 0));
            return;
        }
        if ('global' !== $scope || ! user_can($userId, 'mol_manage_global_presets')) {
            throw ApiException::forbidden();
        }
    }

    private function assertEditor(int $userId): void
    {
        if ($userId < 1 || ! user_can($userId, 'mol_use_editor')) {
            throw ApiException::forbidden();
        }
    }

    private function assertWorkEditor(int $userId, int $workId): void
    {
        $work = 0 < $workId ? $this->works->find($workId) : null;
        if (! $work instanceof \WP_Post) {
            throw ApiException::notFound('Work not found.');
        }
        if (! apply_filters('mol_user_can_edit_work', true, $userId, $work)) {
            throw ApiException::forbidden();
        }
    }
}
