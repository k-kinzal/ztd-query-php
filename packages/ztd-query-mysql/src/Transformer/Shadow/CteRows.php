<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Shadow;

use RuntimeException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector;
use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Serializes arbitrary fixture rows through the public ValueRenderer contract into a table CTE.
 *
 * @visibility public
 * @example Render a fixture table without accessing a database
 *     $rows = new \ZtdQuery\Platform\MySql\Transformer\Shadow\CteRows(
 *         new \ZtdQuery\Platform\MySql\MySqlCastRenderer(),
 *         new \ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector(),
 *         new \ZtdQuery\Platform\MySql\MySqlIdentifierQuoter(),
 *         new \ZtdQuery\Platform\MySql\MySqlValueRenderer());
 *     $rows->generateCte('users', [['id' => 1]], ['id'], [], []) // => '`users` AS (SELECT CAST(1 AS SIGNED) AS `id`)'
 */
final class CteRows
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private CastRenderer $castRenderer, private MySqlGeneratedColumnProjector $generatedColumnProjector, private IdentifierQuoter $quoter, private ValueRenderer $valueRenderer)
    {
    }
    /**
     * Generate a CTE fragment for a single table.
     *
     * @param string $tableName
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $columns
     * @param array<string, ColumnType> $columnTypes
     * @param array<string, string> $generatedExpressions
     * @return string
     * @throws RuntimeException
     */
    public function generateCte(
        string $tableName,
        array $rows,
        array $columns,
        array $columnTypes,
        array $generatedExpressions,
    ): string {
        $quotedTable = $this->quoter->quote($tableName);

        if ($rows === []) {
            if ($columns === []) {
                throw new RuntimeException("Cannot shadow table '$tableName' with empty data (columns unknown).");
            }
            return $this->wrapCte($quotedTable, $this->emptySelect($columns, $columnTypes), $columns, $generatedExpressions);
        }
        $ctes = [];
        foreach ($rows as $row) {
            $selects = [];
            foreach ($columns !== [] ? $columns : array_keys($row) as $column) {
                $value = $this->formatValue($row[$column] ?? null, $columnTypes[$column] ?? null);
                $selects[] = $value . ' AS ' . $this->quoter->quote($column);
            }
            $ctes[] = 'SELECT ' . implode(', ', $selects);
        }
        return $this->wrapCte(
            $quotedTable,
            implode(' UNION ALL ', $ctes),
            $columns !== [] ? $columns : array_keys($rows[0]),
            $generatedExpressions,
        );
    }

    /**
     * @param array<int, string> $columns
     * @param array<string, string> $generatedExpressions
     */
    public function wrapCte(
        string $quotedTable,
        string $baseSql,
        array $columns,
        array $generatedExpressions,
    ): string {
        $sql = $this->generatedColumnProjector->project($baseSql, $columns, $generatedExpressions);

        return "$quotedTable AS ($sql)";
    }

    /**
     * Format Value for the supplied MySQL input.
     */
    public function formatValue(mixed $val, ?ColumnType $type = null): string
    {
        if ($type !== null
            && $val !== null
            && is_scalar($val)
            && $type->family === ColumnTypeFamily::STRING
            && str_starts_with(strtoupper($type->nativeType), 'SET(')
        ) {
            $val = (new \ZtdQuery\Platform\MySql\Transformer\Set\ValueNormalizer())->normalizeSetValue((string) $val, $type->nativeType);
        }

        return $this->valueRenderer->renderValue($val, $type);
    }
    /**
     * @param array<int, string> $columns
     * @param array<string, ColumnType> $columnTypes
     */
    public function emptySelect(array $columns, array $columnTypes): string
    {
        $selects = [];
        foreach ($columns as $column) {
            $type = $columnTypes[$column] ?? new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR');
            $selects[] = $this->castRenderer->renderNullCast($type) . ' AS ' . $this->quoter->quote($column);
        }
        return 'SELECT ' . implode(', ', $selects) . ' FROM DUAL WHERE 0';
    }

}
