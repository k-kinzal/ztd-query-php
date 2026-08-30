<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Rewrite;

use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlIdentifierQuoter;

/**
 * Recomputes database-generated columns from a complete base-row projection.
 */
final class PgSqlGeneratedColumnProjector
{
    private const SOURCE_ALIAS = '__ztd_generated_source';

    private readonly IdentifierQuoter $quoter;

    /**
     * Binds the instance to what it will work from.
     *
     */
    public function __construct()
    {
        $this->quoter = new PgSqlIdentifierQuoter();
    }

    /**
     * @param array<int, string> $columns
     * @param array<string, string> $generatedExpressions
     */
    public function project(string $sourceSql, array $columns, array $generatedExpressions): string
    {
        if ($generatedExpressions === []) {
            return $sourceSql;
        }

        $sourceAlias = $this->quoter->quote(self::SOURCE_ALIAS);
        $selects = [];
        foreach ($columns as $column) {
            $quotedColumn = $this->quoter->quote($column);
            $expression = $generatedExpressions[$column] ?? null;
            $selects[] = $expression === null
                ? "$sourceAlias.$quotedColumn AS $quotedColumn"
                : "$expression AS $quotedColumn";
        }

        return 'SELECT ' . implode(', ', $selects) . " FROM ($sourceSql) AS $sourceAlias";
    }
}
