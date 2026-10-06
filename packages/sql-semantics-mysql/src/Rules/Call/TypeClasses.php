<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeDescriptor;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Reads type facts as the result classes the built-in functions distinguish, and writes classes back as type facts.
 *
 * Rule: MYSQL-CALL-TYPE-CLASS-002. A known type has its class, a choice
 * every class of its alternatives, and anything else (NULL, a missing input,
 * an invalid type, a row) no class. Two classes combine as the numeric
 * functions convert their arguments: numbers to DOUBLE when one is
 * approximate, else to DECIMAL when one is DECIMAL, else to BIGINT; dates
 * and times to DATETIME; any other mixture to a string, binary when one is a
 * binary string, a geometry or BIT. The common type of values that share a
 * result position (COALESCE, IF, LEAD) is MYSQL-TYPE-AGGREGATION-001, not
 * this class rule. Terminates: one pass over finite lists.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/type-conversion.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TypeClasses
{
    /**
     * Answers the classes a known type or a choice can be.
     *
     * @return list<TypeClass>
     */
    public function classes(TypeFact $type): array
    {
        if ($type instanceof Known) {
            return $type->descriptor instanceof Tuple ? [] : [TypeClass::of($type->descriptor)];
        }
        $classes = [];
        if ($type instanceof Choice) {
            foreach ($type->alternatives as $alternative) {
                $classes[] = TypeClass::of($alternative);
            }
        }

        return $classes;
    }

    /**
     * Combines two classes.
     */
    public function merge(TypeClass $left, TypeClass $right): TypeClass
    {
        if ($left === $right) {
            return $left;
        }
        if ($left->numeric() && $right->numeric()) {
            return match (true) {
                $left === TypeClass::Floating || $right === TypeClass::Floating => TypeClass::Floating,
                $left === TypeClass::Decimal || $right === TypeClass::Decimal => TypeClass::Decimal,
                default => TypeClass::Integer,
            };
        }
        if ($left->temporalClass() && $right->temporalClass()) {
            return TypeClass::DateTime;
        }
        $binary = [TypeClass::Binary, TypeClass::Spatial, TypeClass::Bit];

        return in_array($left, $binary, true) || in_array($right, $binary, true) ? TypeClass::Binary : TypeClass::Character;
    }

    /**
     * Answers the type fact of the classes a result can have.
     *
     * @param list<TypeClass> $classes At least one class
     */
    public function fact(array $classes): TypeFact
    {
        $unique = [];
        foreach ($classes as $class) {
            if (!in_array($class, $unique, true)) {
                $unique[] = $class;
            }
        }
        if (count($unique) === 1) {
            return new Known($unique[0]->descriptor());
        }

        return new Choice(array_map(static fn (TypeClass $class): TypeDescriptor => $class->descriptor(), $unique));
    }
}
