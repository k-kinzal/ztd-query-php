<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Key;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

/**
 * Emits the key using the columns already checked by the owning declaration.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class PrimaryKeySql
{
    /**
     * Emits the clause attached to one column.
     */
    public function inline(PrimaryKey $key): string
    {
        return ' PRIMARY KEY' . ($key->terms[0]->descending ? ' DESC' : '') . $this->conflict($key->conflict);
    }

    /**
     * Emits the table constraint using the owning layout.
     * @param list<TableColumn> $columns The complete table's checked columns
     */
    public function table(PrimaryKey $key, array $columns): string
    {
        $terms = [];
        foreach ($key->terms as $term) {
            $collation = $term->collation === Collation::Binary ? '' : ' COLLATE ' . $term->collation->value;
            $terms[] = $columns[$term->slot]->name->quoted() . $collation . ($term->descending ? ' DESC' : '');
        }

        return 'PRIMARY KEY (' . implode(', ', $terms) . ')' . $this->conflict($key->conflict);
    }

    /**
     * Adds an explicit conflict algorithm when the constraint overrides ABORT.
     */
    public function conflict(KeyConflict $conflict): string
    {
        return $conflict === KeyConflict::Abort ? '' : ' ON CONFLICT ' . $conflict->value;
    }
}
