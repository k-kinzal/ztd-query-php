<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks a mutation predicate's destination field in the local row layout.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerMutationScope
{
    /**
     * Checks the field position when the predicate reads the destination.
     */
    public function filter(TriggerDestination $destination, TriggerEquality|TriggerTargetNullTest|TriggerCondition|null $where): void
    {
        if ($where instanceof TriggerEquality || $where instanceof TriggerTargetNullTest) {
            $this->localField($destination, $where->target);
        }
    }

    /**
     * Checks that a local read belongs to the writable statement's table layout.
     */
    public function localField(TriggerDestination $destination, TriggerTargetField $value): void
    {
        Check::input(isset($destination->table->columns[$value->column]), 'A trigger mutation must read a declared position of its destination.');
    }
}
