<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * parse.y:parserDoubleLinkSelect permits ORDER BY and LIMIT only on the final compound operand.
 */
final class CompoundSelectRule implements RewriteRule
{
    /**
     * Removes clauses from each prior operand, preserving nested queries and the final SELECT.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('selectnowith') as $id) {
            $left = $sequence->child($id, 'selectnowith');
            $operand = $left === null ? null : $sequence->child($left->id, 'oneselect');
            if ($operand === null) {
                continue;
            }
            foreach (['orderby_opt', 'limit_opt'] as $rule) {
                $clause = $sequence->child($operand->id, $rule);
                $range = $clause === null ? null : $sequence->range($clause->id);
                if ($range !== null) {
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'parse.y:parserDoubleLinkSelect');
                }
            }
        }
        return $sequence;
    }
}
