<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * Merges two types of which at least one is temporal and neither is a string.
 *
 * Rule: MYSQL-TEMPORAL-MERGE-001, a part of MYSQL-TYPE-AGGREGATION-001. Two
 * values of one temporal kind keep it; DATE, TIME, TIMESTAMP and DATETIME
 * merge with each other to DATETIME, except that TIMESTAMP with TIMESTAMP
 * stays TIMESTAMP; YEAR with an integer is that integer; every other
 * mixture is VARCHAR. Terminates: no recursion.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/union.html,
 * https://dev.mysql.com/doc/refman/8.4/en/date-and-time-type-conversion.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TemporalMerge
{
    /**
     * Answers the common type of two types.
     */
    public function merge(TypeDescriptor $left, TypeDescriptor $right): TypeDescriptor
    {
        if ($left instanceof Temporal && $right instanceof Temporal) {
            if ($left->kind === $right->kind) {
                return new Temporal($left->kind);
            }

            return $left->kind === TemporalKind::Year || $right->kind === TemporalKind::Year ? new Character(CharacterKind::VarChar) : new Temporal(TemporalKind::DateTime);
        }
        $temporal = $left instanceof Temporal ? $left : $right;
        $other = $left instanceof Temporal ? $right : $left;
        if ($temporal instanceof Temporal && $temporal->kind === TemporalKind::Year && $other instanceof Integral) {
            return $other;
        }

        return new Character(CharacterKind::VarChar);
    }
}
