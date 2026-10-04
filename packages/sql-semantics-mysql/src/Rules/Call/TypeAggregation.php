<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Combines the types of several operands into the one type of a result that can be any of them.
 *
 * Rule: MYSQL-CALL-TYPE-AGGREGATION-001. COALESCE, IFNULL, IF, GREATEST,
 * LEAST, LEAD and LAG with a default return a value of one of their operands
 * in a type the server derives from all of them (Item_type_holder): operands
 * of one class keep it, and a single declared type is kept exactly; numbers
 * combine to DOUBLE when one is approximate, else to DECIMAL when one is
 * DECIMAL, else to BIGINT; dates and times combine to DATETIME; any other
 * mixture is a string, binary when one operand is a binary string, a
 * geometry or BIT. A bare NULL operand does not take part; only NULLs give
 * the type of NULL. An invalid operand makes the result invalid; an operand
 * that depends on a missing input makes the result depend on it. An operand
 * with a choice of types gives every combination. A row operand, which the
 * server rejects (MYSQL-CALL-ARGUMENT-001), gives its row type. Terminates: one pass over
 * the operands.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html#function_ifnull,
 * https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#function_coalesce,
 * https://dev.mysql.com/doc/refman/8.4/en/type-conversion.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TypeAggregation
{
    /**
     * Answers the combined type of the operands.
     *
     * @param list<TypeFact> $types
     */
    public function aggregate(array $types): TypeFact
    {
        $missing = [];
        $known = [];
        foreach ($types as $type) {
            if ($type instanceof Invalid) {
                return $type;
            }
            if ($type instanceof Dependent) {
                array_push($missing, ...$type->missing);
            } elseif (!$type instanceof NullOnly) {
                $known[] = $type;
            }
        }
        if ($missing !== []) {
            return new Dependent($missing);
        }
        if ($known === []) {
            return new NullOnly();
        }

        return $this->combine($known);
    }

    /**
     * Combines operands of known types or choices of known types.
     *
     * @param non-empty-list<TypeFact> $types
     */
    public function combine(array $types): TypeFact
    {
        $first = $types[0];
        if ($first instanceof Known && $this->same($types, $first)) {
            return $first;
        }
        foreach ($types as $type) {
            if ($type instanceof Known && $type->descriptor instanceof Tuple) {
                return $type;
            }
        }
        $results = [null];
        foreach ($types as $type) {
            $next = [];
            foreach ($this->classes($type) as $class) {
                foreach ($results as $result) {
                    $next[] = $result === null ? $class : $this->merge($result, $class);
                }
            }
            $results = $next;
        }

        return $this->fact(array_values(array_filter($results, static fn (?TypeClass $class): bool => $class !== null)));
    }

    /**
     * Tells whether every operand has the same declared type as the first one.
     *
     * @param list<TypeFact> $types
     */
    public function same(array $types, Known $first): bool
    {
        foreach ($types as $type) {
            if (!$type instanceof Known || $type->descriptor->name() !== $first->descriptor->name() || $this->classes($type) !== $this->classes($first) || $this->classes($type) === []) {
                return false;
            }
        }

        return true;
    }

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

        return new Choice(array_map(static fn (TypeClass $class): \SqlSemantics\Statement\Type\TypeDescriptor => $class->descriptor(), $unique));
    }
}
