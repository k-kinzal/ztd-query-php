<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableChange;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnFlags;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\DefaultSetting;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;

/**
 * Applies the column actions of one ALTER TABLE to the row shape of its table.
 *
 * Rule: MYSQL-COLUMN-CHANGES-001. The server applies the actions to the
 * existing table as a whole: DROP COLUMN, CHANGE, MODIFY and RENAME COLUMN
 * name a column of the existing table, each such column at most once;
 * ALTER COLUMN names a column of the existing table or one the statement
 * adds; ADD COLUMN adds at the end, FIRST or AFTER a column of the result.
 * The resulting shape is the existing slots without the dropped ones, a
 * renamed slot under its new name re-exposing the existing slot, a changed
 * slot replaced by the declared type and NULL fact of its new definition
 * (MYSQL-COLUMN-DEFINITION-001 through ElementFacts::declaration), and the
 * added columns; a shape that was open stays open. An invisible column (an
 * implicit column of the declaration that aliases no declared column, an
 * added or changed column declared INVISIBLE, or one that ALTER COLUMN …
 * SET INVISIBLE hides) takes part in every check but is found only by
 * name, as an implicit slot, and is not in the row shape. When the table is a
 * declaration with a complete column list, a named column that is not
 * there (or no longer there) is the diagnostic UnknownColumn and a name the
 * result has twice is the diagnostic DuplicateColumn, reported at its later
 * occurrence; nothing is reported for an open shape. Column names compare
 * by the context's column name comparison. Terminates: one pass over the
 * actions, each scanning the finite column list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html,
 * https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_table.cc (mysql_prepare_alter_table).
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnChanges
{
    /**
     * @var list<array{OutputSlot, int|null, bool}> The columns of the result in order, with the position of the existing column each continues and whether it is invisible
     */
    private array $columns = [];

    /**
     * @var array<int, true> The positions of the existing columns a drop, change or rename has used
     */
    private array $used = [];

    /**
     * @var list<Name|null> The names of the existing columns
     */
    private array $existing = [];

    private bool $decided = false;

    private bool $reporting = false;

    private ?Derivation $derivation = null;

    /**
     * Answers the row shape of the visible columns after the actions, and reports the column problems of a completely known table when asked to.
     *
     * @param list<AlterCommand> $commands The actions in order
     * @param bool $report Whether to report the column problems
     */
    public function apply(RelationFact $table, array $commands, Derivation $derivation, bool $report): RowShape
    {
        $this->derivation = $derivation;
        $this->reporting = $report;
        $this->decided = $table->table instanceof DeclaredTable && $table->shape->complete();
        foreach ($table->shape->slots as $position => $slot) {
            $this->columns[] = [$slot, $position, false];
            $this->existing[] = $slot->name;
        }
        $declared = $table->table instanceof DeclaredTable ? $table->table->table->columns : [];
        foreach ($table->table instanceof DeclaredTable ? $table->table->table->implicit : [] as $implicit) {
            if (!in_array($implicit->column, $declared, true)) {
                $this->columns[] = [new OutputSlot($implicit->names[0], new Known($implicit->column->type), $implicit->column->nullability, $implicit->column), count($this->existing), true];
                $this->existing[] = $implicit->names[0];
            }
        }
        $added = [];
        foreach ($commands as $command) {
            $added = [...$added, ...$this->command($command, $added)];
        }
        $this->duplicates();
        $slots = [];
        foreach ($this->columns as [$slot, , $hidden]) {
            if (!$hidden) {
                $slots[] = $slot;
            }
        }

        return new RowShape($slots, $table->shape->missing);
    }

    /**
     * Answers the invisible columns after the actions, which a name finds but a star does not.
     *
     * @return list<ImplicitSlot>
     */
    public function implicit(): array
    {
        $slots = [];
        foreach ($this->columns as [$slot, , $hidden]) {
            if ($hidden && $slot->name !== null) {
                $slots[] = new ImplicitSlot([$slot->name], $slot);
            }
        }

        return $slots;
    }

    /**
     * Applies one action and answers the names of the columns it adds.
     *
     * @param list<Name> $added The names of the columns the earlier actions added
     * @return list<Name>
     */
    public function command(AlterCommand $command, array $added): array
    {
        if ($command instanceof AddColumn) {
            $this->insert($this->slot($command->column), $command->position, null, $this->invisible($command->column));

            return [$command->column->name->column];
        }
        if ($command instanceof AddColumns) {
            $names = [];
            foreach ($command->elements as $element) {
                if ($element instanceof ColumnDefinition) {
                    $this->insert($this->slot($element), null, null, $this->invisible($element));
                    $names[] = $element->name->column;
                }
            }

            return $names;
        }
        if ($command instanceof ChangeColumn) {
            $this->change($command);
        } elseif ($command instanceof DropElement && $command->kind === ElementKind::Column && $command->name !== null) {
            $this->drop($command->name->column);
        } elseif ($command instanceof RenameElement && $command->kind === ElementKind::Column) {
            $this->rename($command->from->column, $command->to->column);
        } elseif ($command instanceof DefaultSetting) {
            $this->altered($command->column->column, $added);
        } elseif ($command instanceof ColumnVisibility) {
            $this->altered($command->column->column, $added);
            $this->show($command);
        }

        return [];
    }

    /**
     * Replaces the existing column a CHANGE or MODIFY names by its new definition.
     */
    public function change(ChangeColumn $command): void
    {
        $existing = $this->use($command->changed()->column);
        $index = $existing === null ? null : $this->index($existing);
        if ($index !== null) {
            array_splice($this->columns, $index, 1);
        }
        $this->insert($this->slot($command->definition), $command->position, $index, $this->invisible($command->definition));
    }

    /**
     * Removes the existing column a DROP COLUMN names.
     */
    public function drop(Name $column): void
    {
        $existing = $this->use($column);
        $index = $existing === null ? null : $this->index($existing);
        if ($index !== null) {
            array_splice($this->columns, $index, 1);
        }
    }

    /**
     * Gives the existing column a RENAME COLUMN names its new name.
     */
    public function rename(Name $column, Name $name): void
    {
        $existing = $this->use($column);
        $index = $existing === null ? null : $this->index($existing);
        if ($existing !== null && $index !== null) {
            [$slot, , $hidden] = $this->columns[$index];
            $this->columns[$index] = [new OutputSlot($name, $slot->type, $slot->nullability, null, $slot), $existing, $hidden];
        }
    }

    /**
     * Reports an ALTER COLUMN of a name that is neither an existing nor an added column.
     *
     * @param list<Name> $added The names of the columns the earlier actions added
     */
    public function altered(Name $column, array $added): void
    {
        if ($this->find([...$this->existing, ...$added], $column) === null) {
            $this->unknown($column);
        }
    }

    /**
     * Marks the existing column of a name as used and answers its position, reporting a name that is absent or already used.
     */
    public function use(Name $column): ?int
    {
        $position = $this->find($this->existing, $column);
        if ($position === null || isset($this->used[$position])) {
            $this->unknown($column);

            return null;
        }
        $this->used[$position] = true;

        return $position;
    }

    /**
     * Answers the position of a name in a list of names.
     *
     * @param list<Name|null> $names The names; null for a position without a name
     */
    public function find(array $names, Name $column): ?int
    {
        $comparison = $this->derivation?->context->columnNames;
        foreach ($names as $position => $name) {
            if ($name !== null && $comparison !== null && $comparison->equal($name->value, $column->value)) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Answers the index in the result of the column that continues an existing column.
     */
    public function index(int $existing): ?int
    {
        foreach ($this->columns as $index => [, $position]) {
            if ($position === $existing) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Places a column of the result: FIRST, AFTER a column of the result, at an index, or last.
     */
    public function insert(OutputSlot $slot, ?ColumnPosition $position, ?int $index, bool $hidden): void
    {
        if ($position !== null && $position->after === null) {
            $index = 0;
        } elseif ($position?->after !== null) {
            $names = [];
            foreach ($this->columns as [$column]) {
                $names[] = $column->name;
            }
            $after = $this->find($names, $position->after);
            if ($after === null) {
                $this->unknown($position->after);
            }
            $index = $after === null ? null : $after + 1;
        }
        array_splice($this->columns, $index ?? count($this->columns), 0, [[$slot, null, $hidden]]);
    }

    /**
     * Makes the columns an ALTER COLUMN … SET VISIBLE or INVISIBLE names visible or invisible.
     */
    public function show(ColumnVisibility $command): void
    {
        $names = [];
        foreach ($this->columns as [$slot]) {
            $names[] = $slot->name;
        }
        $index = $this->find($names, $command->column->column);
        if ($index !== null) {
            $this->columns[$index][2] = !$command->visible;
        }
    }

    /**
     * Tells whether a new column definition is INVISIBLE.
     */
    public function invisible(ColumnDefinition $definition): bool
    {
        return (new ColumnFlags())->invisible($definition->specification);
    }

    /**
     * Answers the slot of a new column definition.
     */
    public function slot(ColumnDefinition $definition): OutputSlot
    {
        $column = (new ElementFacts())->declaration($definition);

        return new OutputSlot($column->name, new Known($column->type), $column->nullability);
    }

    /**
     * Reports each name the result has a second time.
     */
    public function duplicates(): void
    {
        $names = [];
        foreach ($this->columns as [$slot]) {
            if ($slot->name !== null && $this->find($names, $slot->name) !== null) {
                $this->report(new DuplicateColumn($slot->name));
            }
            $names[] = $slot->name;
        }
    }

    /**
     * Reports an absent column name.
     */
    public function unknown(Name $column): void
    {
        $this->report(new UnknownColumn($column));
    }

    /**
     * Reports a problem when the table is completely known.
     */
    public function report(UnknownColumn|DuplicateColumn $problem): void
    {
        if ($this->decided && $this->reporting) {
            $this->derivation?->report($problem);
        }
    }
}
