<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\CreateFunction;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;

/**
 * Derives the facts of a routine definition.
 *
 * Rule: PG-ROUTINE-PARAMETERS-001. The parameter types, the result and the
 * options are derived in the statement environment; a default value sees no
 * relation and no parameter. In an SQL-standard body the input parameters
 * are one visible row, under the routine's own name as qualifier, in the
 * outermost scope: a column of a relation the body reads shadows a parameter
 * of the same name, and `routine.parameter` names the parameter explicitly.
 * The body is derived by PG-ROUTINE-BODY-001; the checks of
 * PG-ROUTINE-CHECK-001 are applied. A routine definition declares no
 * relation and returns no rows. Terminates: one pass over the definition.
 * Source: https://www.postgresql.org/docs/17/xfunc-sql.html#XFUNC-SQL-FUNCTION-ARGUMENTS,
 * https://www.postgresql.org/docs/17/sql-createfunction.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RoutineFacts
{
    /**
     * Derives every part of the definition.
     */
    public function create(CreateFunction $function, Derivation $derivation): void
    {
        $environment = $derivation->environment();
        $parameters = $derivation->relation($function->parameters, $environment);
        $function->returns?->deriveClause($derivation, $environment);
        foreach ($function->options as $option) {
            $option->deriveClause($derivation, $environment);
        }
        $checks = new RoutineChecks();
        $checks->options($function->options, $function->procedure, $derivation);
        $checks->body($function, $derivation);
        $checks->parameters($function, $derivation);
        if ($function->body !== null) {
            $body = new Environment($derivation->context, null, [new VisibleRelation($function->parameters, $parameters->shape, $function->name->last())]);
            $function->body->deriveClause($derivation, $body);
        }
    }
}
