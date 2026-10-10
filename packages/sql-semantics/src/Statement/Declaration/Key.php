<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * A key of a declared table: its primary key, or a unique key, over columns of the table.
 *
 * A primary key determines every column of a row; so does a unique key whose columns are all
 * NOT NULL. A unique key over a column that can be NULL admits several rows with NULL in it.
 *
 * @visibility public
 * @example Reading the primary key of a table
 *     $table = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT PRIMARY KEY, b INT)')->declarations()[0];
 *     [$table->keys[0]->primary, $table->keys[0]->columns[0]->name->value] // => [true, 'a']
 */
final class Key
{
    use Snapshot;

    /**
     * @var list<Column> The columns of the key, in key order
     */
    public readonly array $columns;

    /**
     * @param bool $primary Whether the key is the primary key
     * @param list<Column> $columns The columns of the key, in key order; at least one
     */
    public function __construct(public readonly bool $primary, array $columns)
    {
        $this->columns = Check::listOf($columns, Column::class, 'A key covers at least one column of its table.', 1);
    }

    /**
     * Tells whether the key determines a whole row: it is the primary key, or no column of it can be NULL.
     */
    public function determines(): bool
    {
        if ($this->primary) {
            return true;
        }
        foreach ($this->columns as $column) {
            if ($column->nullability !== \SqlSemantics\Statement\Type\Nullability::NotNull) {
                return false;
            }
        }

        return true;
    }
}
