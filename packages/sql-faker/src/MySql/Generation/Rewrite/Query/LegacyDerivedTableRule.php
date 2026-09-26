<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Query;

use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * Legacy table_factor shares a bare SELECT production with parenthesized derived syntax.
 * A generated table operand needs its own parentheses and alias before joining siblings.
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/sql_yacc.yy
 */
final class LegacyDerivedTableRule implements RewriteRule
{
    /**
     * Completes only the legacy bare SELECT table-factor production.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('table_factor') as $id) {
            if ($sequence->child($id, 'select_derived_init') === null) {
                continue;
            }
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $rule = 'sql_yacc.yy:legacy-derived-table';
            $first = $sequence->terminals[$range[0]];
            if ($first->rewrite === $rule && ($first->ancestors[count($first->ancestors) - 1] ?? null) === $id) {
                continue;
            }
            $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                $sequence->insertedFor('(', $id, $rule),
                ...array_slice($sequence->terminals, $range[0], $range[1] - $range[0]),
                $sequence->insertedFor(')', $id, $rule, 1),
                $sequence->insertedFor('AS', $id, $rule, 2),
                $sequence->insertedFor('IDENT_QUOTED', $id, $rule, 3),
            ], $rule);
        }
        return $sequence;
    }
}
