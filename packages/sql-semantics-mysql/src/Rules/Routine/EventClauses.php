<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;
use SqlSemantics\Rendering\Output;

/**
 * Writes the clauses CREATE EVENT and ALTER EVENT share: ON COMPLETION, the status and COMMENT.
 *
 * Rule: MYSQL-EVENT-CLAUSES-001. Each clause is written with the keywords
 * the grammar reads it from; DISABLE ON SLAVE and DISABLE ON REPLICA keep
 * their own spelling. Terminates: constant work.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class EventClauses
{
    /**
     * Writes the clauses that are present, in grammar order.
     */
    public function write(Output $out, ?Completion $completion, ?EventStatus $status, ?Text $comment): void
    {
        match ($completion) {
            null => null,
            Completion::Preserve => $out->keyword('ON', 'COMPLETION', 'PRESERVE'),
            Completion::NotPreserve => $out->keyword('ON', 'COMPLETION', 'NOT', 'PRESERVE'),
        };
        match ($status) {
            null => null,
            EventStatus::Enable => $out->keyword('ENABLE'),
            EventStatus::Disable => $out->keyword('DISABLE'),
            EventStatus::DisableOnSlave => $out->keyword('DISABLE', 'ON', 'SLAVE'),
            EventStatus::DisableOnReplica => $out->keyword('DISABLE', 'ON', 'REPLICA'),
        };
        if ($comment !== null) {
            $out->keyword('COMMENT')->node($comment);
        }
    }
}
