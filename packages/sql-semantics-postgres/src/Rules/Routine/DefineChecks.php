<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Define;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;

/**
 * Reports the attributes CREATE AGGREGATE and CREATE OPERATOR require.
 *
 * Rule: PG-DEFINE-CHECK-001. Attribute names are compared without regard to
 * case. An aggregate needs a state type (`stype` or `stype1`) and a state
 * transition function (`sfunc` or `sfunc1`); an operator needs a function
 * (`function` or `procedure`) and, as postfix operators no longer exist, a
 * right argument type. Other attributes are passed on as written and are not
 * checked. Terminates: one pass over the attributes.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html, https://www.postgresql.org/docs/17/sql-createoperator.html,
 * `DefineAggregate` in `src/backend/commands/aggregatecmds.c` and `DefineOperator` in `src/backend/commands/operatorcmds.c` of PostgreSQL 17.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DefineChecks
{
    /**
     * Reports the required attributes the definition lacks.
     */
    public function derive(Define $define, Derivation $derivation): void
    {
        $names = [];
        foreach ($define->definition ?? [] as $attribute) {
            $names[] = strtolower($attribute->name->value);
        }
        $checks = match ($define->kind) {
            ObjectKind::Aggregate => [
                [['stype', 'stype1'], new RoutineProblem(RoutineProblemKind::MissingTransition, 'stype')],
                [['sfunc', 'sfunc1'], new RoutineProblem(RoutineProblemKind::MissingTransition, 'sfunc')],
            ],
            ObjectKind::Operator => [
                [['rightarg'], new RoutineProblem(RoutineProblemKind::MissingRightArgument)],
                [['function', 'procedure'], new RoutineProblem(RoutineProblemKind::MissingOperatorFunction)],
            ],
            default => [],
        };
        foreach ($checks as [$alternatives, $problem]) {
            if (array_intersect($alternatives, $names) === []) {
                $derivation->report($problem);
            }
        }
    }
}
