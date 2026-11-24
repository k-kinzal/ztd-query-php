<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerChecks;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerEvent;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks that row-image operands remain visible in a native trigger mutation.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerActionFields
{
    /**
     * Checks the image operands of one ordered action against the root activation.
     */
    public function check(TriggerUpdate|TriggerDelete $step, TriggerEvent $event, TableSource $watched): void
    {
        $target = $step->destination->table;
        $filter = $step->where;
        if ($filter instanceof TriggerCondition) {
            $this->image($filter->field, $event, $watched, $target);
        } elseif ($filter instanceof TriggerEquality) {
            $this->image($filter->image, $event, $watched, $target);
        }
        if ($step instanceof TriggerUpdate) {
            foreach ($step->assignments as $assignment) {
                if ($assignment->value instanceof TriggerField) {
                    $this->image($assignment->value, $event, $watched, $target);
                }
            }
        }
    }

    /**
     * Prevents the destination's local names from capturing an active row image.
     */
    public function image(TriggerField $image, TriggerEvent $event, TableSource $watched, TableSource $target): void
    {
        (new TriggerChecks())->field($image, $event, $watched);
        $qualifier = $image->image === RowImage::Old ? 'old' : 'new';
        if ($target->table->key() !== $qualifier) {
            return;
        }

        $name = $watched->columns[$image->column]->key();
        Check::input($name !== 'rowid' && $name !== '_rowid_' && $name !== 'oid', 'A local trigger destination can shadow this row-image rowid alias.');
        foreach ($target->columns as $field) {
            Check::input($field->key() !== $name, 'A local trigger destination shadows this row-image field.');
        }
    }
}
