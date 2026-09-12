<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Routine;

use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy/stored_routine_body requires a non-SQL language for AS strings and SQL for statement bodies.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class LanguageRule implements RewriteRule
{
    /**
     * Makes the body's language explicit after any characteristics, retaining the chosen body.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('stored_routine_body') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $owner = $origin->ancestor('sp_tail') ?? $origin->ancestor('sf_tail');
            if ($owner === null || ($sequence->terminals[$range[0] - 2]->rewrite ?? null) === 'sql/sql_yacc.yy:stored_routine_body') {
                continue;
            }
            $language = $origin->name === 'AS' ? 'EXTERNAL_ROUTINE_LANGUAGE' : 'SQL_SYM';
            $sequence = $sequence->replace($range[0], 0, [
                $sequence->insertedFor('LANGUAGE_SYM', $owner, 'sql/sql_yacc.yy:stored_routine_body'),
                $sequence->insertedFor($language, $owner, 'sql/sql_yacc.yy:stored_routine_body', 1),
            ], 'sql/sql_yacc.yy:stored_routine_body');
        }
        return $sequence;
    }
}
