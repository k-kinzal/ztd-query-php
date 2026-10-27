<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Projection;

use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

/**
 * Resolves result labels, including character conversion and stored-view names.
 *
 * @visibility MySqlMemory
 */
final class OutputNames
{
    /**
     * @param Planner $planner The bound query and catalog used to resolve stored-view labels
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Answers the name of an output column.
     *
     * An item without alias that names a column of a view is named as the view names the column,
     * in any case the statement writes it; so is a column of a view of INFORMATION_SCHEMA of MySQL
     * 8.0 and later (verified on live 5.7.44, 8.0.44 and 8.4.7 servers).
     *
     * @param Select|null $select The block of the select list, which tells whether the item has an alias
     */
    public function name(Field $field, ?Select $select = null): string
    {
        if ($field->name !== null) {
            return ($select === null ? null : $this->viewed($field, $select)) ?? $field->name->value;
        }

        foreach ($field->slot->unnamed as $missing) {
            if ($missing instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion) {
                $text = \MySqlMemory\Value\Encoding::convert($missing->text, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known($missing->charset), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb3'));
                return mb_strcut($text, 0, 255, 'UTF-8');
            }
        }

        return $field->expression === null ? '' : (new \MySqlMemory\Evaluation\Compile\Printer())->expression($field->expression);
    }

    /**
     * Answers the name a view gives the column an item without alias names, or null for another item.
     */
    public function viewed(Field $field, Select $select): ?string
    {
        $expression = $field->expression;
        if (!$expression instanceof ColumnUse) {
            return null;
        }
        foreach ($select->items as $item) {
            if ($item instanceof \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression && $item->expression === $expression && $item->alias !== null) {
                return null;
            }
        }
        $resolution = $expression->scalarInformation()->resolution;
        if (!$resolution instanceof ResolvedColumn || $resolution->slot->name === null) {
            return null;
        }
        $table = $resolution->relation->hasBinding() ? $resolution->relation->relationInformation()->table : null;
        if (!$table instanceof \SqlSemantics\Statement\Reference\Table\DeclaredTable || $table->table->kind !== \SqlSemantics\Statement\Declaration\RelationKind::View) {
            return null;
        }
        $system = $this->planner->dictionary->system;
        if ($system !== null && $system->table($table->table) !== null && !$system->named($table->table)) {
            return null;
        }

        return $resolution->slot->name->value;
    }
}
