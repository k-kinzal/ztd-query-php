<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Query;

use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy:joined_table defers conditionless joins; joined_table_parens preserves each derived join operand.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class JoinGroupingRule implements RewriteRule
{
    /**
     * Groups nested operands to retain ON and USING attachment without wrapping the root join.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $byId = [];
        foreach ($sequence->productions as $production) {
            $byId[$production->id] = $production;
        }
        foreach ($sequence->occurrences('joined_table') as $id) {
            $parent = $byId[$id]->parent ?? null;
            while ($parent !== null && ($byId[$parent]->rule ?? null) !== 'joined_table') {
                $parent = $byId[$parent]->parent ?? null;
            }
            if ($parent === null) {
                continue;
            }
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $first = $sequence->terminals[$range[0]];
            if ($first->rewrite === 'sql/sql_yacc.yy:joined_table_parens' && ($first->ancestors[count($first->ancestors) - 1] ?? null) === $id) {
                continue;
            }
            $open = $sequence->insertedFor('(', $id, 'sql/sql_yacc.yy:joined_table_parens');
            $close = new TerminalOccurrence(')', $open->id - 1, $open->ancestors, $open->rules, $open->rewrite);
            $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                $open, ...array_slice($sequence->terminals, $range[0], $range[1] - $range[0]), $close,
            ], 'sql/sql_yacc.yy:joined_table_parens');
        }
        return $sequence;
    }
}
