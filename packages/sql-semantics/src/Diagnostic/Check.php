<?php

declare(strict_types=1);

namespace SqlSemantics\Diagnostic;

/**
 * Always-on boundary checks; they do not depend on PHP assertion settings.
 *
 * @visibility SqlSemantics
 */
final class Check
{
    /**
     * Rejects a construction input outside the documented constructor domain.
     *
     * @phpstan-assert true $condition
     * @throws InvalidConstruction When the condition does not hold
     */
    public static function input(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new InvalidConstruction($message);
        }
    }

    /**
     * Narrows a constructor argument to an ordered list of one class, rejecting anything else.
     *
     * PHP does not check array element types, so every list a constructor
     * accepts passes through here whatever its PHPDoc promises.
     *
     * @template T of object
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $items
     * @param class-string<T> $class
     * @return ($minimum is positive-int ? non-empty-list<T> : list<T>)
     * @throws InvalidConstruction When the argument is not a list of that class, or is shorter than the minimum
     */
    public static function listOf(array $items, string $class, string $message, int $minimum = 0): array
    {
        $list = [];
        foreach ($items as $item) {
            if (!$item instanceof $class) {
                throw new InvalidConstruction($message);
            }
            $list[] = $item;
        }
        if (!array_is_list($items) || count($list) < $minimum) {
            throw new InvalidConstruction($message);
        }

        return $list;
    }

    /**
     * Narrows an ordered list of pairs to two specified object types.
     *
     * @template TLeft of object
     * @template TRight of object
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $items
     * @param class-string<TLeft> $left
     * @param class-string<TRight> $right
     * @return list<array{TLeft, TRight}>
     * @throws InvalidConstruction When a pair or its ordered list is malformed
     */
    public static function pairs(array $items, string $left, string $right, string $message): array
    {
        self::input(array_is_list($items), $message);
        $pairs = [];
        foreach ($items as $pair) {
            self::input(is_array($pair) && array_is_list($pair) && count($pair) === 2 && $pair[0] instanceof $left && $pair[1] instanceof $right, $message);
            $pairs[] = [$pair[0], $pair[1]];
        }

        return $pairs;
    }

    /**
     * Rejects a candidate that violates an internal integrity or correspondence condition.
     *
     * @phpstan-assert true $condition
     * @throws InvariantViolation When the condition does not hold
     */
    public static function invariant(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new InvariantViolation($message);
        }
    }
}
