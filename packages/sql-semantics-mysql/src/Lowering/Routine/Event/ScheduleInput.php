<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Event;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Preserves event-wide table dependencies before a refused scalar operand.
 *
 * Schedules inspect all active query bodies before preparing scalar widths.
 * A CTE definition alone is not active: referring to it supplies a table use
 * in the surrounding query. Observed on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1.
 * This consumes syntax only at the input boundary and retains a typed condition.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ScheduleInput
{
    /**
     * Keeps input-domain refusals in order while attaching the completed event header.
     */
    public function wrap(RejectedInput $failure, Node $schedule, QualifiedName $name, ?Account $definer, \SqlSemantics\Contract\GrammarRelease $release = \SqlSemantics\Contract\GrammarRelease::MySql847): RejectedInput
    {
        if (!$failure->problem instanceof OperandWidth && !$failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar) {
            return $failure;
        }
        $readsTable = $this->readsTable($schedule);
        if (!$readsTable && !$failure->problem instanceof OperandWidth) {
            return $failure;
        }
        $problem = $readsTable
            ? new NotSupportedYet(\SqlSemantics\Platform\MySql\Rules\Routine\Event\Dependencies::feature($release))
            : $failure->problem;
        return new RejectedInput(new RefusedOperand($name, $problem, $definer), $failure->end, $failure->aborts);
    }

    /**
     * Finds an active table occurrence without opening or lowering its row operands.
     */
    public function readsTable(Node $schedule): bool
    {
        $pending = [$schedule];
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node->name === 'common_table_expr') {
                continue;
            }
            if ($node->name === 'table_ident') {
                return true;
            }
            foreach ($node->children as $child) {
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }
        return false;
    }
}
