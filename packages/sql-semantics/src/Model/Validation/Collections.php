<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

/**
 * Enforces collection element types that PHP's native array type cannot express.
 *
 * @visibility SqlSemantics
 */
final class Collections
{
    /**
     * @template TValue
     * @param array<array-key, TValue> $values Runtime collection
     * @param class-string $class Required element class
     * @throws InvalidStructure
     */
    public static function objects(array $values, string $class, bool $list = true): void
    {
        if ($list && !array_is_list($values)) {
            throw new InvalidStructure('An ordered semantic collection must be a list.');
        }
        foreach ($values as $value) {
            if (!$value instanceof $class) {
                throw new InvalidStructure('A semantic collection contains an invalid element type.');
            }
        }
    }

    /**
     * @template TValue
     * @param array<array-key, TValue> $values Runtime name parts or identity list
     * @throws InvalidStructure
     */
    public static function strings(array $values): void
    {
        if (!array_is_list($values)) {
            throw new InvalidStructure('An ordered name or identity collection must be a list.');
        }
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidStructure('Name parts and identities must be strings.');
            }
        }
    }

    /**
     * @template TValue
     * @param array<array-key, TValue> $values Runtime SQL components
     * @throws InvalidStructure
     */
    public static function components(array $values): void
    {
        if (!array_is_list($values)) {
            throw new InvalidStructure('SQL components must form an ordered list.');
        }
        foreach ($values as $value) {
            if (!$value instanceof \SqlSemantics\Model\Sql\Tree && !$value instanceof \SqlSemantics\Model\Sql\Atom) {
                throw new InvalidStructure('A SQL structure contains only productions and terminals.');
            }
        }
    }
}
