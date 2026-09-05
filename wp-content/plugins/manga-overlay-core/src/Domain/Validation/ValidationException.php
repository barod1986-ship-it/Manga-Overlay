<?php

declare(strict_types=1);

namespace MOL\Domain\Validation;

use InvalidArgumentException;

final class ValidationException extends InvalidArgumentException
{
    public function __construct(private readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }
}
