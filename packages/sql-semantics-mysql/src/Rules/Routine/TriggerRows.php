<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignments;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Node;

/**
 * Checks the row assignments of a trigger body against its time and event.
 *
 * OLD is read only; NEW is written only in a BEFORE trigger and does not exist
 * in a DELETE trigger. The server refuses these as it parses the assignment,
 * before any later instruction (verified on 8.4.7: `CREATE TRIGGER tr BEFORE
 * INSERT ON t FOR EACH ROW SET OLD.a = 1` answers 1362 even when a syntax
 * error follows). Reads of a row word remain scope-resolved, because a FROM
 * alias named `new` or `old` shadows the row.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/trigger-syntax.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TriggerRows
{
    /**
     * Answers the first row assignment the trigger cannot make, with the SET statement holding it.
     *
     * @return array{ProgramProblem, Node}|null
     */
    public function problem(Node $body, TriggerTime $time, TriggerEvent $event): ?array
    {
        foreach ((new ReadDependencies())->nodes($body) as $node) {
            if (!$node instanceof SetVariables && !$node instanceof DeferredAssignments) {
                continue;
            }
            foreach ($node->items as $item) {
                if (!$item instanceof NameAssignment) {
                    continue;
                }
                $problem = $this->assignment($item, $time, $event);
                if ($problem !== null) {
                    return [$problem, $node];
                }
            }
        }
        return null;
    }

    /**
     * Answers the rule one assignment to the NEW or OLD row breaks, or null.
     */
    public function assignment(NameAssignment $item, TriggerTime $time, TriggerEvent $event): ?ProgramProblem
    {
        if ($item->scope !== null || $item->qualifier === null) {
            return null;
        }
        $row = strtoupper($item->qualifier->value);

        return match (true) {
            $row === 'OLD' => new ProgramProblem(ProgramRule::OldRowUpdate),
            $row !== 'NEW' => null,
            $event === TriggerEvent::Delete => new ProgramProblem(ProgramRule::NoNewRow),
            $time === TriggerTime::After => new ProgramProblem(ProgramRule::AfterRowUpdate),
            default => null,
        };
    }
}
