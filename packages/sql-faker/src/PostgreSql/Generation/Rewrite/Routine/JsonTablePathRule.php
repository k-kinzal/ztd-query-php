<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * json_table requires an A_Const containing a string, even though its RHS accepts a_expr.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L14131-L14159
 */
final class JsonTablePathRule implements RewriteRule
{
    /**
     * Preserves string constants and replaces only the direct path expression, never the context item.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('json_table') as $owner) {
            $path = $sequence->child($owner, 'a_expr');
            $range = $path === null ? null : $sequence->range($path->id);
            if ($range === null) {
                continue;
            }
            $names = array_slice($sequence->names(), $range[0], $range[1] - $range[0]);
            if ($names === ['SCONST'] || $names === ['JSON_TABLE_PATH']) {
                continue;
            }
            $source = 'gram.y:json_table:string-path';
            $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                $sequence->insertedFor('JSON_TABLE_PATH', $path->id, $source),
            ], $source);
        }
        return $sequence;
    }
}
