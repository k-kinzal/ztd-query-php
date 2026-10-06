<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;

/**
 * Reports the clauses of a call that the kind of the called built-in function does not take.
 *
 * Rule: SQLITE-CALL-CLAUSE-001. A window-only function must be called with
 * OVER; a scalar function may not be called with OVER, FILTER or an argument
 * ordering; a call with OVER takes no DISTINCT and no argument ordering; a
 * window-only function takes no FILTER; a DISTINCT aggregate takes exactly
 * one argument. The kind comes from SQLITE-FUNCTION-RESULT-001 for the
 * written number of arguments; a name outside the table or a wrong number
 * of arguments is checked elsewhere and nothing is reported here.
 * Terminates: a fixed number of tests on one call.
 * Source: https://sqlite.org/lang_aggfunc.html, https://sqlite.org/windowfunctions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CallChecks
{
    /**
     * Reports every rule the call breaks.
     */
    public function report(FunctionCall $call, Derivation $derivation): void
    {
        $row = (new Functions())->row($call->name->value, count($call->arguments));
        if ($row === null) {
            return;
        }
        foreach ($this->broken($call, $row[4]) as $rule) {
            $derivation->report(new CallMisuse($rule, $call->name));
        }
    }

    /**
     * Answers the rules a call of a function of the kind breaks, in a fixed order.
     *
     * @param string $kind The kind code: s scalar, a aggregate, w window only
     * @return list<CallMisuseRule>
     */
    public function broken(FunctionCall $call, string $kind): array
    {
        $windowed = $call->over !== null;
        $distinct = $call->quantifier === SetQuantifier::Distinct;
        $tests = [
            [CallMisuseRule::WindowWithoutOver, $kind === 'w' && !$windowed],
            [CallMisuseRule::ScalarAsWindow, $kind === 's' && $windowed],
            [CallMisuseRule::FilterWithoutAggregate, $kind === 's' && $call->filter !== null],
            [CallMisuseRule::FilterOnWindowOnly, $kind === 'w' && $windowed && $call->filter !== null],
            [CallMisuseRule::OrderByWithoutAggregate, ($kind === 's' || $windowed) && $call->order !== []],
            [CallMisuseRule::DistinctInWindow, $kind !== 's' && $windowed && $distinct],
            [CallMisuseRule::DistinctArguments, $kind === 'a' && !$windowed && $distinct && count($call->arguments) !== 1],
        ];
        $broken = [];
        foreach ($tests as [$rule, $holds]) {
            if ($holds) {
                $broken[] = $rule;
            }
        }

        return $broken;
    }
}
