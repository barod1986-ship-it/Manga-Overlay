<?php

declare(strict_types=1);

namespace MOL\Repositories;

use MOL\Database\JsonDocument;

final class IdempotencyKeyRepository extends AbstractRepository
{
    /**
     * @param array{
     *   user_id: int,
     *   scope: string,
     *   idempotency_key: string,
     *   request_hash: string,
     *   resource_type?: string|null,
     *   resource_id?: int|null,
     *   response_code?: int|null,
     *   response?: mixed,
     *   created_at?: string,
     *   expires_at: string
     * } $record
     */
    public function insert(array $record): int
    {
        $this->positiveId($record['user_id'], 'user_id');
        if (64 !== strlen($record['request_hash'])) {
            throw new \InvalidArgumentException('request_hash must contain exactly 64 characters.');
        }
        $hasResponse = array_key_exists('response', $record) && null !== $record['response'];

        return $this->insertRow(
            $this->tables->idempotencyKeys,
            array(
                'user_id' => $record['user_id'],
                'scope' => $record['scope'],
                'idempotency_key' => $record['idempotency_key'],
                'request_hash' => $record['request_hash'],
                'resource_type' => $record['resource_type'] ?? null,
                'resource_id' => $record['resource_id'] ?? null,
                'response_code' => $record['response_code'] ?? null,
                'response_json' => $hasResponse ? JsonDocument::encode($record['response']) : null,
                'created_at' => $record['created_at'] ?? $this->utcNow(),
                'expires_at' => $record['expires_at'],
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s')
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, string $scope, string $idempotencyKey): ?array
    {
        $this->positiveId($userId, 'user_id');
        $row = $this->fetchOne($this->prepare(
            "SELECT * FROM {$this->tables->idempotencyKeys}
             WHERE user_id = %d AND scope = %s AND idempotency_key = %s",
            $userId,
            $scope,
            $idempotencyKey
        ));

        if (null === $row) {
            return null;
        }
        foreach (array('id', 'user_id') as $field) {
            $row[$field] = (int) $row[$field];
        }
        foreach (array('resource_id', 'response_code') as $field) {
            $row[$field] = null === $row[$field] ? null : (int) $row[$field];
        }
        $row['response'] = null === $row['response_json']
            ? null
            : JsonDocument::decode((string) $row['response_json']);
        unset($row['response_json']);

        return $row;
    }

    public function deleteExpired(string $utcNow): int
    {
        return $this->execute(
            $this->prepare("DELETE FROM {$this->tables->idempotencyKeys} WHERE expires_at <= %s", $utcNow),
            'Deleting expired idempotency keys'
        );
    }
}
