<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Type;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Type\Identity;

/**
 * Writes only the parameters applicable to each built-in storage family.
 * @visibility SqlSemantics
 */
final class TypeParameters
{
    /**
     * @param list<Identity\Numeric\NumericParameter|null> $parameters
     */
    public static function numbers(array $parameters): Tree
    {
        $values = [];
        foreach ($parameters as $parameter) {
            if ($parameter !== null) {
                $values[] = Build::keyword($parameter->spelling);
            }
        }
        return $values === [] ? new Tree('type-parameters', []) : Build::parentheses(Build::separated($values));
    }

    /**
     * Writes integer display width and signedness.
     */
    public static function integer(Identity\Numeric\IntegerStorage $type): Tree
    {
        return new Tree('integer-type', [Build::keyword($type->base->value), self::numbers([$type->displayWidth]), ...($type->unsigned ? [Build::keyword('UNSIGNED')] : [])]);
    }

    /**
     * Writes precision before scale and then signedness.
     */
    public static function numeric(Identity\Numeric\NumericStorage $type): Tree
    {
        return new Tree('numeric-type', [Build::keyword($type->base->value), self::numbers([$type->precision, $type->scale]), ...($type->unsigned ? [Build::keyword('UNSIGNED')] : [])]);
    }

    /**
     * Writes string length and its encoding.
     */
    public static function string(Identity\StringStorage $type, Dialect $dialect): Tree
    {
        return new Tree('string-type', [Build::keyword($type->base->value), self::numbers([$type->length]), ...($type->characterSet === null ? [] : [Build::keyword('CHARACTER SET'), Build::identifier([$type->characterSet], $dialect)]), ...($type->binary ? [Build::keyword('BINARY')] : [])]);
    }

    /**
     * Places temporal precision before its time zone qualifier.
     */
    public static function temporal(Identity\TemporalStorage $type): Tree
    {
        return new Tree('temporal-type', [Build::keyword($type->base->value), self::numbers([$type->precision]), Build::keyword($type->timeZone->value)]);
    }

    /**
     * Places interval precision at the declared range's SECOND field.
     */
    public static function interval(Identity\IntervalStorage $type): Tree
    {
        return new Tree('interval-type', [Build::keyword('INTERVAL'), Build::keyword($type->fields->value), self::numbers([$type->precision])]);
    }
}
