<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y:func_expr forbids inner ordering, DISTINCT and VARIADIC with WITHIN GROUP.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class WithinGroupRule implements RewriteRule
{
    /**
     * Retains the ordered-set clause and arguments without changing nested function calls.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('func_expr') as $id) {
            $within = $sequence->child($id, 'within_group_clause');
            $application = $sequence->child($id, 'func_application');
            if ($within === null || $application === null || $sequence->range($within->id) === null) {
                continue;
            }
            $order = $sequence->child($application->id, 'opt_sort_clause');
            $range = $order === null ? null : $sequence->range($order->id);
            if ($range !== null) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'gram.y:func_expr:within-group');
            }
            for ($index = count($sequence->terminals) - 1; $index >= 0; --$index) {
                $terminal = $sequence->terminals[$index];
                if (in_array($terminal->name, ['DISTINCT', 'VARIADIC'], true)
                    && ($terminal->ancestors[count($terminal->ancestors) - 1] ?? null) === $application->id) {
                    $sequence = $sequence->replace($index, 1, [], 'gram.y:func_expr:within-group');
                }
            }
        }
        return $sequence;
    }
}
