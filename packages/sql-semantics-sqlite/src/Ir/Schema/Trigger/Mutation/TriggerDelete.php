<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A resolved DELETE inside an ordered SQLite trigger program.
 *
 * @visibility public
 * @example Seeing the resolved destination of a trigger deletion
 *     $dialect = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $declarations = $dialect->context(['CREATE TABLE watched (id)', 'CREATE TABLE audit (id)']);
 *     $trigger = $dialect->analyze('CREATE TRIGGER trace AFTER DELETE ON watched BEGIN DELETE FROM audit WHERE id=OLD.id; END', $declarations);
 *     $trigger->program[0]->destination->table->table->value // => 'audit'
 */
final class TriggerDelete
{
    use Snapshot;

    /**
     * Checks the local filter in the context of the selected destination.
     */
    public function __construct(
        public readonly TriggerDestination $destination,
        public readonly TriggerEquality|TriggerTargetNullTest|TriggerCondition|null $where = null,
    ) {
        (new TriggerMutationScope())->filter($destination, $where);
    }
}
