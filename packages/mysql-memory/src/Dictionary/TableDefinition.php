<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use MySqlMemory\Result\ColumnFlag;
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
    ) {
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
        if ($column->domain->field === Field::Year) {
            $flags |= ColumnFlag::ZeroFill->value;
        }

        return $flags;
    }
}
