<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Name;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_lex.cc/MY_LEX_SYSTEM_VAR accepts an identifier or backtick immediately after the second at-sign.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class SystemVariableRule implements RewriteRule
{
    /**
     * Keeps string spellings in ordinary user variables and names after a namespace dot.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->terminals as $index => $terminal) {
            if ($terminal->name === 'TEXT_STRING' && $sequence->nameAt($index - 1) === '@'
                && $sequence->nameAt($index - 2) === '@') {
                $sequence = $sequence->replace($index, 1, [
                    $terminal->replaced('IDENT_QUOTED', 'sql/sql_lex.cc:MY_LEX_SYSTEM_VAR'),
                ], 'sql/sql_lex.cc:MY_LEX_SYSTEM_VAR');
            }
        }
        foreach (['rvalue_system_variable', 'lvalue_variable'] as $rule) {
            foreach ($sequence->occurrences($rule) as $id) {
                $range = $sequence->range($id);
                if ($range !== null && $sequence->nameAt($range[0] + 1) === '.'
                    && in_array($sequence->nameAt($range[0]), ['GLOBAL_SYM', 'LOCAL_SYM', 'SESSION_SYM'], true)) {
                    $sequence = $sequence->replace($range[0], 1, [
                        $sequence->terminals[$range[0]]->replaced('IDENT_QUOTED', 'sql/sql_yacc.yy:check_reserved_words'),
                    ], 'sql/sql_yacc.yy:check_reserved_words');
                }
            }
        }
        return $sequence;
    }
}
