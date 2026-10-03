<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\ImmutableGraph;

/**
 * Construction invariants shared by immutable declaration values.
 * @visibility SqlSemantics
 */
final class Invariant
{
    /**
     * States a construction invariant of the represented semantic value.
     */
    public static function ensure(bool $condition, string $message): void
    {
        assert($condition, $message);
    }

    /**
     * Requires independent immutable SQL values, never retained parser nodes.
     */
    public static function elements(?Element ...$elements): void
    {
        foreach ($elements as $element) {
            self::ensure($element === null || (new ImmutableGraph())->containsOnlyImmutableValues($element), 'Schema SQL fields must be deeply immutable semantic values.');
        }
    }

    /**
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
     * Asserts the native array boundary for a collection of concrete value variants.
     * @template T
     * @param array<array-key, T> $members
     * @param class-string $first
     * @param class-string $second
     */
    public static function alternatives(array $members, string $first, string $second): void
    {
        assert(array_is_list($members), 'Members must be an ordered list.');
        foreach ($members as $member) {
            assert($member instanceof $first || $member instanceof $second, 'A member has the wrong value variant.');
        }
    }

    /**
     * Requires a name path rather than an empty list.
     * @param list<string> $names
     */
    public static function nonEmptyNames(array $names): void
    {
        self::names($names);
        self::ensure($names !== [], 'A reference needs a name.');
    }

    /**
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
