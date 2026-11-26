<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\TableSource;

/**
 * Emits the ordered fields of the native mutation output scope.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ReturningSql
{
    /**
     * Writes unqualified target columns and their result labels.
     * @param list<ReturnedColumn> $returning Fields checked by the owning mutation
     */
    public function clause(TableSource $target, array $returning): string
    {
        $fields = [];
        foreach ($returning as $column) {
            $fields[] = $target->columns[$column->reference->slot]->quoted() . ' AS ' . $column->name->quoted();
        }

        return $fields === [] ? '' : ' RETURNING ' . implode(', ', $fields);
    }
}
