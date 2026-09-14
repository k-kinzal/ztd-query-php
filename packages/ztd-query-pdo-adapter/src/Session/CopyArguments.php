<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use ZtdQuery\Adapter\Pdo\ZtdPdoException;

/**
 * Validates native COPY arguments before dispatching typed COPY operations.
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
final class CopyArguments
{
    /**
     * Validate named string arguments in their native evaluation order.
     *
     * @template TName of string
     * @template TValue
     * @param array<TName, TValue> $arguments
     * @return array<TName, string>
     * @throws ZtdPdoException When a supplied value is not a string.
     */
    public function strings(array $arguments): array
    {
        $strings = [];
        foreach ($arguments as $name => $value) {
            if (!is_string($value)) {
                throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $%s must be a string, %s given.', $name, get_debug_type($value)));
            }
            $strings[$name] = $value;
        }
        return $strings;
    }

    /**
     * Validate the optional column list.
     *
     * @template TValue
     * @param TValue $fields
     * @throws ZtdPdoException When a supplied column list is neither null nor a string.
     */
    public function fields(mixed $fields): ?string
    {
        if ($fields !== null && !is_string($fields)) {
            throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $fields must be a string, %s given.', get_debug_type($fields)));
        }
        return $fields;
    }

    /**
     * Materialize and validate all input lines before COPY can write a row.
     *
     * @template TKey
     * @template TValue
     * @param iterable<TKey, TValue> $rows
     * @return list<string>
     * @throws ZtdPdoException When a row is not an encoded string.
     */
    public function rows(iterable $rows): array
    {
        $lines = [];
        foreach ($rows as $row) {
            if (!is_string($row)) {
                throw new ZtdPdoException(sprintf('PostgreSQL COPY rows must be strings, %s given.', get_debug_type($row)));
            }
            $lines[] = $row;
        }
        return $lines;
    }
}
