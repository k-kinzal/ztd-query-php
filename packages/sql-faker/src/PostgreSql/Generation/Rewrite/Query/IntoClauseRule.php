<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Query;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * analyze.c:transformOptionalSelectInto accepts INTO only on a top-level query's leftmost SELECT.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/analyze.c
 */
final class IntoClauseRule implements RewriteRule
{
    /**
     * Preserves standalone and EXPLAIN destinations while removing nested and later set-operand destinations.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('into_clause') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $nested = array_diff($origin->rules, ['parse_toplevel', 'stmtmulti', 'toplevel_stmt', 'stmt', 'SelectStmt',
                'select_no_parens', 'select_with_parens', 'select_clause', 'simple_select', 'into_clause',
                'ExplainStmt', 'ExplainableStmt']) !== [];
            foreach ($origin->ancestors as $ancestor) {
                $left = $sequence->child($ancestor, 'select_clause');
                if ($left !== null && !in_array($left->id, $origin->ancestors, true)) {
                    $nested = true;
                }
            }
            if ($nested) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'parser/analyze.c:transformOptionalSelectInto');
            }
        }
        return $sequence;
    }
}
