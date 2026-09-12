<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * gram.y:substr_list uses SIMILAR/ESCAPE as argument separators, also accepted inside a_expr.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class SubstringRule implements RewriteRule
{
    /**
     * Groups compound arguments after nested operand grouping to keep their operators inside the argument.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $lists = array_fill_keys($sequence->occurrences('substr_list'), true);
        foreach ($sequence->productions as $production) {
            if ($production->rule !== 'a_expr' || !isset($lists[$production->parent ?? -1])) {
                continue;
            }
            $range = $sequence->range($production->id);
            if ($range === null || $range[1] - $range[0] < 2 || $sequence->terminals[$range[0]]->rewrite === 'gram.y:substr_list:argument-boundary') {
                continue;
            }
            $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                $sequence->insertedFor('(', $production->id, 'gram.y:substr_list:argument-boundary'),
                ...array_slice($sequence->terminals, $range[0], $range[1] - $range[0]),
                $sequence->insertedFor(')', $production->id, 'gram.y:substr_list:argument-boundary', 1),
            ], 'gram.y:substr_list:argument-boundary');
        }
        return $sequence;
    }
}
