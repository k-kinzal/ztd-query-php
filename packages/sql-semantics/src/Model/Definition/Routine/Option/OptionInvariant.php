<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Rules shared by the attribute lists of routine definitions and alterations.
 * @visibility SqlSemantics
 */
final class OptionInvariant
{
    /**
     * An execution estimate is a positive finite PostgreSQL number.
     * @throws InvalidStructure
     */
    public static function estimate(Literal $value): void
    {
        if ($value->type->dialect !== Dialect::PostgreSql || !is_numeric($value->text) || !is_finite((float) $value->text) || (float) $value->text <= 0.0) {
            throw new InvalidStructure('COST and ROWS are positive finite numbers.');
        }
    }

    /**
     * Each attribute other than SET and RESET is given at most once; a procedure takes only SECURITY, SET, and RESET.
     * @param list<RoutineOption|RoutineSecurity> $options
     * @throws InvalidStructure
     */
    public static function options(array $options, bool $procedure): void
    {
        Collections::alternatives($options, [RoutineOption::class, RoutineSecurity::class]);
        $seen = [];
        foreach ($options as $option) {
            if ($option instanceof RoutineSetting || $option instanceof RoutineReset) {
                continue;
            }
            if ($procedure && !$option instanceof RoutineSecurity) {
                throw new InvalidStructure('A procedure accepts only SECURITY, SET, and RESET attributes.');
            }
            $key = $option::class;
            if (isset($seen[$key])) {
                throw new InvalidStructure('A routine attribute is given at most once.');
            }
            $seen[$key] = true;
        }
    }

    /**
     * ROWS estimates the rows of a set-returning function only.
     * @param list<RoutineOption|RoutineSecurity> $options
     * @throws InvalidStructure
     */
    public static function rows(array $options, bool $returnsSet): void
    {
        foreach ($options as $option) {
            if ($option instanceof ResultRows && !$returnsSet) {
                throw new InvalidStructure('ROWS applies to set-returning functions.');
            }
        }
    }
}
