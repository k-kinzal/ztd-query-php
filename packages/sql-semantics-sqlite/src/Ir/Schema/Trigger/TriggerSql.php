<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

/**
 * Renders checked trigger operands and local destinations in their owning scope.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerSql
{
    /**
     * Emits the complete trigger definition.
     */
    public function statement(CreateTrigger $trigger): string
    {
        $temporary = $trigger->placement === TriggerPlacement::TemporaryKeyword;
        $declaration = ($temporary ? 'CREATE TEMP TRIGGER ' : 'CREATE TRIGGER ')
            . ($trigger->ifNotExists ? 'IF NOT EXISTS ' : '')
            . ($temporary ? '' : $trigger->schema->quoted() . '.') . $trigger->name->quoted();
        $watched = $trigger->watched;
        $events = $trigger->timing->value . ' ' . $trigger->event->value;
        $names = [];
        foreach ($trigger->selectors as $member) {
            $selector = $member->selector;
            $names[] = (is_int($selector) ? $watched->columns[$selector] : $selector)->quoted();
        }
        if ($names !== []) {
            $events .= ' OF ' . implode(', ', $names);
        }
        $condition = $trigger->condition;
        $when = $condition === null ? '' : ' WHEN ' . $this->field($condition->field, $watched)
            . ($condition->isNull ? ' IS NULL' : ' IS NOT NULL');
        $steps = [];
        foreach ($trigger->program as $step) {
            $steps[] = $this->insertion($step, $watched) . ';';
        }

        return $declaration . ' ' . $events . ' ON ' . $watched->schema->quoted() . '.' . $watched->table->quoted()
            . $when . ' BEGIN ' . implode(' ', $steps) . ' END';
    }

    /**
     * Emits one exact insertion and its destination and value associations.
     */
    public function insertion(TriggerInsert $step, TableSource $watched): string
    {
        $target = $step->destination->table;
        $columns = [];
        $values = [];
        foreach ($step->fields as $field) {
            $columns[] = $target->columns[$field->column]->quoted();
            $value = $field->value;
            $values[] = match (true) {
                $value instanceof TriggerField => $this->field($value, $watched),
                $value instanceof IntegerLiteral => $value->decimal,
                $value instanceof TextLiteral => $value->toString(),
                $value instanceof NullLiteral => 'NULL',
            };
        }
        $policy = $step->conflict === InsertConflict::Inherit ? '' : ' OR ' . strtoupper($step->conflict->value);

        return 'INSERT' . $policy . ' INTO ' . $target->table->quoted()
            . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
    }

    /**
     * Emits a checked row-image field against the watched layout.
     */
    public function field(TriggerField $field, TableSource $watched): string
    {
        return $field->image->value . '.' . $watched->columns[$field->column]->quoted();
    }
}
