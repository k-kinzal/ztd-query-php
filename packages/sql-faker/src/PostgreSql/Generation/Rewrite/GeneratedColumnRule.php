<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y ColConstraintElem requires GENERATED ALWAYS for expressions; IDENTITY also permits BY DEFAULT.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class GeneratedColumnRule implements RewriteRule
{
    /**
     * Replaces only the generated-expression timing, preserving identity columns and expression subtrees.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('ColConstraintElem') as $id) {
            $when = $sequence->child($id, 'generated_when');
            $expression = $sequence->child($id, 'a_expr');
            $range = $when === null ? null : $sequence->range($when->id);
            if ($expression === null || $range === null || $sequence->nameAt($range[0]) === 'ALWAYS') {
                continue;
            }
            $source = 'gram.y:ColConstraintElem:generated-always';
            $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                $sequence->inserted('ALWAYS', $sequence->terminals[$range[0]], $source),
            ], $source);
        }
        return $sequence;
    }
}
