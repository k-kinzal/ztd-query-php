<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Typing;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerValued;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\IntervalSpan;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ModifierProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Applies type modifiers to a catalog type.
 *
 * Rule: PG-TYPE-MODIFIER-001. Scope: the modifier lists of `Numeric`,
 * `Bit`, `Character`, `ConstDatetime`, `ConstInterval` and `GenericType`.
 * Facts: no modifier gives the catalog type; `numeric` takes a precision of
 * 1 to 1000 and an optional scale of -1000 to 1000; `character` and
 * `character varying` take a length of 1 to 10485760; `bit` and `bit varying`
 * a length of 1 to 83886080; the time, timestamp and interval types a
 * precision that is not negative and is reduced to 6 above 6. Diagnostics: a
 * modifier that is not an integer constant, a wrong count, a value out of
 * range, and modifiers on any other type are `ModifierProblem`. Minimum
 * precision: `Known` for every list of integer constants in range.
 * Source: https://www.postgresql.org/docs/17/datatype-numeric.html#DATATYPE-NUMERIC-DECIMAL,
 * https://www.postgresql.org/docs/17/datatype-character.html, https://www.postgresql.org/docs/17/datatype-bit.html,
 * https://www.postgresql.org/docs/17/datatype-datetime.html. Termination: one pass over the modifiers.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Modifiers
{
    /**
     * The types that take a length, with the largest length.
     */
    private const LENGTHS = ['bpchar' => 10485760, 'varchar' => 10485760, 'bit' => 83886080, 'varbit' => 83886080];

    /**
     * The types that take a fractional-second precision.
     */
    private const PRECISIONS = ['time', 'timetz', 'timestamp', 'timestamptz', 'interval'];

    /**
     * Answers the type a catalog type becomes under modifier expressions.
     *
     * @param list<Scalar> $modifiers
     */
    public function apply(Builtin $base, array $modifiers): TypeFact
    {
        $values = [];
        foreach ($modifiers as $modifier) {
            $value = $modifier instanceof IntegerValued ? $modifier->integerValue() : null;
            if ($value === null) {
                return new Invalid(new ModifierProblem($base->name()));
            }
            $values[] = $value;
        }

        return $this->constrained($base, $values);
    }

    /**
     * Answers the type a catalog type becomes under integer modifiers.
     *
     * @param list<string> $values Canonical decimal integers with an optional minus sign
     */
    public function constrained(Builtin $base, array $values): TypeFact
    {
        if ($values === []) {
            return new Known($base);
        }
        $numbers = [];
        foreach ($values as $value) {
            if (strlen(ltrim($value, '-')) > 9) {
                return new Invalid(new ModifierProblem($base->name()));
            }
            $numbers[] = (int) $value;
        }
        $first = $numbers[0];
        if ($base === Builtin::Numeric) {
            $valid = count($numbers) <= 2 && $first >= 1 && $first <= 1000 && abs($numbers[1] ?? 0) <= 1000;

            return $valid ? new Known(new Parameterized($base, ...$numbers)) : new Invalid(new ModifierProblem($base->name()));
        }
        if (count($numbers) === 1 && isset(self::LENGTHS[$base->value]) && $first >= 1 && $first <= self::LENGTHS[$base->value]) {
            return new Known(new Parameterized($base, ...$numbers));
        }
        if (count($numbers) === 1 && in_array($base->value, self::PRECISIONS, true) && $first >= 0) {
            return new Known($base === Builtin::Interval ? new IntervalSpan(null, min(6, $first)) : new Parameterized($base, min(6, $first)));
        }

        return new Invalid(new ModifierProblem($base->name()));
    }
}
