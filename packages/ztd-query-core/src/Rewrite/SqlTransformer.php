<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Schema\ColumnType;

/**
 * Transforms SQL statements using shadow table data.
 *
 * All implementations are stateless and domain-agnostic:
 * they receive table data as arguments and produce transformed SQL.
 */
interface SqlTransformer
{
    /**
     * Transform a SQL statement using the provided table context.
     *
     * @param string $sql The original SQL statement.
     * @param array<string, array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, ColumnType>
     * }> $tables Table name => shadow data and column information.
     * @return string The transformed SQL.
     */
    public function transform(string $sql, array $tables): string;
}
