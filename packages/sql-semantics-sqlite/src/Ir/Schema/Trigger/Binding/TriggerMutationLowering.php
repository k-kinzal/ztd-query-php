<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding;

use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerAssignment;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerDelete;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerUpdate;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete as ParsedTriggerDelete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update as ParsedTriggerUpdate;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTable;

/**
 * Binds an UPDATE or DELETE statement to the checked local mutation roles.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerMutationLowering
{
    /**
     * Resolves a supported single-table mutation and preserves its assignment order.
     */
    public function lower(BoundStatement $operation, TriggerTable $source, TableSource $watched, ParsedTriggerUpdate|ParsedTriggerDelete $step): TriggerUpdate|TriggerDelete
    {
        Check::input($step->with === null && $step->returning === [], 'A checked trigger mutation needs a local target without WITH or RETURNING.');
        Check::input(!$step instanceof ParsedTriggerUpdate || $step->from === null, 'A checked trigger update needs a local target without FROM.');
        [$binding, $destination] = (new TriggerTargetLowering())->lower($operation, $step->target);
        $operands = new TriggerMutationOperands($operation, $source, $watched, $binding);
        $where = $operands->filter($step->where);
        if ($step instanceof ParsedTriggerDelete) {
            return new TriggerDelete($destination, $where);
        }
        $assignments = [];
        foreach ($step->assignments as $assignment) {
            Check::input($assignment instanceof Assignment, 'A checked trigger update requires scalar assignments.');
            $assignments[] = new TriggerAssignment($binding->write($assignment->column), $operands->value($assignment->value));
        }

        return new TriggerUpdate($destination, $assignments, $where, $this->conflict($step->resolution));
    }

    /**
     * Preserves each explicit SQLite conflict algorithm.
     */
    public function conflict(?ConflictResolution $resolution): UpdateConflict
    {
        return match ($resolution) {
            null => UpdateConflict::Inherit,
            ConflictResolution::Abort => UpdateConflict::Abort,
            ConflictResolution::Fail => UpdateConflict::Fail,
            ConflictResolution::Ignore => UpdateConflict::Ignore,
            ConflictResolution::Replace => UpdateConflict::Replace,
            ConflictResolution::Rollback => UpdateConflict::Rollback,
        };
    }
}
