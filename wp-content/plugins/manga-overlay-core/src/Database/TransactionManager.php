<?php

declare(strict_types=1);

namespace MOL\Database;

use LogicException;
use Throwable;

final class TransactionManager
{
    private bool $active = false;

    public function __construct(private readonly \wpdb $database)
    {
    }

    public function begin(): void
    {
        if ($this->active) {
            throw new LogicException('Nested transactions are not supported.');
        }

        $this->execute('START TRANSACTION', 'Starting transaction');
        $this->active = true;
    }

    public function commit(): void
    {
        if (! $this->active) {
            throw new LogicException('No transaction is active.');
        }

        $this->execute('COMMIT', 'Committing transaction');
        $this->active = false;
    }

    public function rollback(): void
    {
        if (! $this->active) {
            return;
        }

        $this->execute('ROLLBACK', 'Rolling back transaction');
        $this->active = false;
    }

    /** @template T @param callable(): T $operation @return T */
    public function run(callable $operation): mixed
    {
        $this->begin();

        try {
            $result = $operation();
            $this->commit();

            return $result;
        } catch (Throwable $error) {
            try {
                $this->rollback();
            } catch (Throwable) {
                // Preserve the application error that caused the rollback.
            }

            throw $error;
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    private function execute(string $sql, string $operation): void
    {
        if (false === $this->database->query($sql)) {
            throw DatabaseException::fromWpdb($this->database, $operation);
        }
    }
}
