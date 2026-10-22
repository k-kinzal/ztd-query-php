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
    public function wrap(RejectedInput $failure, Node $schedule, QualifiedName $name, ?Account $definer, \SqlSemantics\Contract\GrammarRelease $release = \SqlSemantics\Contract\GrammarRelease::MySql847, ?\SqlSemantics\Platform\MySql\Lowering\Lowering $lowering = null): RejectedInput
    {
        $operands = $lowering === null ? [] : $this->operands($schedule, $lowering);
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse && $failure->problem->rule === \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::RecursiveReferencePosition) {
            return new RejectedInput(new RefusedOperand($name, $failure->problem, $definer, $operands), $failure->end, $failure->aborts);
        }
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
        return new RejectedInput(new RefusedOperand($name, $problem, $definer, $operands), $failure->end, $failure->aborts);
    }

    /**
     * Retains completed schedule operands for name resolution before a scalar refusal.
     *
     * @return list<\SqlSemantics\Statement\Operand> The timestamp or interval followed by written bounds
     */
    public function operands(Node $schedule, \SqlSemantics\Platform\MySql\Lowering\Lowering $lowering): array
    {
        $form = $lowering->form($schedule);
        $operands = [$lowering->expressions->operand($form->node(1))];
        if ($form->signature === 'ev_schedule_time: EVERY_SYM expr interval ev_starts ev_ends') {
            foreach ([3, 4] as $index) {
                $bound = $lowering->form($form->node($index));
                if (count($bound->node->children) !== 0) {
                    $operands[] = $lowering->expressions->operand($bound->node(1));
                }
            }
        }
        return $operands;
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
