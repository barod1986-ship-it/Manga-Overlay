<?php

declare(strict_types=1);

namespace MOL\REST;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status,
        private readonly array $details = array()
    ) {
        parent::__construct($message);
    }

    public static function invalidParams(string $message = 'Request validation failed.'): self
    {
        return new self('mol_invalid_params', $message, 400);
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return new self('mol_not_found', $message, 404);
    }

    public static function forbidden(string $message = 'You are not allowed to perform this action.'): self
    {
        return new self('mol_forbidden', $message, 403);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->details;
    }
}
