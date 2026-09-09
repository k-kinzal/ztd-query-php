<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Expression;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy:bool_pri explicitly rejects null-safe equality before ALL or ANY.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class QuantifiedComparisonRule implements RewriteRule
{
    /**
     * Uses ordinary equality only for a quantified comparison, retaining scalar null-safe equality.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('bool_pri') as $expression) {
            if ($sequence->child($expression, 'all_or_any') === null) {
                continue;
            }
            $operator = $sequence->child($expression, 'comp_op');
            $range = $operator === null ? null : $sequence->range($operator->id);
            if ($range !== null && $sequence->nameAt($range[0]) === 'EQUAL_SYM') {
                $source = 'sql/sql_yacc.yy:bool_pri:quantified-comparison';
                $sequence = $sequence->replace($range[0], 1, [$sequence->terminals[$range[0]]->replaced('EQ', $source)], $source);
            }
        }
        return $sequence;
    }
}
