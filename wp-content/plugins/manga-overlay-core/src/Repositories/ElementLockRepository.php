<?php

declare(strict_types=1);

namespace MOL\Repositories;

final class ElementLockRepository extends AbstractRepository
{
    public function insert(
        int $elementId,
        int $userId,
        string $lockToken,
        string $acquiredAt,
        string $expiresAt
    ): void {
        $this->positiveId($elementId, 'element_id');
        $this->positiveId($userId, 'user_id');
        if (64 !== strlen($lockToken)) {
            throw new \InvalidArgumentException('lock_token must contain exactly 64 characters.');
        }

        $this->insertRecord(
            $this->tables->elementLocks,
            array(
                'element_id' => $elementId,
                'user_id' => $userId,
                'lock_token' => $lockToken,
                'acquired_at' => $acquiredAt,
                'expires_at' => $expiresAt,
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
    }

    /** @return array<string, mixed>|null */
    public function findForElement(int $elementId): ?array
    {
        $this->positiveId($elementId, 'element_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->elementLocks} WHERE element_id = %d",
            $elementId
        ));

        if (null === $row) {
            return null;
        }
        $row['element_id'] = (int) $row['element_id'];
        $row['user_id'] = (int) $row['user_id'];

        return $row;
    }

    public function deleteForElement(int $elementId): bool
    {
        $this->positiveId($elementId, 'element_id');

        return 0 < $this->execute(
            $this->prepare("DELETE FROM {$this->tables->elementLocks} WHERE element_id = %d", $elementId),
            'Deleting an element lock'
        );
    }

    public function deleteExpired(string $utcNow): int
    {
        return $this->execute(
            $this->prepare("DELETE FROM {$this->tables->elementLocks} WHERE expires_at <= %s", $utcNow),
            'Deleting expired element locks'
        );
    }
}
