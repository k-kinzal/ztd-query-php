<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding;

use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertTargetLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerDestination;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsert;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertField;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
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
            $step->upserts === [] && $step->returning === [] && count($step->rows->rows) === 1,
            'A checked trigger insertion needs one VALUES tuple without RETURNING or UPSERT.'
        );
        $into = $step->into;
        $binding = new SourceBinding($operation, $into->target);
        $resolved = $operation->facts->relation($into->target)->table;
        Check::input($resolved instanceof DeclaredTable && $resolved->table->complete, 'A trigger insertion needs a complete destination declaration.');
        foreach ($resolved->table->columns as $column) {
            $binding->slot(0, $column);
        }
        $target = $binding->source();
        Check::input($target instanceof TableSource, 'A trigger insertion needs a resolved destination.');
        $available = [];
        foreach ($binding->implicitWrites() as $slot) {
            $available[] = $slot->slot;
        }
        $selected = (new InsertTargetLowering())->lower($binding, $into, false);
        $values = $step->rows->rows[0]->values;
        Check::input(count($selected) === count($values), 'A trigger insertion needs exactly one value for each checked destination slot.');
        $operands = new TriggerOperands();
        $fields = [];
        foreach ($selected as $index => $slot) {
            $fields[] = new TriggerInsertField($slot->slot, $operands->value($operation, $table, $watched, $values[$index]));
        }
        $policy = $into->replace ? InsertConflict::Replace : match ($into->resolution) {
            null => InsertConflict::Inherit,
            ConflictResolution::Abort => InsertConflict::Abort,
            ConflictResolution::Ignore => InsertConflict::Ignore,
            ConflictResolution::Fail => InsertConflict::Fail,
            ConflictResolution::Rollback => InsertConflict::Rollback,
            ConflictResolution::Replace => InsertConflict::Replace,
        };

        return new TriggerInsert(new TriggerDestination($target, $available), $fields, $policy);
    }
}
