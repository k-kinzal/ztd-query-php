<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Query;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_lex.cc restricts query options; sql_yacc.yy provides AS to disambiguate CREATE query sources.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class QueryContextRule implements RewriteRule
{
    /**
     * Keeps nested query options valid and separates partition definitions from the following query.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('as_create_query_expression') as $id) {
            $range = $sequence->range($id);
            if ($range !== null && $sequence->nameAt($range[0]) !== 'AS') {
                $sequence = $sequence->replace($range[0], 0, [
                    $sequence->insertedFor('AS', $id, 'sql/sql_yacc.yy:as_create_query_expression'),
                ], 'sql/sql_yacc.yy:as_create_query_expression');
            }
        }
        for ($index = count($sequence->terminals) - 1; $index >= 0; --$index) {
            $terminal = $sequence->terminals[$index];
            if (in_array($terminal->name, ['HIGH_PRIORITY', 'SQL_BUFFER_RESULT', 'SQL_CALC_FOUND_ROWS'], true)
                && $terminal->within('select_option') && $terminal->within('subquery')) {
                $sequence = $sequence->replace($index, 1, [], 'sql/sql_lex.cc:validate_outermost_option');
            }
        }
        return $sequence;
    }
}
