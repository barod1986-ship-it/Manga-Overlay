<?php

declare(strict_types=1);

namespace MOL\Repositories;

use MOL\Database\DatabaseException;
use MOL\Database\TableNames;
use RuntimeException;

abstract class AbstractRepository
{
    protected readonly TableNames $tables;

    public function __construct(protected readonly \wpdb $database)
    {
        $this->tables = new TableNames($database->prefix);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $formats
     */
    protected function insertRow(string $table, array $data, array $formats): int
    {
        $this->insertRecord($table, $data, $formats);

        $insertId = (int) $this->database->insert_id;
        if ($insertId < 1) {
            throw new RuntimeException(sprintf('Insert into %s returned no identifier.', $table));
        }

        return $insertId;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $formats
     */
    protected function insertRecord(string $table, array $data, array $formats): void
    {
        $result = $this->database->insert($table, $data, $formats);
        if (false === $result) {
            throw DatabaseException::fromWpdb($this->database, sprintf('Insert into %s', $table));
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     * @param list<string> $formats
     * @param list<string> $whereFormats
     */
    protected function updateRecord(
        string $table,
        array $data,
        array $where,
        array $formats,
        array $whereFormats
    ): int {
        $result = $this->database->update($table, $data, $where, $formats, $whereFormats);
        if (false === $result) {
            throw DatabaseException::fromWpdb($this->database, sprintf('Update %s', $table));
        }

        return (int) $result;
    }

    /** @return array<string, mixed>|null */
    protected function fetchOne(string $query): ?array
    {
        $row = $this->database->get_row($query, ARRAY_A);
        $this->throwOnLastError('Fetching one database row');

        if (null === $row) {
            return null;
        }
        if (! is_array($row)) {
            throw new RuntimeException('WordPress returned an unexpected row representation.');
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    protected function fetchAll(string $query): array
    {
        $rows = $this->database->get_results($query, ARRAY_A);
        $this->throwOnLastError('Fetching database rows');

        if (! is_array($rows)) {
            throw new RuntimeException('WordPress returned an unexpected result set.');
        }

        return array_values($rows);
    }

    protected function execute(string $query, string $operation): int
    {
        $result = $this->database->query($query);
        if (false === $result) {
            throw DatabaseException::fromWpdb($this->database, $operation);
        }

        return (int) $result;
    }

    protected function prepare(string $query, mixed ...$arguments): string
    {
        $prepared = $this->database->prepare($query, ...$arguments);
        if (! is_string($prepared)) {
            throw new RuntimeException('WordPress could not prepare a database query.');
        }

        return $prepared;
    }

    protected function utcNow(): string
    {
        return current_time('mysql', true);
    }

    protected function positiveId(int $value, string $field): void
    {
        if ($value < 1) {
            throw new \InvalidArgumentException(sprintf('%s must be positive.', $field));
        }
    }

    private function throwOnLastError(string $operation): void
    {
        if ('' !== $this->database->last_error) {
            throw DatabaseException::fromWpdb($this->database, $operation);
        }
    }
}
