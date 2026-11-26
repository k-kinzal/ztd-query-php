<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

/**
 * Emits named and ordered fields of the affected target row.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ReturningSql
{
    /**
     * Writes the checked column occurrences with explicit output names.
     *
     * @param list<ReturnedColumn> $returning Fields scoped by the owning mutation
     */
    public function clause(TableSource $target, array $returning): string
    {
        $fields = [];
        foreach ($returning as $field) {
            $fields[] = $target->columns[$field->reference->slot]->quoted() . ' AS ' . $field->name->quoted();
        }

        return $fields === [] ? '' : ' RETURNING ' . implode(', ', $fields);
    }
}
