<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql/sql_yacc.yy option_value_no_option_type declares NAMES equal expr solely to reject it.
 */
final class SetNamesRule implements RewriteRule
{
    /**
     * Binds the release's DEFAULT token name from sql_yacc.yy.
     */
    public function __construct(private readonly string $defaultTerminal = 'DEFAULT_SYM')
    {
    }

    /**
     * Replaces that diagnostic alternative with its valid NAMES DEFAULT sibling, including arbitrary expressions.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('option_value_no_option_type') as $occurrence) {
            $range = $sequence->range($occurrence);
            if ($range === null || $sequence->nameAt($range[0]) !== 'NAMES_SYM'
                || !in_array($sequence->nameAt($range[0] + 1), ['EQ', 'EQUAL_SYM', 'SET_VAR', '='], true)) {
                continue;
            }
            $names = $sequence->terminals[$range[0]];
            $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                $names, $sequence->inserted($this->defaultTerminal, $names, 'mysql.set-names'),
            ], 'mysql.set-names');
        }
        return $sequence;
    }
}
