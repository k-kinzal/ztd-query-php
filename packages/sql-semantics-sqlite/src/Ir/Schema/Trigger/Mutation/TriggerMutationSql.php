<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerSql;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

/**
 * Emits the checked destination, ordered writes and scoped filters of trigger mutations.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerMutationSql
{
    /**
     * Emits one UPDATE or DELETE action using the bound destination's local name.
     */
    public function action(TriggerUpdate|TriggerDelete $step, TableSource $watched): string
    {
        $target = $step->destination->table;
        $filter = $this->condition($step->where, $target, $watched);
        if ($step instanceof TriggerDelete) {
            return 'DELETE FROM ' . $target->table->quoted() . $filter;
        }

        $fields = [];
        $sql = new TriggerSql();
        foreach ($step->assignments as $assignment) {
            $value = $assignment->value;
            $rendered = $value instanceof TriggerTargetField ? $this->local($value, $target) : $sql->value($value, $watched);
            $fields[] = $target->columns[$assignment->column]->quoted() . ' = ' . $rendered;
        }
        $policy = $step->conflict === UpdateConflict::Inherit ? '' : ' OR ' . strtoupper($step->conflict->value);

        return 'UPDATE' . $policy . ' ' . $target->table->quoted() . ' SET ' . implode(', ', $fields) . $filter;
    }

    /**
     * Emits a filter after resolving its two possible field scopes.
     */
    public function condition(TriggerEquality|TriggerTargetNullTest|TriggerCondition|null $filter, TableSource $target, TableSource $watched): string
    {
        $sql = new TriggerSql();

        return match (true) {
            $filter === null => '',
            $filter instanceof TriggerTargetNullTest => ' WHERE ' . $this->local($filter->target, $target) . ($filter->isNull ? ' IS NULL' : ' IS NOT NULL'),
            $filter instanceof TriggerCondition => ' WHERE ' . $sql->field($filter->field, $watched) . ($filter->isNull ? ' IS NULL' : ' IS NOT NULL'),
            $filter instanceof TriggerEquality => ' WHERE ' . $this->equality($filter, $target, $watched),
        };
    }

    /**
     * Preserves the native left operand's collation preference.
     */
    public function equality(TriggerEquality $filter, TableSource $target, TableSource $watched): string
    {
        $local = $this->local($filter->target, $target);
        $image = (new TriggerSql())->field($filter->image, $watched);

        return '(' . ($filter->targetFirst ? $local . ' = ' . $image : $image . ' = ' . $local) . ')';
    }

    /**
     * Emits the known local field without reusing any ambiguous input qualifier.
     */
    public function local(TriggerTargetField $field, TableSource $target): string
    {
        return $target->columns[$field->column]->quoted();
    }
}
