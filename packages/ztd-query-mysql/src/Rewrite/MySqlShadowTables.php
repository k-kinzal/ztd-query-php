<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

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
 * A view is carried as the statement that defines it rather than as rows,
 * because a view has none of its own, and one whose name a table has taken is
 * left out, because the table is what that name now means.
 *
 * @phpstan-import-type ShadowTables from SqlTransformer
 * @phpstan-import-type ShadowRows from SqlTransformer
 * @phpstan-import-type RenderableValue from SqlTransformer
 */
final class MySqlShadowTables
{
    /**
     * @param ShadowStore $shadowStore What the shadow holds
     * @param TableDefinitionRegistry $registry What has been declared
     * @param MySqlViewShadowRenderer $views Writes a view out as the statement defining it
     */
    public function __construct(
        private readonly ShadowStore $shadowStore,
        private readonly TableDefinitionRegistry $registry,
        private readonly MySqlViewShadowRenderer $views = new MySqlViewShadowRenderer(),
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
            'partitioning' => $definition?->partitioning,
            'primaryKeys' => $definition->primaryKeys ?? [],
            'candidateKeys' => $definition === null ? [] : $definition->candidateKeys()->keys(),
        ];
    }

    /**
     * Answers the columns rows are written under, where nothing declared them.
     *
     * Rows the shadow holds need not agree on their columns, so every row is
     * gone over rather than only the first.
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
}
