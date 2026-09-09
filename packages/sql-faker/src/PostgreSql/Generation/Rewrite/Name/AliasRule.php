<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Name;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y/relation_expr_opt_alias reduces before SET unless the alias has an explicit AS.
 */
final class AliasRule implements RewriteRule
{
    /**
     * Uses the explicit alias alternative while retaining the alias and relation subtrees.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('relation_expr_opt_alias') as $id) {
            $alias = $sequence->child($id, 'ColId');
            $range = $alias === null ? null : $sequence->range($alias->id);
            if ($range === null || $sequence->nameAt($range[0] - 1) === 'AS') {
                continue;
            }
            $sequence = $sequence->replace($range[0], 0, [
                $sequence->insertedFor('AS', $id, 'gram.y:relation_expr_opt_alias'),
            ], 'gram.y:relation_expr_opt_alias');
        }
        return $sequence;
    }
}
