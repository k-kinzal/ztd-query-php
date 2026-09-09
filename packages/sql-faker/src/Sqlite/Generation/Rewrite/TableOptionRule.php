<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * version-3.47.2 parse.y table_option recognizes the ID spelling STRICT and WITHOUT ROWID.
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/parse.y
 */
final class TableOptionRule implements RewriteRule
{
    /**
     * Resolves the generic table-option identifier without restricting identifiers in other grammar positions.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('table_option') as $occurrence) {
            $range = $sequence->range($occurrence);
            if ($range === null) {
                continue;
            }
            $without = $sequence->nameAt($range[0]) === 'WITHOUT';
            $start = $range[0] + ($without ? 1 : 0);
            $name = $without ? 'ROWID_TABLE_OPTION' : 'STRICT_TABLE_OPTION';
            $sequence = $sequence->replace($start, $range[1] - $start, [
                $sequence->terminals[$start]->replaced($name, 'sqlite.table-option'),
            ], 'sqlite.table-option');
        }
        return $sequence;
    }
}
