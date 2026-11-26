<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A call of a built-in function with a clause its kind of function does not take.
 *
 * A window-only function needs OVER, a scalar function takes neither OVER
 * nor FILTER nor an argument ordering, a window takes no DISTINCT, and a
 * DISTINCT aggregate takes exactly one argument.
 *
 * @visibility SqlSemantics
 */
final class CallMisuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param CallMisuseRule $rule The broken rule
     * @param Name $function The function name as written
     */
    public function __construct(public readonly CallMisuseRule $rule, public readonly Name $function)
    {
    }

    /**
     * Describes the broken rule in the words of SQLite.
     */
    public function message(): string
    {
        return match ($this->rule) {
            CallMisuseRule::WindowWithoutOver => 'misuse of window function ' . $this->function->value . '()',
            CallMisuseRule::ScalarAsWindow => $this->function->value . '() may not be used as a window function',
            CallMisuseRule::FilterWithoutAggregate => 'FILTER may not be used with non-aggregate ' . $this->function->value . '()',
            CallMisuseRule::FilterOnWindowOnly => 'FILTER clause may only be used with aggregate window functions',
            CallMisuseRule::OrderByWithoutAggregate => 'ORDER BY may not be used with non-aggregate ' . $this->function->value . '()',
            CallMisuseRule::DistinctInWindow => 'DISTINCT is not supported for window functions',
            CallMisuseRule::DistinctArguments => 'DISTINCT aggregates must have exactly one argument',
        };
    }
}
