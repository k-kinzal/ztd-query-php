<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Schema\ColumnDeclaration;

/**
 * Transforms SQL statements using shadow table data.
 *
 * All implementations are stateless and domain-agnostic:
 * they receive table data as arguments and produce transformed SQL.
 *
 * @phpstan-import-type Row from StatementInterface
 */
interface SqlTransformer
{
    /**
     * Transform a SQL statement using the provided table context.
     *
     * @param string $sql The original SQL statement.
     * @param array<string, array{viewSql: string}|array{
     *     rows: list<Row>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, ColumnDeclaration>,
     *     primaryKeys?: array<int, string>,
     *     candidateKeys?: array<string, array<int, string>>,
     *     columnDefaults?: array<string, string>,
     *     identityStrategies?: array<string, \ZtdQuery\Schema\IdentityGenerationStrategy>,
     *     generatedExpressions?: array<string, string>,
     *     partitioning?: \ZtdQuery\Schema\TablePartitioning|null,
     *     sourceSql?: string,
     *     storageTable?: string
     * }> $tables Table name => shadow data and column information.
     * @return string The transformed SQL.
     */
    public function transform(string $sql, array $tables): string;
}
