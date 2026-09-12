<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use RuntimeException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\MySqlCteShadowComposer;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\UpdateAssignmentExtractor;
use ZtdQuery\Platform\MySql\UpdateSourceExtractor;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Shadow\Mutation\MultiTableMutationTarget;

/**
 * Transforms UPDATE statements into SELECT projections with CTE shadowing.
 */
final class UpdateTransformer implements SqlTransformer
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
        if (!isset($statements[0]) || !$statements[0] instanceof UpdateStatement) {
            throw new UnsupportedSqlException($sql, 'Expected UPDATE statement');
        }

        if (preg_match('/\bPARTITION\s*\(([^)]+)\)/i', $sql) === 1) {
            throw new UnsupportedSqlException($sql, 'PARTITION clause not supported');
        }

        $statement = $statements[0];

        $targetTable = (new Update\TargetProjection())->targetTable($statement, $sql);

        $columns = $tables[$targetTable]['columns'] ?? [];

        $primaryKeys = $tables[$targetTable]['primaryKeys'] ?? [];
        $assignmentValues = (new UpdateAssignmentExtractor())->values($sql);
        $whereExpression = (new DmlWhereClauseExtractor())->extract($sql);
        $sourceExpression = (new UpdateSourceExtractor())->extract($sql);
        $projection = $this->buildProjection(
            $statement,
            $columns,
            $primaryKeys,
            [],
            $assignmentValues,
            $whereExpression,
            $sourceExpression,
        );
        $targetTableNames = array_keys($projection['tables']);
        if (isset($targetTableNames[1])) {
            $targets = (new Update\TargetProjection())->targetsFromContexts($projection['tables'], $tables);
            $projection = $this->buildProjection(
                $statement,
                $columns,
                $primaryKeys,
                $targets,
                $assignmentValues,
                $whereExpression,
                $sourceExpression,
            );
        }

        return $this->selectTransformer->transform(
            $this->cteComposer->carryPrefix($sql, $projection['sql']),
            $tables,
        );
    }



    /**
     * Build a result-select SQL from an UPDATE statement.
     *
     * @param UpdateStatement $stmt
     * @param array<int, string> $columns
     * @param array<int, string> $primaryKeys
     * @param list<MultiTableMutationTarget> $targets
     * @param list<string> $assignmentValues
     * @return array{sql: string, table: string, tables: array<string, array{alias: string}>}
     * @throws RuntimeException
     */
    public function buildProjection(
        UpdateStatement $stmt,
        array $columns,
        array $primaryKeys = [],
        array $targets = [],
        array $assignmentValues = [],
        ?string $whereExpression = null,
        ?string $sourceExpression = null,
    ): array {
        return (new Update\ResultSelect())->buildProjection($stmt, $columns, $primaryKeys, $targets, $assignmentValues, $whereExpression, $sourceExpression);
    }

}
