<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use MySqlMemory\Dictionary\Partition\Partitioning;
use MySqlMemory\Result\ColumnFlag;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Declaration\Table;

/**
 * The definition of a stored table: its columns, keys and options, and the declaration SQL Semantics binds statements against.
 *
 * @visibility MySqlMemory
 */
final class TableDefinition
{
    /**
     * @param string $schema The database the table belongs to
     * @param string $name The table name
     * @param list<ColumnDefinition> $columns The columns in declared order, invisible ones included
     * @param list<Key> $keys The indexes, the primary key first
     * @param Table $declaration The declaration statements are bound against
     * @param string $engine The storage engine named, InnoDB by default
     * @param string $collation The default collation of the table
     * @param bool $temporary Whether the table is a temporary table of one session
     * @param string $comment The comment of the table
     * @param CreateTable|null $statement The CREATE TABLE statement that declares the table as it is, which a change of the table starts from; null when none is known
     * @param list<Check> $checks The CHECK constraints, in the order SHOW CREATE TABLE writes them
     * @param list<ForeignKey> $foreignKeys The foreign keys, in the order the table declares them
     * @param Partitioning|null $partitioning How the rows are split into partitions, or null when the table is not partitioned
     * @param RowFormat $rowFormat The effective InnoDB format selected when this definition was stored
     */
    public function __construct(
        public readonly string $schema,
        public readonly string $name,
        public readonly array $columns,
        public readonly array $keys,
        public readonly Table $declaration,
        public readonly string $engine = 'InnoDB',
        public readonly string $collation = 'utf8mb4_0900_ai_ci',
        public readonly bool $temporary = false,
        public readonly string $comment = '',
        public readonly ?CreateTable $statement = null,
        public readonly array $checks = [],
        public readonly array $foreignKeys = [],
        public readonly ?Partitioning $partitioning = null,
        public readonly RowFormat $rowFormat = RowFormat::Dynamic,
    ) {
    }

    /**
     * Answers the same definition with other columns.
     *
     * @param list<ColumnDefinition> $columns
     */
    public function withColumns(array $columns): self
    {
        return new self($this->schema, $this->name, $columns, $this->keys, $this->declaration, $this->engine, $this->collation, $this->temporary, $this->comment, $this->statement, $this->checks, $this->foreignKeys, $this->partitioning, $this->rowFormat);
    }

    /**
     * Retains reported widths absent from CREATE TABLE syntax, and publishes
     * the same column domains to SQL Semantics without losing key identities.
     *
     * @param array<string, int> $widths The reported widths by lowercase column name
     */
    public function withColumnDisplays(array $widths): self
    {
        if ($widths === []) {
            return $this;
        }
        $columns = array_map(static fn (ColumnDefinition $column): ColumnDefinition => isset($widths[mb_strtolower($column->name)]) ? $column->withDomain($column->domain->withDisplay($widths[mb_strtolower($column->name)])) : $column, $this->columns);
        $declared = [];
        foreach ($this->declaration->columns as $position => $column) {
            $declared[mb_strtolower($column->name->value)] = isset($widths[mb_strtolower($column->name->value)]) ? new \SqlSemantics\Statement\Declaration\Column($column->name, $columns[$position]->domain->resolved(), $column->nullability, $column->generated) : $column;
        }
        $keys = array_map(static fn (\SqlSemantics\Statement\Declaration\Key $key): \SqlSemantics\Statement\Declaration\Key => new \SqlSemantics\Statement\Declaration\Key($key->primary, array_map(static fn (\SqlSemantics\Statement\Declaration\Column $column): \SqlSemantics\Statement\Declaration\Column => $declared[mb_strtolower($column->name->value)], $key->columns)), $this->declaration->keys);
        $original = $this->declaration;
        $declaration = new Table($original->name, $original->profile, array_values($declared), $original->implicit, $original->complete, $original->kind, $keys, $original->partitions);

        return new self($this->schema, $this->name, $columns, $this->keys, $declaration, $this->engine, $this->collation, $this->temporary, $this->comment, $this->statement, $this->checks, $this->foreignKeys, $this->partitioning, $this->rowFormat);
    }

