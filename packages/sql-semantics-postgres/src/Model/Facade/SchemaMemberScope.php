<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Facade;

use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
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
        foreach ($provided as $table) {
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

        return new AnalysisContext(
            $outer->profile,
            $path,
            $tables,
            $outer->complete,
            $outer->relationNames,
            $outer->columnNames,
            $schema,
            $outer->indexNamespace,
            $outer->currentDatabase,
            $outer->tablespaces,
            $outer->roles,
        );
    }
}
