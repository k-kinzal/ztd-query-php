<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy/opt_flush_lock requires a table list for FOR EXPORT, but not for WITH READ LOCK.
 */
final class FlushExportRule implements RewriteRule
{
    /**
     * Completes the original empty table-list occurrence when exporting table files.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('flush_options') as $id) {
            $tables = $sequence->child($id, 'opt_table_list');
            $lock = $sequence->child($id, 'opt_flush_lock');
            if ($tables === null || $lock === null || $sequence->range($tables->id) !== null) {
                continue;
            }
            $range = $sequence->range($lock->id);
            if ($range !== null && $sequence->nameAt($range[0]) === 'FOR_SYM') {
                $sequence = $sequence->replace($range[0], 0, [
                    $sequence->insertedFor('IDENT_QUOTED', $tables->id, 'mysql.flush-export-table'),
                ], 'mysql.flush-export-table');
            }
        }
        return $sequence;
    }
}
