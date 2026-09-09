<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Replication;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Maps replication table filters to the string domain checked by sql_yacc.yy.
 */
final class TablePatternRule implements RewriteRule
{
    /**
     * Preserves ordinary strings while retaining filter occurrence identities through the wrapper rules.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->terminals as $index => $terminal) {
            if ($terminal->name === 'TEXT_STRING' && $terminal->within('filter_wild_db_table_string')) {
                $source = 'sql/sql_yacc.yy:filter_wild_db_table_string';
                $sequence = $sequence->replace($index, 1, [$terminal->replaced('REPLICATION_TABLE_PATTERN', $source)], $source);
            }
        }
        return $sequence;
    }
}
