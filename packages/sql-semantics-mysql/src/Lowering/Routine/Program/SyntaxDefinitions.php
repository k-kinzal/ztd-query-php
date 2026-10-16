<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

/**
 * Preserves prohibited nested declaration headers before later body operands.
 *
 * The caller has identified a stored instruction. CREATE routines and triggers
 * stop at their header; an event definition with a body stops before that body.
 * Observed on MySQL 8.0, 8.4 and 9.1 without consulting implementation code.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxDefinitions
{
    /**
     * Answers an immediate nested declaration restriction at its input boundary.
     */
    public function rejected(Node $node): ?RejectedInput
    {
        $kind = match ($node->name) {
            'sp_tail' => 'PROCEDURE',
            'sf_tail' => 'FUNCTION',
            'trigger_tail' => 'TRIGGER',
            default => null,
        };
        if ($kind !== null) {
            return new RejectedInput(new ProgramProblem(ProgramRule::RecursiveCreate, $kind), $node->tokens()[0]->end(), true);
        }
        if (!in_array($node->name, ['event_tail', 'alter_event_stmt', 'alter'], true)) {
            return null;
        }
        foreach ($node->children as $child) {
            if ($child instanceof Node && ($child->name === 'ev_sql_stmt' || ($child->name === 'opt_ev_sql_stmt' && $child->children !== []))) {
                return new RejectedInput(new ProgramProblem(ProgramRule::EventRecursion), (new SourceRanges())->span($child)[0] ?? 0, true);
            }
        }
        return null;
    }
}
