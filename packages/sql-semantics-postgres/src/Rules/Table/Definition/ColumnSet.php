<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * Collects the columns of a table declaration while a definition is read; lives for one derivation.
 *
 * Rule: PG-TABLE-DECLARATION-001 (work area). Columns keep the order they are
 * added in; an inherited column of a name already present is merged into it
 * (the column is NOT NULL when either is, and generated when either is); a
 * column added under a name already present is not added again. Once
 * closed, the set takes no more columns: the declaration is complete only up
 * to that point. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Rules\Table
 */
final class ColumnSet
{
    /**
     * @var array<string, array{Name, TypeDescriptor, bool, bool}>
     */
    private array $entries = [];

    private bool $closed = false;

    /**
     * Adds a column unless the set is closed or holds one of that name; answers whether it was added.
     *
     * @param bool $generated Whether the column is a generated column
     */
    public function add(Name $name, TypeDescriptor $type, bool $notNull, bool $generated = false): bool
    {
        if ($this->closed || isset($this->entries[$name->value])) {
            return false;
        }
        $this->entries[$name->value] = [$name, $type, $notNull, $generated];

        return true;
    }

    /**
     * Adds an inherited column, merging it into a column of the same name.
     */
    public function inherit(Column $column): void
    {
        $notNull = $column->nullability === Nullability::NotNull;
        if ($this->add($column->name, $column->type, $notNull, $column->generated) || $this->closed) {
            return;
        }
        if ($notNull) {
            $this->require($column->name);
        }
        if ($column->generated) {
            $this->entries[$column->name->value][3] = true;
        }
    }

    /**
     * Marks a column NOT NULL, when the set holds it.
     */
    public function require(Name $name): void
    {
        if (isset($this->entries[$name->value])) {
            $this->entries[$name->value][2] = true;
        }
    }

    /**
     * Closes the set: the columns after this point are not known.
     */
    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * Tells whether the set is closed.
     */
    public function closed(): bool
    {
        return $this->closed;
    }

    /**
     * Answers the declared columns in order.
     *
     * @return list<Column>
     */
    public function columns(): array
    {
        $columns = [];
        foreach ($this->entries as [$name, $type, $notNull, $generated]) {
            $columns[] = new Column($name, $type, $notNull ? Nullability::NotNull : Nullability::Nullable, $generated);
        }

        return $columns;
    }
}
