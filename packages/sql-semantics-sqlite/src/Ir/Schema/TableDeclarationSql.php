<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\TableKeySql;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\DefinitionSource;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;

/**
 * Composes a table definition from its columns and constraints.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableDeclarationSql
{
    /**
     * Emits inline constraints on their declared column and table constraints after the columns.
     * @param non-empty-list<TableColumn> $columns
     * @param list<PrimaryKey|UniqueKey> $keys
     * @param list<CheckConstraint> $checks
     * @return list<string>
     */
    public function declarations(array $columns, array $keys, array $checks): array
    {
        $inline = [];
        $table = [];
        $keySql = new TableKeySql();
        foreach ($keys as $key) {
            if ($key->inline) {
                $slot = $key->terms[0]->slot;
                $inline[$slot] = ($inline[$slot] ?? '') . $keySql->inline($key);
            } else {
                $table[] = $keySql->table($key, $columns);
            }
        }
        if ($checks !== []) {
            $names = [];
            foreach ($columns as $column) {
                $names[] = $column->name;
            }
            $source = new DefinitionSource($names);
            $expressions = new SelectSql();
            foreach ($checks as $check) {
                $sql = 'CONSTRAINT ' . $check->label->quoted() . ' CHECK (' . $expressions->expression($check->condition, $source) . ')';
                if ($check->column === null) {
                    $table[] = $sql;
                } else {
                    $inline[$check->column] = ($inline[$check->column] ?? '') . ' ' . $sql;
                }
            }
        }

        $parts = [];
        foreach ($columns as $slot => $column) {
            $parts[] = $column->toString() . ($inline[$slot] ?? '');
        }

        return [...$parts, ...$table];
    }
}
