<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Platform\IdentifierQuoter;

/**
 * Recomputes database-generated columns from a complete base-row projection.
 */
final class GeneratedColumnProjector
{
    private const SOURCE_ALIAS = '__ztd_generated_source';

    public function __construct(private readonly IdentifierQuoter $quoter)
    {
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
