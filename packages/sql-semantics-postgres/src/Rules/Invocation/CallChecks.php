<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Invocation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Argument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\ArgumentProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\ArgumentProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\CallMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\CallMisuseKind;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Reports the calls every routine, or the resolved kind of function, rejects.
 *
 * Rule: PG-CALL-CHECKS-001. Whatever the routine, a positional argument
 * cannot follow a named one and a parameter name cannot be used twice. A
 * plain function takes no `*`, DISTINCT, ordering, WITHIN GROUP, FILTER or
 * OVER. An aggregate that is not an ordered-set aggregate takes no WITHIN
 * GROUP, and a parameterless aggregate is called with `*`; with OVER, it
 * takes no DISTINCT and no ordering. A window function requires OVER and
 * takes no WITHIN GROUP, DISTINCT, ordering or FILTER. Parameter names are
 * compared exactly, as decoded. Source: `ParseFuncOrColumn` in
 * `src/backend/parser/parse_func.c` of PostgreSQL 17,
 * https://www.postgresql.org/docs/17/sql-syntax-calling-funcs.html,
 * https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-AGGREGATES. Termination: one pass over the arguments. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CallChecks
{
    /**
     * Reports the argument naming mistakes of a call.
     *
     * @param list<Argument> $arguments
     */
    public function arguments(Derivation $derivation, array $arguments): void
    {
        $names = [];
        $last = null;
        foreach ($arguments as $argument) {
            $name = $argument->name();
            if ($name === null) {
                if ($last !== null) {
                    $derivation->report(new ArgumentProblem(ArgumentProblemKind::PositionalAfterNamed, $last));
                }
                continue;
            }
            if (in_array($name->value, $names, true)) {
                $derivation->report(new ArgumentProblem(ArgumentProblemKind::RepeatedName, $name));
            }
            $names[] = $name->value;
            $last = $name;
        }
    }

    /**
     * Reports the clauses the kind of the resolved function does not accept and answers the first, or null when there is none.
     *
     * @param string $kind The kind of the function: s plain, a aggregate, w window
     */
    public function misuse(Derivation $derivation, FunctionCall $call, string $kind): ?CallMisuse
    {
        $inWindow = $call->over !== null;
        $ordered = $call->order !== [] && !$call->withinGroup;
        $found = match ($kind) {
            's' => [
                [CallMisuseKind::StarOnPlainFunction, $call->star], [CallMisuseKind::DistinctOnPlainFunction, $call->distinct],
                [CallMisuseKind::WithinGroupOnPlainFunction, $call->withinGroup], [CallMisuseKind::OrderOnPlainFunction, $ordered],
                [CallMisuseKind::FilterOnPlainFunction, $call->filter !== null], [CallMisuseKind::OverOnPlainFunction, $inWindow],
            ],
            'a' => [
                [CallMisuseKind::WithinGroupOnPlainAggregate, $call->withinGroup], [CallMisuseKind::ParameterlessWithoutStar, $call->arguments === [] && !$call->star && !$call->withinGroup],
                [CallMisuseKind::DistinctInWindow, $inWindow && $call->distinct], [CallMisuseKind::OrderInWindow, $inWindow && $ordered],
            ],
            default => [
                [CallMisuseKind::WindowWithoutOver, !$inWindow], [CallMisuseKind::WithinGroupOnWindow, $call->withinGroup],
                [CallMisuseKind::DistinctInWindow, $call->distinct], [CallMisuseKind::OrderInWindow, $ordered], [CallMisuseKind::FilterOnWindow, $call->filter !== null],
            ],
        };
        $first = null;
        foreach ($found as [$case, $applies]) {
            if ($applies) {
                $misuse = new CallMisuse($case, new Name($call->name->last()->value));
                $derivation->report($misuse);
                $first ??= $misuse;
            }
        }

        return $first;
    }
}
