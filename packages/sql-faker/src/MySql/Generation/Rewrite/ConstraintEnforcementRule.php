<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql/sql_yacc.yy column_attribute_list allows enforcement only immediately after a CHECK attribute.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class ConstraintEnforcementRule implements RewriteRule
{
    /**
     * Removes only misplaced column enforcement attributes, preserving table CHECK enforcement.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('constraint_enforcement') as $occurrence) {
            $range = $sequence->range($occurrence);
            if ($range === null || !$sequence->terminals[$range[0]]->within('column_attribute')) {
                continue;
            }
            $previous = $sequence->terminals[$range[0] - 1] ?? null;
            if ($previous !== null && $previous->within('check_constraint') && $previous->name === ')') {
                continue;
            }
            $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'mysql.column-check-enforcement');
        }
        return $sequence;
    }
}
