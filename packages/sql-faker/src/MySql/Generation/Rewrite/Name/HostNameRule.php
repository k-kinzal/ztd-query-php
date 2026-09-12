<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Name;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * sql_lex.cc enters MY_LEX_HOSTNAME only after a single at sign, including account and user-variable names.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc#L2065-L2096
 */
final class HostNameRule implements RewriteRule
{
    /**
     * Outside that scanner state, ident_or_text must use an ordinary identifier instead of a dotted hostname run.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->terminals as $index => $terminal) {
            if ($terminal->name !== 'LEX_HOSTNAME') {
                continue;
            }
            if (($sequence->terminals[$index - 1]->name ?? null) === '@' && ($sequence->terminals[$index - 2]->name ?? null) !== '@') {
                continue;
            }
            $source = 'sql/sql_lex.cc:MY_LEX_HOSTNAME:entry-state';
            $sequence = $sequence->replace($index, 1, [$terminal->replaced('IDENT', $source)], $source);
        }
        return $sequence;
    }
}
