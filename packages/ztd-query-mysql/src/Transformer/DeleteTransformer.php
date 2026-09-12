<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use RuntimeException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlCteShadowComposer;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Shadow\Mutation\MultiTableMutationTarget;

/**
 * Transforms DELETE statements into SELECT projections with CTE shadowing.
 */
final class DeleteTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private SelectTransformer $selectTransformer;
    private MySqlCteShadowComposer $cteComposer;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(
        MySqlParser $parser,
        SelectTransformer $selectTransformer,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->cteComposer = new MySqlCteShadowComposer();
    }

    /**
     * {@inheritDoc}
     * @throws UnsupportedSqlException
     */
    public function transform(string $sql, array $tables): string
    {
        $statements = $this->parser->parse($sql);
        if (!isset($statements[0]) || !$statements[0] instanceof DeleteStatement) {
            throw new UnsupportedSqlException($sql, 'Expected DELETE statement');
        }

        $statement = $statements[0];

        $targetTable = null;
        if ($statement->from !== null && $statement->from !== []) {
            $targetExpr = $statement->from[0];
            $targetTable = \ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::table($targetExpr);
        }

        $columnNames = [];
        if ($targetTable !== null && isset($tables[$targetTable]['columns'])) {
            $columnNames = $tables[$targetTable]['columns'];
        }

        $projection = $this->buildProjection($statement, $sql, $columnNames);
        $targetTableNames = array_keys($projection['tables']);
        if (isset($targetTableNames[1])) {
            $targets = (new Delete\TargetProjection())->targetsFromContexts($projection['tables'], $tables);
            $projection = $this->buildProjection($statement, $sql, $columnNames, $targets);
        }

        return $this->selectTransformer->transform(
            $this->cteComposer->carryPrefix($sql, $projection['sql']),
            $tables,
        );
    }

    /**
     * Build a result-select SQL and resolve the target table(s).
     *
     * @param DeleteStatement $stmt
     * @param string $originalSql
     * @param array<int, string> $columns
     * @param list<MultiTableMutationTarget> $targets
     * @return array{sql: string, table: string, tables: array<string, array{alias: string}>}
     * @throws RuntimeException
     */
    public function buildProjection(DeleteStatement $stmt, string $originalSql, array $columns, array $targets = []): array
    {
        return (new Delete\ResultSelect())->buildProjection($stmt, $originalSql, $columns, $targets);
    }

}
