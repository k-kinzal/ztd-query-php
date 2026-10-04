<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvariantViolation;
use SqlSemantics\Platform\MySql\Rules\Expression\TypeAggregation;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The result facts of a built-in function from its result code and the facts of its arguments.
 *
 * Rule: MYSQL-CALL-RESULT-001. A result code is two letters: the type and
 * the NULL rule. Fixed types: I BIGINT, U BIGINT UNSIGNED, D DOUBLE, N
 * DECIMAL, T a character string, B a binary string, J JSON, V VECTOR, G a
 * geometry, A DATE, M TIME, E DATETIME. Derived types: S a string whose
 * character set comes from the arguments (binary when one is binary); H the
 * numeric type of the first argument and O that of all arguments (an exact
 * integer stays BIGINT, DECIMAL stays DECIMAL, anything else is DOUBLE); C
 * CEILING and FLOOR, whose DECIMAL argument gives BIGINT or DECIMAL by its
 * size; 1 and 2 the type of the first or second argument; + the aggregated
 * type of all arguments and Z of the arguments after the first
 * (MYSQL-TYPE-AGGREGATION-001); X ADDTIME and SUBTIME, which keep a
 * DATETIME or TIME first argument and give a string otherwise; K
 * STR_TO_DATE, a DATE, TIME or DATETIME by the format; W UNIX_TIMESTAMP of a
 * value, BIGINT or DECIMAL by its fractional seconds. NULL rules: P NULL
 * exactly when an argument can be NULL, N never NULL, Y possibly NULL, C
 * NULL only when every argument can be, F as the first argument, L as the
 * last argument, R NULL when an argument after the first can be. The codes
 * of the built-in functions are listed by MYSQL-NATIVE-FUNCTIONS-001 and the
 * call classes. Terminates: one pass over the arguments.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/type-conversion.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ResultTyping
{
    /**
     * The fixed result types by code letter.
     */
    private const FIXED = [
        'I' => TypeClass::Integer, 'U' => TypeClass::Unsigned, 'D' => TypeClass::Floating, 'N' => TypeClass::Decimal,
        'T' => TypeClass::Character, 'B' => TypeClass::Binary, 'J' => TypeClass::Json, 'V' => TypeClass::Vector,
        'G' => TypeClass::Spatial, 'A' => TypeClass::Date, 'M' => TypeClass::Time, 'E' => TypeClass::DateTime,
    ];

    /**
     * Answers the facts of a result.
     *
     * @param string $code The two-letter result code
     * @param list<ScalarFact> $arguments The facts of the arguments in order
     */
    public function fact(string $code, array $arguments): ScalarFact
    {
        Check::invariant(strlen($code) === 2, 'A result code has two letters: ' . $code);

        return new ScalarFact($this->type($code[0], $arguments), $this->nullability($code[1], $arguments));
    }

    /**
     * Answers the type a type code gives.
     *
     * @param list<ScalarFact> $arguments
     * @throws InvariantViolation When the code is not a type code
     */
    public function type(string $code, array $arguments): TypeFact
    {
        if (isset(self::FIXED[$code])) {
            return new Known(self::FIXED[$code]->descriptor());
        }
        $types = array_map(static fn (ScalarFact $fact): TypeFact => $fact->type, $arguments);
        $aggregation = new TypeAggregation();
        $classes = new TypeClasses();

        return match ($code) {
            'S' => $this->string($types),
            'H' => $this->numeric(array_slice($types, 0, 1), false),
            'O' => $this->numeric($types, false),
            'C' => $this->numeric(array_slice($types, 0, 1), true),
            '1' => $types[0] ?? new NullOnly(),
            '2' => $types[1] ?? new NullOnly(),
            '+' => $aggregation->aggregate($types),
            'Z' => $aggregation->aggregate(array_slice($types, 1)),
            'X' => $this->temporal($types[0] ?? new NullOnly()),
            'K' => $classes->fact([TypeClass::Date, TypeClass::Time, TypeClass::DateTime]),
            'W' => $classes->fact([TypeClass::Integer, TypeClass::Decimal]),
            default => throw new InvariantViolation('Unknown result type code: ' . $code),
        };
    }

    /**
     * Answers the type of a string result whose character set comes from the arguments.
     *
     * @param list<TypeFact> $types
     */
    public function string(array $types): TypeFact
    {
        $binary = false;
        $open = false;
        foreach ($types as $type) {
            $classes = (new TypeClasses())->classes($type);
            $binary = $binary || ($type instanceof Known && (in_array(TypeClass::Binary, $classes, true) || in_array(TypeClass::Spatial, $classes, true)));
            $open = $open || (!$type instanceof Known && !$type instanceof NullOnly) || ($type instanceof Known && $classes === []);
        }
        if ($binary) {
            return new Known(TypeClass::Binary->descriptor());
        }

        return (new TypeClasses())->fact($open ? [TypeClass::Character, TypeClass::Binary] : [TypeClass::Character]);
    }

    /**
     * Answers the numeric type of the arguments of a numeric function.
     *
     * An argument of an integer class, YEAR or BIT is an integer, DECIMAL is
     * DECIMAL (or an integer for CEILING and FLOOR when it fits), a date or
     * time is an integer or a DECIMAL by its fractional seconds, any other
     * argument is DOUBLE; several arguments combine as numbers do.
     *
     * @param list<TypeFact> $types
     * @param bool $integral Whether a DECIMAL argument may give an integer, as for CEILING and FLOOR
     */
    public function numeric(array $types, bool $integral): TypeFact
    {
        $aggregation = new TypeClasses();
        $results = [null];
        foreach ($types as $type) {
            if ($type instanceof NullOnly) {
                continue;
            }
            $next = [];
            foreach ($this->numbers($aggregation->classes($type), $integral) as $class) {
                foreach ($results as $result) {
                    $next[] = $result === null ? $class : $aggregation->merge($result, $class);
                }
            }
            $results = $next;
        }
        $classes = array_values(array_filter($results, static fn (?TypeClass $class): bool => $class !== null));

        return $classes === [] ? new NullOnly() : $aggregation->fact($classes);
    }

    /**
     * Answers the numeric classes an argument of the given classes is converted to; no class is any number.
     *
     * @param list<TypeClass> $classes
     * @return list<TypeClass>
     */
    public function numbers(array $classes, bool $integral): array
    {
        if ($classes === []) {
            return [TypeClass::Integer, TypeClass::Decimal, TypeClass::Floating];
        }
        $numbers = [];
        foreach ($classes as $class) {
            array_push($numbers, ...match (true) {
                in_array($class, [TypeClass::Integer, TypeClass::Unsigned, TypeClass::Year, TypeClass::Bit], true) => [TypeClass::Integer],
                $class === TypeClass::Decimal => $integral ? [TypeClass::Integer, TypeClass::Decimal] : [TypeClass::Decimal],
                $class->temporalClass() => [TypeClass::Integer, TypeClass::Decimal],
                default => [TypeClass::Floating],
            });
        }

        return $numbers;
    }

    /**
     * Answers the type of ADDTIME and SUBTIME from the type of the first argument.
     */
    public function temporal(TypeFact $first): TypeFact
    {
        if ($first instanceof NullOnly) {
            return $first;
        }
        $classes = (new TypeClasses())->classes($first);
        if ($classes === []) {
            return (new TypeClasses())->fact([TypeClass::DateTime, TypeClass::Time, TypeClass::Character]);
        }
        $results = [];
        foreach ($classes as $class) {
            $results[] = match ($class) {
                TypeClass::DateTime, TypeClass::Date => TypeClass::DateTime,
                TypeClass::Time => TypeClass::Time,
                TypeClass::Integer, TypeClass::Unsigned, TypeClass::Decimal, TypeClass::Floating, TypeClass::Character, TypeClass::Binary,
                TypeClass::Year, TypeClass::Json, TypeClass::Spatial, TypeClass::Bit, TypeClass::Vector => TypeClass::Character,
            };
        }

        return (new TypeClasses())->fact($results);
    }

    /**
     * Answers the type of a date plus or minus an interval: DATE_ADD, DATE_SUB, ADDDATE, SUBDATE and TIMESTAMPADD.
     *
     * A DATE stays a DATE for a unit of days or larger and becomes a DATETIME
     * otherwise; a TIME stays a TIME for a unit of hours or smaller; a
     * DATETIME or TIMESTAMP gives a DATETIME; any other argument gives a
     * string (https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_date-add).
     */
    public function dateArithmetic(TypeFact $date, IntervalUnit $unit): TypeFact
    {
        if ($date instanceof NullOnly) {
            return $date;
        }
        $classes = (new TypeClasses())->classes($date);
        if ($classes === []) {
            return (new TypeClasses())->fact([TypeClass::Date, TypeClass::Time, TypeClass::DateTime, TypeClass::Character]);
        }
        $dateOnly = in_array($unit, [IntervalUnit::Day, IntervalUnit::Week, IntervalUnit::Month, IntervalUnit::Quarter, IntervalUnit::Year, IntervalUnit::YearMonth], true);
        $timeOnly = in_array($unit, [IntervalUnit::Microsecond, IntervalUnit::Second, IntervalUnit::Minute, IntervalUnit::Hour, IntervalUnit::SecondMicrosecond, IntervalUnit::MinuteMicrosecond, IntervalUnit::MinuteSecond, IntervalUnit::HourMicrosecond, IntervalUnit::HourSecond, IntervalUnit::HourMinute], true);
        $results = [];
        foreach ($classes as $class) {
            $results[] = match ($class) {
                TypeClass::Date => $dateOnly ? TypeClass::Date : TypeClass::DateTime,
                TypeClass::Time => $timeOnly ? TypeClass::Time : TypeClass::DateTime,
                TypeClass::DateTime => TypeClass::DateTime,
                TypeClass::Integer, TypeClass::Unsigned, TypeClass::Decimal, TypeClass::Floating, TypeClass::Character, TypeClass::Binary,
                TypeClass::Year, TypeClass::Json, TypeClass::Spatial, TypeClass::Bit, TypeClass::Vector => TypeClass::Character,
            };
        }

        return (new TypeClasses())->fact($results);
    }

    /**
     * Answers the NULL fact a NULL rule gives.
     *
     * @param list<ScalarFact> $arguments
     * @throws InvariantViolation When the code is not a NULL rule
     */
    public function nullability(string $rule, array $arguments): Nullability
    {
        $facts = array_map(static fn (ScalarFact $fact): Nullability => $fact->nullability, $arguments);

        return match ($rule) {
            'P' => $this->propagate($facts),
            'N' => Nullability::NotNull,
            'Y' => Nullability::Nullable,
            'C' => $this->coalesce($facts),
            'F' => $facts[0] ?? Nullability::NotNull,
            'L' => $facts === [] ? Nullability::NotNull : $facts[count($facts) - 1],
            'R' => $this->propagate(array_slice($facts, 1)),
            default => throw new InvariantViolation('Unknown NULL rule code: ' . $rule),
        };
    }

    /**
     * Combines NULL facts when NULL in any operand gives NULL.
     *
     * @param list<Nullability> $facts
     */
    public function propagate(array $facts): Nullability
    {
        $result = Nullability::NotNull;
        foreach ($facts as $fact) {
            $result = $result->propagate($fact);
        }

        return $result;
    }

    /**
     * Combines NULL facts when the result is NULL only if every operand is.
     *
     * @param list<Nullability> $facts
     */
    public function coalesce(array $facts): Nullability
    {
        if ($facts === [] || in_array(Nullability::NotNull, $facts, true)) {
            return Nullability::NotNull;
        }

        return in_array(Nullability::Dependent, $facts, true) ? Nullability::Dependent : Nullability::Nullable;
    }
}
