<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The result of an aggregate function with one argument, COUNT(*) or COUNT(DISTINCT ...).
 *
 * Rule: MYSQL-AGGREGATE-RESULT-001. COUNT is a BIGINT and never NULL. SUM
 * and AVG of an exact number (an integer, DECIMAL, YEAR or BIT) are DECIMAL,
 * of anything else DOUBLE. MIN and MAX have the type of their argument. The
 * BIT_AND, BIT_OR and BIT_XOR of a binary string are a binary string, of
 * anything else a BIGINT UNSIGNED; they are never NULL. STD, VARIANCE,
 * STDDEV_SAMP and VAR_SAMP are DOUBLE, JSON_ARRAYAGG is JSON, ST_COLLECT a
 * geometry. Every other aggregate is NULL for a group without non-NULL
 * values. DISTINCT in a windowed aggregate is not supported by the server
 * (ER_NOT_SUPPORTED_YET). Terminates: a fixed number of tests on one call.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-functions.html#function_bit-and,
 * https://dev.mysql.com/doc/refman/8.4/en/window-function-restrictions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AggregateResults
{
    /**
     * Answers the facts of an aggregate call and reports what the server rejects.
     *
     * @param list<ScalarFact> $arguments
     */
    public function aggregate(Aggregate $call, array $arguments, Derivation $derivation): ScalarFact
    {
        if ($call->distinct && $call->over !== null) {
            $derivation->report(new UnsupportedWindowing(WindowingLimit::DistinctAggregate));
        }
        $type = $arguments[0]->type ?? new NullOnly();
        $aggregation = new TypeClasses();

        return match ($call->function) {
            AggregateFunction::Count => new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull),
            AggregateFunction::Sum, AggregateFunction::Average => new ScalarFact($this->sum($type), Nullability::Nullable),
            AggregateFunction::Minimum, AggregateFunction::Maximum => new ScalarFact($type, Nullability::Nullable),
            AggregateFunction::BitAnd, AggregateFunction::BitOr, AggregateFunction::BitXor => new ScalarFact($this->bits($type), Nullability::NotNull),
            AggregateFunction::StandardDeviation, AggregateFunction::Variance, AggregateFunction::SampleStandardDeviation,
            AggregateFunction::SampleVariance => new ScalarFact(new Known(TypeClass::Floating->descriptor()), Nullability::Nullable),
            AggregateFunction::JsonArray => new ScalarFact(new Known(TypeClass::Json->descriptor()), Nullability::Nullable),
            AggregateFunction::Collect => new ScalarFact($aggregation->fact([TypeClass::Spatial]), Nullability::Nullable),
        };
    }

    /**
     * Answers the type of SUM and AVG.
     */
    public function sum(TypeFact $argument): TypeFact
    {
        $classes = (new TypeClasses())->classes($argument);
        if ($classes === []) {
            return (new TypeClasses())->fact([TypeClass::Decimal, TypeClass::Floating]);
        }
        $results = [];
        foreach ($classes as $class) {
            $results[] = in_array($class, [TypeClass::Integer, TypeClass::Unsigned, TypeClass::Decimal, TypeClass::Year, TypeClass::Bit], true) ? TypeClass::Decimal : TypeClass::Floating;
        }

        return (new TypeClasses())->fact($results);
    }

    /**
     * Answers the type of BIT_AND, BIT_OR and BIT_XOR.
     */
    public function bits(TypeFact $argument): TypeFact
    {
        $classes = (new TypeClasses())->classes($argument);
        if ($argument instanceof NullOnly) {
            return new Known(TypeClass::Unsigned->descriptor());
        }
        if ($classes === []) {
            return (new TypeClasses())->fact([TypeClass::Unsigned, TypeClass::Binary]);
        }
        $results = [];
        foreach ($classes as $class) {
            $results[] = $class === TypeClass::Binary ? TypeClass::Binary : TypeClass::Unsigned;
        }

        return (new TypeClasses())->fact($results);
    }
}
