<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments;
use SqlSemantics\Statement\Type\Known;

/**
 * Reports aggregate argument lists the server rejects while parsing.
 *
 * Rule: PG-AGGREGATE-ARGS-001. An aggregate argument is an input: OUT, INOUT
 * and IN OUT are reported. When the direct arguments of an ordered-set
 * aggregate end with a VARIADIC argument, the aggregated arguments must be
 * exactly one VARIADIC argument of the same type; a different count or mode,
 * or two known different types, are reported. Terminates: one pass.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, `aggr_arg` and
 * `makeOrderedSetArgs` in `src/backend/parser/gram.y` of PostgreSQL 17. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class AggregateChecks
{
    /**
     * Reports the problems of an aggregate argument list.
     */
    public function arguments(AggregateArguments $arguments, Derivation $derivation): void
    {
        foreach ([...$arguments->direct, ...($arguments->ordered ?? [])] as $argument) {
            if ($argument->output()) {
                $derivation->report(new RoutineProblem(RoutineProblemKind::AggregateOutput));
            }
        }
        $last = $arguments->direct[count($arguments->direct) - 1] ?? null;
        if ($arguments->ordered === null || $last?->mode !== ParameterMode::Variadic) {
            return;
        }
        $first = $arguments->ordered[0];
        $left = $last->type->typeFact($derivation->context);
        $right = $first->type->typeFact($derivation->context);
        $differ = $left instanceof Known && $right instanceof Known && $left->descriptor->name() !== $right->descriptor->name();
        if (count($arguments->ordered) !== 1 || $first->mode !== ParameterMode::Variadic || $differ) {
            $derivation->report(new RoutineProblem(RoutineProblemKind::OrderedSetVariadic));
        }
    }
}
