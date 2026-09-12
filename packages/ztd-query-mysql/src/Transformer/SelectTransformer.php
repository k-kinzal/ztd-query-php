<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlCteShadowComposer;
use ZtdQuery\Platform\MySql\MySqlFullTextSearchRewriter;
use ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlPartitionSelectionRewriter;
use ZtdQuery\Platform\MySql\MySqlTypeSemantics;
use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Rewrite\SqlTransformer;

/**
 * Applies CTE shadowing to SELECT statements.
 *
 * Generates WITH clauses that shadow referenced tables using in-memory data,
 * rewrites SET column ORDER BY for correct bit-order ranking.
 */
final class SelectTransformer implements SqlTransformer
{
    private CastRenderer $castRenderer;
    private IdentifierQuoter $quoter;
    private ValueRenderer $valueRenderer;
    private MySqlTypeSemantics $typeSemantics;
    private MySqlCteShadowComposer $cteComposer;
    private MySqlGeneratedColumnProjector $generatedColumnProjector;
    private MySqlPartitionSelectionRewriter $partitionSelectionRewriter;
    private MySqlFullTextSearchRewriter $fullTextSearchRewriter;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(
        ?CastRenderer $castRenderer = null,
        ?IdentifierQuoter $quoter = null,
        ?ValueRenderer $valueRenderer = null,
    ) {
        $this->castRenderer = $castRenderer ?? new MySqlCastRenderer();
        $this->quoter = $quoter ?? new MySqlIdentifierQuoter();
        $this->valueRenderer = $valueRenderer ?? new \ZtdQuery\Platform\MySql\MySqlValueRenderer($this->castRenderer);
        $this->typeSemantics = new MySqlTypeSemantics();
        $this->cteComposer = new MySqlCteShadowComposer();
        $this->generatedColumnProjector = new MySqlGeneratedColumnProjector();
        $this->partitionSelectionRewriter = new MySqlPartitionSelectionRewriter();
        $this->fullTextSearchRewriter = new MySqlFullTextSearchRewriter();
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        if (stripos($sql, 'PARTITION') !== false) {
            $sql = $this->partitionSelectionRewriter->rewrite($sql, $tables);
        }
        $sql = $this->typeSemantics->rewrite($sql, $tables);
        $sql = (new Set\OrderRewriter())->rewriteSetOrderBy($sql, $tables);
        $sql = $this->fullTextSearchRewriter->rewrite($sql);

        $ctes = [];
        foreach ($tables as $tableName => $tableContext) {
            if (isset($tableContext['viewSql'])) {
                $ctes[$tableName] = $this->quoter->quote($tableName) . " AS ({$tableContext['viewSql']})";
                continue;
            }

            $rows = $tableContext['rows'];
            $columns = $tableContext['columns'];
            $columnTypes = $tableContext['columnTypes'];
            $generatedExpressions = $tableContext['generatedExpressions'] ?? [];

            if ($columns === [] && $rows !== []) {
                $columns = array_keys($rows[0]);
                foreach ($rows as $row) {
                    foreach (array_keys($row) as $column) {
                        if (!in_array($column, $columns, true)) {
                            $columns[] = $column;
                        }
                    }
                }
            }

            if ($columns === [] && $rows === []) {
                continue;
            }

            $ctes[$tableName] = (new Shadow\CteRows($this->castRenderer, $this->generatedColumnProjector, $this->quoter, $this->valueRenderer))->generateCte(
                $tableName,
                $rows,
                $columns,
                $columnTypes,
                $generatedExpressions,
            );
        }

        return $this->cteComposer->compose($sql, $ctes);
    }

}
