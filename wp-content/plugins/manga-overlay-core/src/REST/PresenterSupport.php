<?php

declare(strict_types=1);

namespace MOL\REST;

final class PresenterSupport
{
    public static function dateTime(mixed $value): ?string
    {
        if (! is_string($value) || '' === $value) {
            return null;
        }
        $timestamp = strtotime($value . ' UTC');

        return false === $timestamp ? null : gmdate('c', $timestamp);
    }
}
