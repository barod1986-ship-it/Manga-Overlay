<?php

declare(strict_types=1);

namespace MOL\Database;

use JsonException;
use stdClass;
use UnexpectedValueException;

final class JsonDocument
{
    /** @param array<string, mixed> $value */
    public static function encodeObject(array $value): string
    {
        return self::encode(self::objectify($value));
    }

    public static function encode(mixed $value): string
    {
        $json = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (false === $json) {
            throw new UnexpectedValueException('JSON encoding failed.');
        }

        return $json;
    }

    /** @return array<string, mixed> */
    public static function decodeObject(string $json): array
    {
        try {
            $object = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new UnexpectedValueException('Stored JSON is invalid.', 0, $error);
        }

        if (! $object instanceof stdClass || ! is_array($value)) {
            throw new UnexpectedValueException('Stored JSON must be an object.');
        }

        return $value;
    }

    public static function decode(string $json): mixed
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new UnexpectedValueException('Stored JSON is invalid.', 0, $error);
        }
    }

    /** @param array<string, mixed> $value */
    private static function objectify(array $value): stdClass
    {
        $object = new stdClass();

        foreach ($value as $key => $item) {
            $object->{$key} = is_array($item) ? self::objectify($item) : $item;
        }

        return $object;
    }
}
