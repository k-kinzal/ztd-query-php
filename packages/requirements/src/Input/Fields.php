<?php

declare(strict_types=1);

namespace Requirements\Input;

/**
 * Narrows decoded YAML, JSON and Markdown values to the shapes the definitions require.
 *
 * Every accessor either returns the typed value or raises an InvalidInputException whose
 * message names the context, so callers never handle loosely typed data themselves.
 */
final class Fields
{
    /**
     * Requires a mapping with string keys.
     *
     * @param mixed $value The decoded value
     * @param string $context The name used in the error message
     *
     * @return array<string, mixed> The mapping
     *
     * @throws InvalidInputException When the value is not a mapping with string keys
     */
    public static function mapping(mixed $value, string $context): array
    {
        if (!is_array($value)) {
            throw new InvalidInputException("$context must be a mapping.");
        }
        foreach ($value as $key => $_) {
            if (!is_string($key)) {
                throw new InvalidInputException("$context must have string keys.");
            }
        }
        return $value;
    }

    /**
     * Rejects keys that the context does not define.
     *
     * @param array<string, mixed> $data The mapping to check
     * @param list<string> $allowed The keys the context defines
     * @param string $context The name used in the error message
     *
     * @throws InvalidInputException When the mapping has a key outside the allowed list
     */
    public static function keys(array $data, array $allowed, string $context): void
    {
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidInputException("$context: unknown field '$key'.");
            }
        }
    }

    /**
     * Reads a nonempty string field.
     *
     * @param array<string, mixed> $data The mapping holding the field
     * @param string $key The field name
     * @param string|null $default The value used when the field is absent
     *
     * @return string The field value
     *
     * @throws InvalidInputException When the value is missing, not a string or blank
     */
    public static function text(array $data, string $key, ?string $default = null): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidInputException("$key must be a nonempty string.");
        }
        return $value;
    }

    /**
     * Requires a list.
     *
     * @param mixed $value The decoded value
     * @param string $context The name used in the error message
     *
     * @return list<mixed> The list
     *
     * @throws InvalidInputException When the value is not a list
     */
    public static function sequence(mixed $value, string $context): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidInputException("$context must be a list.");
        }
        return $value;
    }

    /**
     * Requires a list of nonempty strings.
     *
     * @param mixed $value The decoded value
     * @param string $context The name used in the error message
     * @param bool $unique Whether duplicates are rejected
     *
     * @return list<string> The strings
     *
     * @throws InvalidInputException When the value is not a list of nonempty strings or repeats one
     */
    public static function strings(mixed $value, string $context, bool $unique = true): array
    {
        $result = [];
        foreach (self::sequence($value, $context) as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                throw new InvalidInputException("$context must contain nonempty strings.");
            }
            $result[] = $entry;
        }
        if ($unique && count(array_unique($result)) !== count($result)) {
            throw new InvalidInputException("$context contains duplicates.");
        }
        return $result;
    }

    /**
     * Requires a finite number from 0 to 100.
     *
     * @param mixed $value The decoded value
     * @param string $context The name used in the error message
     *
     * @return float The percentage
     *
     * @throws InvalidInputException When the value is not a number within 0 to 100
     */
    public static function percentage(mixed $value, string $context): float
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0 || $value > 100) {
            throw new InvalidInputException("$context must be a number from 0 to 100.");
        }
        return (float) $value;
    }
}
