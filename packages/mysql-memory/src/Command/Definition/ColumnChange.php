<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\DefaultSetting;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;

/**
 * Applies the actions of ALTER TABLE that change one column to the layout of a table, as the server applies them.
 *
 * An action names a column by the name it had before the statement. CHANGE and MODIFY replace the
 * definition of the column, which keeps its place unless the action names a position; SET DEFAULT
 * and DROP DEFAULT replace the default, SET VISIBLE and SET INVISIBLE the visibility, and RENAME
 * COLUMN the name. A new or moved column is placed at the end, first, or after a column of the new
 * table. Every rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility MySqlMemory
 */
final class ColumnChange
{
    /**
     * @param TableLayout $layout The layout the actions change
     * @param string $table The name of the table, for messages
     */
    public function __construct(public readonly TableLayout $layout, public readonly string $table)
    {
    }

    /**
     * Answers the index in the layout of a column the table has, or raises ER_BAD_FIELD_ERROR.
     *
     * @throws SqlError When the table has no such column
     */
    public function existing(string $name): int
    {
        $index = $this->layout->column($name);
        if ($index === null) {
            throw QueryError::BadField->error($name, $this->table);
        }

        return $index;
    }

    /**
     * Applies CHANGE or MODIFY: replaces the definition of a column in its place, or takes the
     * column out and answers it with its position and its old position, to be placed later.
     *
     * @return list<array{ColumnElement, ColumnPosition|null, int|null}>
     *
     * @throws SqlError When the table has no such column
     */
    public function change(ChangeColumn $command): array
    {
        $layout = $this->layout;
        $index = $this->existing(($command->column ?? $command->definition->name)->column->value);
        $origin = $layout->columns[$index][1];
        $this->stored($layout->columns[$index][0], $command->definition);
        unset($layout->undefaulted[mb_strtolower($layout->columns[$index][0]->name->column->value)]);
        if ($command->position === null) {
            $layout->columns[$index] = [$command->definition, $origin];

            return [];
        }
        array_splice($layout->columns, $index, 1);

        return [[$command->definition, $command->position, $origin]];
    }

    /**
     * Refuses a change of a column between VIRTUAL and STORED, or between VIRTUAL and not generated; a STORED column may become an ordinary one and back.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-generated-columns.html.
     *
     * @throws SqlError When the change does
     */
    public function stored(ColumnElement $old, ColumnElement $new): void
    {
        $kind = static fn (ColumnElement $element): string => $element->specification instanceof GeneratedColumn ? ($element->specification->storage === GeneratedStorage::Stored ? 'stored' : 'virtual') : 'ordinary';
        if ($kind($old) !== $kind($new) && ($kind($old) === 'virtual' || $kind($new) === 'virtual')) {
            throw ConstraintError::GeneratedUnsupported->error('Changing the STORED status');
        }
    }

    /**
     * Applies SET DEFAULT or DROP DEFAULT: replaces the default of a column, and remembers a
     * column whose default is dropped.
     *
     * @throws SqlError When the table has no such column
     */
    public function alterDefault(DefaultSetting $command): void
    {
        $layout = $this->layout;
        $index = $this->existing($command->column->column->value);
        [$element, $origin] = $layout->columns[$index];
        $attributes = array_values(array_filter($this->attributes($element), static fn (ColumnAttribute $attribute): bool => !$attribute instanceof DefaultLiteral && !$attribute instanceof DefaultExpression));
        if ($command->value !== null) {
            $attributes[] = $command->expression ? new DefaultExpression($command->value) : new DefaultLiteral($command->value);
            unset($layout->undefaulted[mb_strtolower($element->name->column->value)]);
        } else {
            $layout->undefaulted[mb_strtolower($element->name->column->value)] = true;
        }
        $layout->columns[$index] = [$this->attributed($element, $attributes), $origin];
    }

    /**
     * Applies SET VISIBLE or SET INVISIBLE: replaces the visibility of a column.
     *
     * @throws SqlError When the table has no such column
     */
    public function alterVisibility(ColumnVisibility $command): void
    {
        $layout = $this->layout;
        $index = $this->existing($command->column->column->value);
        [$element, $origin] = $layout->columns[$index];
        $attributes = array_values(array_filter($this->attributes($element), static fn (ColumnAttribute $attribute): bool => !$attribute instanceof KeywordAttribute || ($attribute->keyword !== ColumnKeyword::Visible && $attribute->keyword !== ColumnKeyword::Invisible)));
        if (!$command->visible) {
            $attributes[] = new KeywordAttribute(ColumnKeyword::Invisible);
        }
        $layout->columns[$index] = [$this->attributed($element, $attributes), $origin];
    }

    /**
     * Applies RENAME COLUMN: gives a column a new name, which keeps a dropped default dropped.
     *
     * @throws SqlError When the table has no such column
     */
    public function rename(RenameElement $command): void
    {
        $layout = $this->layout;
        $index = $this->existing($command->from->column->value);
        [$element, $origin] = $layout->columns[$index];
        if (isset($layout->undefaulted[mb_strtolower($element->name->column->value)])) {
            unset($layout->undefaulted[mb_strtolower($element->name->column->value)]);
            $layout->undefaulted[mb_strtolower($command->to->column->value)] = true;
        }
        $layout->columns[$index] = [new ColumnElement(new ColumnName($command->to->column), $element->specification), $origin];
    }

    /**
     * Places a new or moved column: at the end, first, or after a column of the new table.
     *
     * @throws SqlError When the column to follow does not exist
     */
    public function place(ColumnElement $element, ?ColumnPosition $position, ?int $origin): void
    {
        $columns = $this->layout->columns;
        if ($position === null) {
            $columns[] = [$element, $origin];
        } elseif ($position->after === null) {
            array_unshift($columns, [$element, $origin]);
        } else {
            $after = $this->layout->column($position->after->value);
            if ($after === null) {
                throw QueryError::BadField->error($position->after->value, $this->table);
            }
            array_splice($columns, $after + 1, 0, [[$element, $origin]]);
        }
        $this->layout->columns = $columns;
    }

    /**
     * Answers the attributes of a column definition.
     *
     * @return list<ColumnAttribute>
     */
    public function attributes(ColumnElement $element): array
    {
        $specification = $element->specification;

        return $specification instanceof OrdinaryColumn || $specification instanceof GeneratedColumn ? $specification->attributes : [];
    }

    /**
     * Answers a column definition with other attributes.
     *
     * @param list<ColumnAttribute> $attributes
     */
    public function attributed(ColumnElement $element, array $attributes): ColumnElement
    {
        $specification = $element->specification;
        if (!$specification instanceof OrdinaryColumn && !$specification instanceof GeneratedColumn) {
            return $element;
        }

        return new ColumnElement($element->name, TableLayout::specified($specification, $attributes));
    }
}
