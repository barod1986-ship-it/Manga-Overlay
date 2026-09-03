<?php

declare(strict_types=1);

namespace MOL\Database;

use RuntimeException;

final class DatabaseException extends RuntimeException
{
    public static function fromWpdb(\wpdb $database, string $operation): self
    {
        $detail = '' !== $database->last_error ? $database->last_error : 'unknown database error';

        return new self(sprintf('%s failed: %s', $operation, $detail));
    }
}
