<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Key;

use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

/**
 * Emits the checked columns and key clauses in declaration order.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableKeySql
{
    /**
     * Emits inline clauses on their own columns and appends the table clauses.
     *
     * @param non-empty-list<TableColumn> $columns
     * @param list<PrimaryKey|UniqueKey> $keys
     * @return list<string>
     */
    public function declarations(array $columns, array $keys): array
    {
        $inline = [];
        $table = [];
        foreach ($keys as $key) {
            if ($key->inline) {
                $slot = $key->terms[0]->slot;
                $inline[$slot] = ($inline[$slot] ?? '') . $this->inline($key);
            } else {
                $table[] = $this->table($key, $columns);
            }
        }

        $parts = [];
        foreach ($columns as $slot => $column) {
            $parts[] = $column->toString() . ($inline[$slot] ?? '');
        }

        return [...$parts, ...$table];
    }

    /**
     * Emits the clause attached to one column.
     */
    public function inline(PrimaryKey|UniqueKey $key): string
    {
        $conflict = $this->conflict($key->conflict);
        if ($key instanceof UniqueKey) {
            return ' UNIQUE' . $conflict;
        }

        return ' PRIMARY KEY' . ($key->terms[0]->descending ? ' DESC' : '') . $conflict
            . ($key->autoIncrement ? ' AUTOINCREMENT' : '');
    }

    /**
     * Emits the table constraint using the owning layout.
     *
     * @param non-empty-list<TableColumn> $columns
     */
    public function table(PrimaryKey|UniqueKey $key, array $columns): string
    {
        $terms = [];
        foreach ($key->terms as $term) {
            $column = $columns[$term->slot];
            $collation = $term->collation === $column->collation ? '' : ' COLLATE ' . $term->collation->value;
            $terms[] = $column->name->quoted() . $collation . ($term->descending ? ' DESC' : '');
        }
        $keyword = $key instanceof PrimaryKey ? 'PRIMARY KEY' : 'UNIQUE';
        $auto = $key instanceof PrimaryKey && $key->autoIncrement ? ' AUTOINCREMENT' : '';

        return $keyword . ' (' . implode(', ', $terms) . $auto . ')' . $this->conflict($key->conflict);
    }

    /**
     * Emits the explicit algorithm, including ABORT when written.
     */
    public function conflict(?KeyConflict $conflict): string
    {
        return $conflict === null ? '' : ' ON CONFLICT ' . $conflict->value;
    }
}