    /**
     * Answers the same definition with other CHECK constraints and foreign keys.
     *
     * @param list<Check> $checks
     * @param list<ForeignKey> $foreignKeys
     */
    public function withConstraints(array $checks, array $foreignKeys): self
    {
        return new self($this->schema, $this->name, $this->columns, $this->keys, $this->declaration, $this->engine, $this->collation, $this->temporary, $this->comment, $this->statement, $checks, $foreignKeys, $this->partitioning, $this->rowFormat);
    }

    /**
     * Answers the same definition with another partitioning.
     */
    public function withPartitioning(?Partitioning $partitioning): self
    {
        return new self($this->schema, $this->name, $this->columns, $this->keys, $this->declaration, $this->engine, $this->collation, $this->temporary, $this->comment, $this->statement, $this->checks, $this->foreignKeys, $partitioning, $this->rowFormat);
    }

    /**
     * Keeps the engine's effective format when an operation changes only metadata.
     */
    public function withRowFormat(RowFormat $format): self
    {
        return new self($this->schema, $this->name, $this->columns, $this->keys, $this->declaration, $this->engine, $this->collation, $this->temporary, $this->comment, $this->statement, $this->checks, $this->foreignKeys, $this->partitioning, $format);
    }

    /**
     * Answers the primary key, or null when the table has none.
     */
    public function primaryKey(): ?Key
    {
        foreach ($this->keys as $key) {
            if ($key->kind === KeyKind::Primary) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Answers the position of a column by name, compared without regard to case, or null.
     */
    public function position(string $name): ?int
    {
        foreach ($this->columns as $position => $column) {
            if (strcasecmp($column->name, $name) === 0) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Answers the position of the AUTO_INCREMENT column, or null when the table has none.
     */
    public function autoIncrementColumn(): ?int
    {
        foreach ($this->columns as $position => $column) {
            if ($column->autoIncrement) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Answers the order rows are read in by a full scan: the columns of the primary key, else of the first unique key over NOT NULL columns.
     *
     * @return list<int>
     */
    public function clusterColumns(): array
    {
        foreach ($this->keys as $key) {
            if ($key->kind === KeyKind::Primary) {
                return $key->columns;
            }
        }
        foreach ($this->keys as $key) {
            if ($key->kind !== KeyKind::Unique || in_array(true, array_map(fn (int $column): bool => $this->columns[$column]->nullable(), $key->columns), true) || array_filter($key->prefixes, static fn (?int $prefix): bool => $prefix !== null && $prefix !== 0) !== []) {
                continue;
            }

            return $key->columns;
        }

        return [];
    }

    /**
     * Answers the column definition flags a column of the table carries: its keys, AUTO_INCREMENT and default, and ZEROFILL for a YEAR column.
     */
    public function flags(int $position): int
    {
        $flags = 0;
        foreach ($this->keys as $key) {
            if (in_array($position, $key->columns, true)) {
                $flags |= ColumnFlag::PartKey->value;
            }
            if ($key->kind === KeyKind::Primary && in_array($position, $key->columns, true)) {
                $flags |= 2;
            } elseif ($key->kind === KeyKind::Unique && in_array($position, $key->columns, true)) {
                $flags |= 4;
            } elseif ($key->columns[0] === $position) {
                $flags |= 8;
            }
        }
        $column = $this->columns[$position];
        if ($column->autoIncrement) {
            $flags |= 512;
        } elseif (!$column->default->declared && !$column->nullable()) {
            $flags |= 4096;
        }
        if ($column->onUpdateNow) {
            $flags |= 8192;
        }
        if ($column->domain->field->blob()) {
            $flags |= ColumnFlag::Blob->value;
        }
        if ($column->domain->field === Field::Year || $this->zeroFill($position)) {
            $flags |= ColumnFlag::ZeroFill->value;
        }

        return $flags;
    }

    /**
     * Tells whether the numeric column was declared with ZEROFILL.
     */
    public function zeroFill(int $position): bool
    {
        foreach ($this->statement->elements ?? [] as $element) {
            if (!$element instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition || strcasecmp($element->name->column->value, $this->columns[$position]->name) !== 0) {
                continue;
            }
            $type = $element->specification->dataType();

            return ($type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Integral || $type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Decimal || $type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Floating)
                && in_array(\SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier::Zerofill, $type->modifiers, true);
        }

        return false;
    }

}
