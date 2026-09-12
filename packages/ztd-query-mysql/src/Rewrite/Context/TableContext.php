<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite\Context;

use ZtdQuery\Platform\MySql\MySqlCteShadowComposer;
use ZtdQuery\Platform\MySql\MySqlSelectRelationParser;
use ZtdQuery\Platform\MySql\MySqlViewShadowRenderer;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Snapshots fixture values and schema metadata into the public transformer context contract.
 *
 * @visibility public
 * @example Discover columns from fixture rows
 *     $store = new \ZtdQuery\Shadow\ShadowStore();
 *     $store->set('users', [['id' => 1]]);
 *     $tableContext = new \ZtdQuery\Platform\MySql\Rewrite\Context\TableContext(
 *         new \ZtdQuery\Platform\MySql\MySqlCteShadowComposer(),
 *         new \ZtdQuery\Schema\TableDefinitionRegistry(),
 *         $store,
 *         new \ZtdQuery\Schema\ViewDefinitionSet());
 *     $tableContext->buildTableContext()['users']['columns'] // => ['id']
 */
final class TableContext
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private MySqlCteShadowComposer $cteComposer, private TableDefinitionRegistry $registry, private ShadowStore $shadowStore, private ViewDefinitionSet $views)
    {
    }
    /**
     * Build the table context map for transformers.
     *
     * @return array<string, array{viewSql: string}|array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, \ZtdQuery\Schema\ColumnType>,
     *     columnDefaults: array<string, string>,
     *     identityStrategies: array<string, \ZtdQuery\Schema\IdentityGenerationStrategy>,
     *     generatedExpressions: array<string, string>,
     *     partitioning: \ZtdQuery\Schema\TablePartitioning|null
     * }>
     */
    public function buildTableContext(): array
    {
        $context = [];
        $allData = $this->shadowStore->getAll();
        foreach ($this->registry->getAll() as $tableName => $definition) {
            $allData[$tableName] ??= [];
        }

        foreach ($allData as $tableName => $rows) {
            $definition = $this->registry->get($tableName);
            $columns = $definition?->columns;
            if ($columns === null && $rows !== []) {
                $columns = array_keys($rows[0]);
                foreach ($rows as $row) {
                    foreach (array_keys($row) as $column) {
                        if (!in_array($column, $columns, true)) {
                            $columns[] = $column;
                        }
                    }
                }
            }


            $context[$tableName] = [
                'rows' => $rows,
                'columns' => $columns ?? [],
                'columnTypes' => $definition->typedColumns ?? [],
                'columnDefaults' => $definition->columnDefaults ?? [],
                'identityStrategies' => $definition->identityStrategies ?? [],
                'generatedExpressions' => $definition->generatedExpressions ?? [],
                'partitioning' => $definition?->partitioning,
                'primaryKeys' => $definition !== null ? $definition->primaryKeys : [],
                'candidateKeys' => $definition !== null ? $definition->candidateKeys()->keys() : [],
            ];
        }

        foreach ((new MySqlViewShadowRenderer())->render($this->views, array_keys($context)) as $viewName => $viewSql) {
            if (isset($context[$viewName])) {
                continue;
            }
            $context[$viewName] = ['viewSql' => $viewSql];
        }

        return $context;
    }

    /**
     * Find Unknown Table for the supplied MySQL input.
     */
    public function findUnknownTable(string $sql): ?string
    {
        $tableNames = (new MySqlSelectRelationParser())->tableNames($sql);
        $declaredCtes = array_fill_keys($this->cteComposer->declaredCteNames($sql), true);

        foreach ($tableNames as $tableName) {
            if (isset($declaredCtes[strtolower($tableName)])) {
                continue;
            }
            if (!$this->tableExists($tableName)) {
                return $tableName;
            }
        }

        return null;
    }

    /**
     * Table Exists for the supplied MySQL input.
     */
    public function tableExists(string $tableName): bool
    {
        if ($this->shadowStore->has($tableName)) {
            return true;
        }

        if ($this->registry->has($tableName)) {
            return true;
        }

        if ($this->views->has($tableName)) {
            return true;
        }

        return false;
    }

    /**
     * Has Schema Context for the supplied MySQL input.
     */
    public function hasSchemaContext(): bool
    {
        if ($this->shadowStore->getAll() !== []) {
            return true;
        }

        if ($this->registry->hasAnyTables()) {
            return true;
        }

        if ($this->views->hasAnyViews()) {
            return true;
        }

        return false;
    }
    /**
     * Reject references to unknown tables when the session has schema context.
     *
     * @throws \ZtdQuery\Exception\UnknownSchemaException
     */
    public function requireKnownTables(string $sql): void
    {
        if ($this->hasSchemaContext()) {
            $unknownTable = $this->findUnknownTable($sql);
            if ($unknownTable !== null) {
                throw new \ZtdQuery\Exception\UnknownSchemaException($sql, $unknownTable, 'table');
            }
        }

    }

}
