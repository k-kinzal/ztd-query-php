<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Name;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_lex.cc/MY_LEX_SYSTEM_VAR accepts an identifier or backtick immediately after the second at-sign.
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
        return $sequence;
    }
}
