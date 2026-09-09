<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * parse_clause.c:transformRangeFunction disallows ordinality beside a top-level column definition list.
 */
final class RangeFunctionOrdinalityRule implements RewriteRule
{
    /**
     * Keeps WITH ORDINALITY when columns only have names or definitions belong inside ROWS FROM.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('table_ref') as $table) {
            $function = $sequence->child($table, 'func_table');
            $alias = $sequence->child($table, 'func_alias_clause');
            if ($function === null || $alias === null || $sequence->child($alias->id, 'TableFuncElementList') === null) {
                continue;
            }
            $ordinality = $sequence->child($function->id, 'opt_ordinality');
            $range = $ordinality === null ? null : $sequence->range($ordinality->id);
            if ($range !== null) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'parse_clause.c:transformRangeFunction');
            }
        }
        return $sequence;
    }
}
