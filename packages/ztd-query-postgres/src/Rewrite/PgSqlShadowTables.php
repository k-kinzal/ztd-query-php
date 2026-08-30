<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Rewrite;

use ZtdQuery\Platform\Postgres\Dialect\PgSqlIdentifierQuoter;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Everything the shadow holds, in the form a transformer is handed.
 *
 * A transformer shadows a table by writing its rows out as a CTE, and to do
 * that it needs more than the rows: the columns they are written under, what
 * each column holds, and what identifies one row. Some of that is declared
 * and some can only be read off the rows, and a table may be declared with no
 * rows or hold rows nothing declared, so both are gone over.
 *
 * A partition holds no rows of its own: they belong to the table it
 * partitions, and what it holds of them is whatever its own condition
 * selects. A view is carried as the statement that defines it, and one whose
 * name a table has taken is left out, because the table is what that name now
 * means.
 *
 * @phpstan-import-type ShadowTables from SqlTransformer
 * @phpstan-import-type ShadowRows from SqlTransformer
 * @phpstan-import-type RenderableValue from SqlTransformer
 */
final class PgSqlShadowTables
{
    /**
     * @param ShadowStore $shadowStore What the shadow holds
     * @param TableDefinitionRegistry $registry What has been declared
     * @param PgSqlPartitionPredicateRenderer $predicates Writes what a partition holds of its parent
     * @param PgSqlIdentifierQuoter $quoter Writes a name as PostgreSQL would
     * @param PgSqlViewShadowRenderer $views Writes a view out as the statement defining it
     */
    public function __construct(
        private readonly ShadowStore $shadowStore,
        private readonly TableDefinitionRegistry $registry,
        private readonly PgSqlPartitionPredicateRenderer $predicates = new PgSqlPartitionPredicateRenderer(),
        private readonly PgSqlIdentifierQuoter $quoter = new PgSqlIdentifierQuoter(),
        private readonly PgSqlViewShadowRenderer $views = new PgSqlViewShadowRenderer(),
    ) {
    }

    /**
     * Answers everything the shadow holds.
     *
     * @param ViewDefinitionSet $viewDefinitions The views that have been declared
     *
     * @return ShadowTables Table name => what the shadow holds for it
     */
    public function of(ViewDefinitionSet $viewDefinitions): array
    {
        $context = [];
        foreach ($this->shadowStore->getAll() as $tableName => $rows) {
            $context[$tableName] = $this->heldFor($this->registry->get($tableName), $rows);
        }
        foreach ($this->registry->getAll() as $tableName => $definition) {
            if (!isset($context[$tableName])) {
                $context[$tableName] = $this->heldFor($definition, []);
            }
        }
        $context = $this->withPartitions($context);
        foreach ($this->views->render($viewDefinitions, array_keys($context)) as $viewName => $viewSql) {
            if (!isset($context[$viewName])) {
                $context[$viewName] = ['viewSql' => $viewSql];
            }
        }

        return $context;
    }

    /**
     * Answers what the shadow holds for one table.
     *
     * @param TableDefinition|null $definition What declared the table, or null where nothing did
     * @param list<array<string, RenderableValue>> $rows The rows the shadow holds
     *
     * @return ShadowRows What the shadow holds for it
     */
    public function heldFor(?TableDefinition $definition, array $rows): array
    {
        return [
            'rows' => $rows,
            'columns' => $definition->columns ?? $this->columnsAcross($rows),
            'columnTypes' => $definition->typedColumns ?? [],
            'columnDefaults' => $definition->columnDefaults ?? [],
            'identityStrategies' => $definition->identityStrategies ?? [],
            'generatedExpressions' => $definition->generatedExpressions ?? [],
            'primaryKeys' => $definition->primaryKeys ?? [],
            'candidateKeys' => $definition === null ? [] : $definition->candidateKeys()->keys(),
            'partialUniqueIndexes' => $definition->partialUniqueIndexes ?? [],
        ];
    }

    /**
     * Answers the columns rows are written under, where nothing declared them.
     *
     * @param list<array<string, RenderableValue>> $rows The rows the shadow holds
     *
     * @return list<string> The columns, in the order they were first written
     */
    public function columnsAcross(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * Answers what the shadow holds, with each partition reading its parent.
     *
     * @param ShadowTables $context Table name => what the shadow holds for it
     *
     * @return ShadowTables The same, with the partitions reading what is theirs
     */
    public function withPartitions(array $context): array
    {
        $definitions = $this->registry->getAll();
        foreach ($definitions as $tableName => $definition) {
            $relation = $definition->partitionRelation;
            $held = $context[$tableName] ?? null;
            if ($relation === null || $held === null) {
                continue;
            }
            $storageTable = $this->storageTable($tableName);
            $held['rows'] = $this->shadowStore->get($storageTable);
            $held['storageTable'] = $storageTable;
            $held['sourceSql'] = 'SELECT * FROM '
                . $this->quoter->quote($relation->parentTable)
                . ' WHERE '
                . $this->predicates->render($relation, $this->siblingPredicates($definitions, $relation->parentTable));
            $context[$tableName] = $held;
        }

        return $context;
    }

    /**
     * Answers what every partition of a table holds of it.
     *
     * A partition's own condition is written against what the others hold, so
     * ZTD has to know all of them to write what one of them selects.
     *
     * @param array<string, TableDefinition> $definitions What has been declared
     * @param string $parentTable The table being partitioned
     *
     * @return list<string> The conditions, as they were declared
     */
    public function siblingPredicates(array $definitions, string $parentTable): array
    {
        $predicates = [];
        foreach ($definitions as $definition) {
            $sibling = $definition->partitionRelation;
            if ($sibling !== null
                && strcasecmp($sibling->parentTable, $parentTable) === 0
                && $sibling->predicate !== null
            ) {
                $predicates[] = $sibling->predicate;
            }
        }

        return $predicates;
    }

    /**
     * Answers which table a partition's rows are actually held in.
     *
     * @param string $tableName Table it belongs to
     *
     * @return string The table the rows are in
     */
    public function storageTable(string $tableName): string
    {
        $seen = [];
        while (!in_array($tableName, $seen, true)) {
            $seen[] = $tableName;
            $parent = $this->registry->get($tableName)?->partitionRelation?->parentTable;
            if ($parent === null) {
                return $tableName;
            }
            $tableName = $parent;
        }

        return $tableName;
    }
}
