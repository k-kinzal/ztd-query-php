<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Transformer;

use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Platform\Sqlite\SqliteCastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteCteShadowComposer;
use ZtdQuery\Platform\Sqlite\SqliteFullTextSearchRewriter;
use ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector;
use ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter;
use ZtdQuery\Platform\Sqlite\SqliteIndexHintStripper;
use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Rewrite\SqlTransformer;

/**
 * Applies CTE shadowing to SELECT statements for SQLite.
 *
 * Generates WITH clauses that shadow referenced tables using in-memory data.
 * Uses double-quote identifiers and SQLite-compatible CAST types.
 */
final class SelectTransformer implements SqlTransformer
{
    private CastRenderer $castRenderer;
    private IdentifierQuoter $quoter;
    private ValueRenderer $valueRenderer;
    private SqliteCteShadowComposer $cteComposer;
    private SqliteIndexHintStripper $indexHintStripper;
    private SqliteGeneratedColumnProjector $generatedColumnProjector;
    private SqliteFullTextSearchRewriter $fullTextSearchRewriter;

    /**
     * Binds the dependencies used by this operation.
     */
    public function __construct(
        ?CastRenderer $castRenderer = null,
        ?IdentifierQuoter $quoter = null,
        ?ValueRenderer $valueRenderer = null,
    ) {
        $this->castRenderer = $castRenderer ?? new SqliteCastRenderer();
        $this->quoter = $quoter ?? new SqliteIdentifierQuoter();
        $this->valueRenderer = $valueRenderer ?? new \ZtdQuery\Platform\Sqlite\SqliteValueRenderer($this->castRenderer);
        $this->cteComposer = new SqliteCteShadowComposer();
        $this->indexHintStripper = new SqliteIndexHintStripper();
        $this->generatedColumnProjector = new SqliteGeneratedColumnProjector();
        $this->fullTextSearchRewriter = new SqliteFullTextSearchRewriter();
    }

    /**
     * {@inheritDoc}
     *
     * @visibility public
     * @example Read fixture rows through SQLite CTE shadowing
     *     $tables = ['users' => ['columns' => ['id', 'name'], 'columnTypes' => [], 'rows' => [['id' => 1, 'name' => 'Alice']]]];
     *     $sql = (new \ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer())->transform('SELECT name FROM users', $tables);
     *     $pdo = new \PDO('sqlite::memory:');
     *     $pdo->query($sql)->fetchColumn() // => 'Alice'
     */
    public function transform(string $sql, array $tables): string
    {
        $sql = $this->fullTextSearchRewriter->rewrite($sql, $tables);
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

            $ctes[$tableName] = (new Select\ShadowCteRenderer($this->castRenderer, $this->generatedColumnProjector, $this->quoter, $this->valueRenderer))->generateCte(
                $tableName,
                $rows,
                $columns,
                $columnTypes,
                $generatedExpressions,
            );
        }

        $sql = $this->indexHintStripper->strip($sql, array_keys($ctes));

        return $this->cteComposer->compose($sql, $ctes);
    }

}
