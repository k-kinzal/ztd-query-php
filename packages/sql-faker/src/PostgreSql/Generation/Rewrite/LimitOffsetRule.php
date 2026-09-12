<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * Replaces gram.y's always-error LIMIT value,value diagnostic alternative with LIMIT/OFFSET.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class LimitOffsetRule implements RewriteRule
{
    /**
     * Retains values; when a separate OFFSET already exists, it remains the sole offset clause.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('limit_clause') as $id) {
            $offset = $sequence->child($id, 'select_offset_value');
            $range = $offset === null ? null : $sequence->range($offset->id);
            if ($range === null || $sequence->nameAt($range[0] - 1) !== ',') {
                continue;
            }
            $comma = $sequence->terminals[$range[0] - 1];
            $parent = $comma->ancestor('select_limit');
            $existing = $parent === null ? null : $sequence->child($parent, 'offset_clause');
            $replacement = $existing === null ? [$comma->replaced('OFFSET', 'gram.y:limit_clause')] : [];
            $length = $existing === null ? 1 : $range[1] - $range[0] + 1;
            $sequence = $sequence->replace($range[0] - 1, $length, $replacement, 'gram.y:limit_clause');
        }
        return $sequence;
    }
}
