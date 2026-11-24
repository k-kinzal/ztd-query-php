<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Facade;

use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexNamespace;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;

/**
 * The transient resolution scope for one CREATE SCHEMA member.
 *
 * @visibility SqlSemantics
 */
final class SchemaMemberScope
{
    /**
     * Selects the containing namespace and adds the preceding table declarations.
     *
     * @param list<Table> $provided
     */
    public function bind(AnalysisContext $outer, Name $schema, array $provided): AnalysisContext
    {
        $tables = $outer->tables;
        $created = [];
        foreach ($provided as $table) {
            $coordinate = $table->name->name->value;
            if (($table->name->schema->value ?? $schema->value) === $schema->value) {
                if (isset($created[$coordinate])) {
                    continue;
                }
                $created[$coordinate] = true;
            }
            if (!in_array($table, $tables, true)) {
                $tables[] = $table;
            }
        }
        $path = [];
        $placed = false;
        foreach ($outer->searchPath as $searched) {
            if (!$placed && $searched->value !== 'pg_temp' && $searched->value !== 'pg_catalog') {
                $path[] = $schema;
                $placed = true;
            }
            $path[] = $searched;
        }
        if (!$placed) {
            $path[] = $schema;
        }

        $names = $outer->indexNamespace;
        $namespace = new IndexNamespace([...$names->schemas, $schema], $names->indexes, $names->opaque, $names->partitionedTables);

        return new AnalysisContext(
            $outer->profile,
            $path,
            $tables,
            $outer->complete,
            $outer->relationNames,
            $outer->columnNames,
            $schema,
            $namespace,
            $outer->currentDatabase,
            $outer->tablespaces,
            $outer->roles,
        );
    }
}
