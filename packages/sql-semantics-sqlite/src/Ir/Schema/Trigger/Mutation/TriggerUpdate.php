<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A resolved UPDATE inside an ordered SQLite trigger program.
 *
 * @visibility public
 * @example Keeping the written conflict policy of a trigger step
 *     $dialect = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $declarations = $dialect->context(['CREATE TABLE watched (id)', 'CREATE TABLE audit (id)']);
 *     $trigger = $dialect->analyze('CREATE TRIGGER trace AFTER INSERT ON watched BEGIN UPDATE OR IGNORE audit SET id=NEW.id; END', $declarations);
 *     $trigger->program[0]->conflict->value // => 'ignore'
 */
final class TriggerUpdate
{
    use Snapshot;

    /**
     * @var non-empty-list<TriggerAssignment> The scalar assignments in written order
     */
    public readonly array $assignments;

    /**
     * Checks all scalar writes against the destination and validates the local filter.
     *
     * @param list<TriggerAssignment> $assignments
     */
    public function __construct(
        public readonly TriggerDestination $destination,
        array $assignments,
        public readonly TriggerEquality|TriggerTargetNullTest|TriggerCondition|null $where = null,
        public readonly UpdateConflict $conflict = UpdateConflict::Inherit,
    ) {
        $list = Check::listOf($assignments, TriggerAssignment::class, 'A trigger update needs one or more ordered scalar assignments.', 1);
        $scope = new TriggerMutationScope();
        $seen = [];
        foreach ($list as $assignment) {
            $column = $assignment->column;
            Check::input(in_array($column, $destination->writable, true) && !isset($seen[$column]), 'A trigger update must write distinct available slots of its destination.');
            if ($assignment->value instanceof TriggerTargetField) {
                $scope->localField($destination, $assignment->value);
            }
            $seen[$column] = true;
        }
        $scope->filter($destination, $where);
        $this->assignments = $list;
    }
}
