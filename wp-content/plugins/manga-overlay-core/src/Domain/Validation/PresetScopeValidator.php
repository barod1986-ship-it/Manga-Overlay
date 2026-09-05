<?php

declare(strict_types=1);

namespace MOL\Domain\Validation;

final class PresetScopeValidator
{
    public static function validate(string $scope, ?int $ownerUserId, ?int $workId): void
    {
        AllowedValues::presetScope($scope);

        $valid = match ($scope) {
            'personal' => null !== $ownerUserId && $ownerUserId > 0 && null === $workId,
            'work' => null === $ownerUserId && null !== $workId && $workId > 0,
            'global' => null === $ownerUserId && null === $workId,
        };

        if (! $valid) {
            throw new ValidationException('scope', 'Preset ownership does not match its effective scope.');
        }
    }
}
