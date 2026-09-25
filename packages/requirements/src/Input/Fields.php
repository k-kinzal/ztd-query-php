<?php

declare(strict_types=1);

namespace Requirements\Config;

use InvalidArgumentException;

final class Fields
{
    /** @return array<string, mixed> */
    public static function mapping(mixed $value, string $context): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("$context must be a mapping.");
        }
        foreach ($value as $key => $_) {
            if (!is_string($key)) {
                throw new InvalidArgumentException("$context must have string keys.");
            }
        }
        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $allowed
     */
    public static function keys(array $data, array $allowed, string $context): void
    {
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("$context: unknown field '$key'.");
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function text(array $data, string $key, ?string $default = null): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("$key must be a nonempty string.");
        }
        return $value;
    }

    /** @return list<mixed> */
    public static function sequence(mixed $value, string $context): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException("$context must be a list.");
        }
        return $value;
    }

    /** @return list<string> */
    public static function strings(mixed $value, string $context, bool $unique = true): array
    {
        $result = [];
        foreach (self::sequence($value, $context) as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                throw new InvalidArgumentException("$context must contain nonempty strings.");
            }
            $result[] = $entry;
        }
        if ($unique && count(array_unique($result)) !== count($result)) {
            throw new InvalidArgumentException("$context contains duplicates.");
        }
        return $result;
    }

    public static function percentage(mixed $value, string $context): float
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0 || $value > 100) {
            throw new InvalidArgumentException("$context must be a number from 0 to 100.");
        }
        return (float) $value;
    }
}
