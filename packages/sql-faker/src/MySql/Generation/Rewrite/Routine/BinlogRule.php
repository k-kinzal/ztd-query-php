<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Routine;

use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * MySQL admits BINLOG as a label, hiding the statement in routine bodies.
 * @see https://github.com/mysql/mysql-server/blob/mysql-9.1.0/sql/sql_yacc.yy
 */
final class BinlogRule implements RewriteRule
{
    /**
     * Evaluates the retained string with DO when BINLOG cannot begin a body statement.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->terminals as $index => $terminal) {
            if ($terminal->name === 'BINLOG_SYM' && ($terminal->within('binlog_base64_event') || $terminal->within('binlog_stmt')) && $terminal->within('sp_proc_stmt_statement')) {
                $sequence = $sequence->replace($index, 1, [
                    $terminal->replaced('DO_SYM', 'sql/sql_yacc.yy:keyword_sp:BINLOG_SYM'),
                ], 'sql/sql_yacc.yy:keyword_sp:BINLOG_SYM');
            }
        }
        return $sequence;
    }
}
