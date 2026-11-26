<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding;

use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertTargetLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsert;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertRow;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTable;

/**
 * Lowers a trigger insertion with a bound mutation target and ordered row operands.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerStepLowering
{
    /**
     * Resolves the writable target, ordered slots, row operands and conflict policy.
     * @throws InvalidConstruction When a trigger insertion is outside the checked form
     */
    public function lower(BoundStatement $operation, TriggerTable $table, TableSource $watched, InsertRows $step): TriggerInsert
    {
        Check::input(
            $step->upserts === [] && $step->returning === [],
            'A checked trigger insertion needs VALUES without RETURNING or UPSERT.'
        );
        $into = $step->into;
        [$binding, $destination] = (new TriggerTargetLowering())->lower($operation, $into->target);
        $selected = (new InsertTargetLowering())->lower($binding, $into, false);
        $operands = new TriggerOperands();
        $rows = [];
        foreach ($step->rows->rows as $row) {
            $values = $row->values;
            Check::input(count($selected) === count($values), 'A trigger insertion needs exactly one value for each checked destination slot.');
            $fields = [];
            foreach ($selected as $index => $slot) {
                $fields[] = new TriggerInsertField($slot->slot, $operands->value($operation, $table, $watched, $values[$index]));
            }
            $rows[] = new TriggerInsertRow($fields);
        }
        $policy = $into->replace ? InsertConflict::Replace : match ($into->resolution) {
            null => InsertConflict::Inherit,
            ConflictResolution::Abort => InsertConflict::Abort,
            ConflictResolution::Ignore => InsertConflict::Ignore,
            ConflictResolution::Fail => InsertConflict::Fail,
            ConflictResolution::Rollback => InsertConflict::Rollback,
            ConflictResolution::Replace => InsertConflict::Replace,
        };

        return new TriggerInsert($destination, $rows, $policy);
    }
}
