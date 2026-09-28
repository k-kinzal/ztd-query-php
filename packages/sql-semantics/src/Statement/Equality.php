<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use ReflectionObject;
use UnitEnum;

/**
 * Compares SQL values by structure: the same forms, the same spellings, the same options, and the same comments.
 *
 * Two values are equal when they are of the same class and every field is
 * equal: a child value by structure, a spelling byte for byte, an option by
 * identity, and the comments position by position. No SQL is written to
 * compare them, so two values that happen to write the same text but differ
 * in structure, such as `a - -1` read two ways, are not equal.
 *
 * @visibility public
 * @example Values read from the same SQL are equal
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     \SqlSemantics\Statement\Equality::same($semantics->analyze('SELECT a')->command, $semantics->analyze('select  a')->command) // => true
 * @example Finding a command inside the envelope of the grammar's start form
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $command = $semantics->analyze('SELECT 1')->command;
 *     \SqlSemantics\Statement\Equality::enclosed($command, $command) === $command // => true
 * @example A different spelling is a different value
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     \SqlSemantics\Statement\Equality::same($semantics->analyze('SELECT a')->command, $semantics->analyze('SELECT A')->command) // => false
 */
final class Equality
{
    /**
     * Answers whether two values have the same structure, spellings, options, and comments.
     */
    public static function same(Element $left, Element $right): bool
    {
        if ($left === $right) {
            return true;
        }
        if ($left::class !== $right::class || $left instanceof UnitEnum) {
            return false;
        }
        foreach ((new ReflectionObject($left))->getProperties() as $property) {
            $a = $property->getValue($left);
            $b = $property->getValue($right);
            $equal = match (true) {
                $a instanceof Element && $b instanceof Element => self::same($a, $b),
                $a instanceof Comments && $b instanceof Comments => $a->equals($b),
                default => $a === $b,
            };
            if (!$equal) {
                return false;
            }
        }

        return true;
    }

    /**
     * Finds the value equal to another inside a value that encloses it in forms that write nothing more, or answers null.
     *
     * The grammar reads a statement into its start form, which ends it with
     * an optional terminator; a command built without that envelope writes
     * the same SQL and is the value the envelope holds.
     */
    public static function enclosed(Element $outer, Element $inner): ?Element
    {
        while (!self::same($outer, $inner)) {
            $written = array_values(array_filter($outer->children(), static fn (Element $child): bool => Writer::render($child) !== ''));
            if (count($written) !== 1 || Writer::render($outer) !== Writer::render($written[0])) {
                return null;
            }
            $outer = $written[0];
        }

        return $outer;
    }
}
