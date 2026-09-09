<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Routine;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy/sp_proc_stmt_return permits RETURN only in stored functions.
 */
final class ReturnRule implements RewriteRule
{
    /**
     * Evaluates the retained expression with DO when the enclosing routine cannot return a value.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('sp_proc_stmt_return') as $id) {
            $range = $sequence->range($id);
            if ($range === null || $sequence->nameAt($range[0]) !== 'RETURN_SYM') {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            foreach (array_reverse($origin->rules) as $scope) {
                if ($scope === 'sf_tail') {
                    break;
                }
                if (in_array($scope, ['sp_tail', 'trigger_tail', 'ev_sql_stmt'], true)) {
                    $sequence = $sequence->replace($range[0], 1, [
                        $origin->replaced('DO_SYM', 'sql/sql_yacc.yy:sp_proc_stmt_return'),
                    ], 'sql/sql_yacc.yy:sp_proc_stmt_return');
                    break;
                }
            }
        }
        return $sequence;
    }
}
