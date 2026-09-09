<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql/parse_tree_nodes.cc PT_subquery rejects a query expression carrying an INTO destination.
 */
final class IntoClauseRule implements RewriteRule
{
    /**
     * Removes INTO only inside an actual subquery, preserving top-level output destinations.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('into_clause') as $id) {
            $range = $sequence->range($id);
            if ($range !== null && $sequence->terminals[$range[0]]->ancestor('subquery') !== null) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'sql/parse_tree_nodes.cc:PT_subquery:into');
            }
        }
        return $sequence;
    }
}
