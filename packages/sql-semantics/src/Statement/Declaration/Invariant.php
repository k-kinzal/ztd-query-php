<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use InvalidArgumentException;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\ImmutableGraph;

/**
 * Construction invariants shared by immutable declaration values.
 * @visibility SqlSemantics
 */
final class Invariant
{
    /**
     * Rejects invalid public construction even when PHP assertions are disabled.
     * @throws InvalidArgumentException When a state invariant does not hold
     */
    public static function ensure(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * @throws InvalidArgumentException When an SQL value graph is mutable
     * Requires independent immutable SQL values, never retained parser nodes.
     */
    public static function elements(?Element ...$elements): void
    {
        foreach ($elements as $element) {
            self::ensure($element === null || (new ImmutableGraph())->containsOnlyImmutableValues($element), 'Schema SQL fields must be deeply immutable semantic values.');
        }
    }

    /**
     * @throws InvalidArgumentException When the list or its members have the wrong shape
     * Checks the runtime boundary of PHP arrays, whose item types are not enforced by PHP.
     * @template T
     * @param array<array-key, T> $members
     * @param class-string $class
     */
    public static function members(array $members, string $class): void
    {
        self::ensure(array_is_list($members), 'Schema members must be an ordered list.');
        foreach ($members as $member) {
            self::ensure($member instanceof $class, 'A schema member has the wrong type.');
        }
    }

    /**
     * Requires a name path rather than an empty list.
     * @param list<string> $names
     * @throws InvalidArgumentException When there are no name parts
     */
    public static function nonEmptyNames(array $names): void
    {
        self::names($names);
        self::ensure($names !== [], 'A reference needs a name.');
    }

    /**
     * @throws InvalidArgumentException When a name has the wrong type or the list has the wrong shape
     * Requires an ordered list of names; dialects may allow empty quoted names.
     * @template T
     * @param array<array-key, T> $names
     */
    public static function names(array $names): void
    {
        self::ensure(array_is_list($names), 'Names must be an ordered list.');
        foreach ($names as $name) {
            self::ensure(is_string($name), 'A name must be a string.');
        }
    }
}
