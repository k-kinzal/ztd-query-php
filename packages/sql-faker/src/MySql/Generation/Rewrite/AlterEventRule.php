<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy/alter_event_stmt requires at least one event alteration after its name.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class AlterEventRule implements RewriteRule
{
    /**
     * Fills an empty status only when all five alteration clauses were omitted.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('alter_event_stmt') as $id) {
            $present = false;
            foreach (['ev_alter_on_schedule_completion', 'opt_ev_rename_to', 'opt_ev_status', 'opt_ev_comment', 'opt_ev_sql_stmt'] as $clause) {
                $child = $sequence->child($id, $clause);
                $present = $present || ($child !== null && $sequence->range($child->id) !== null);
            }
            $status = $sequence->child($id, 'opt_ev_status');
            $range = $sequence->range($id);
            if (!$present && $status !== null && $range !== null) {
                $sequence = $sequence->replace($range[1], 0, [
                    $sequence->insertedFor('ENABLE_SYM', $status->id, 'sql_yacc.yy:alter_event_stmt'),
                ], 'sql_yacc.yy:alter_event_stmt');
            }
        }
        return $sequence;
    }
}
