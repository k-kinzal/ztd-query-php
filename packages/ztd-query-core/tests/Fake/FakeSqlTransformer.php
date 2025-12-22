<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnType;

/**
 * Fake SqlTransformer that injects CTEs for shadow tables.
 *
 * Generates simplified CTE syntax using double-quoted identifiers
 * and generic CAST expressions.
 */
final class FakeSqlTransformer implements SqlTransformer
{
    private FakeCastRenderer $castRenderer;

    private FakeIdentifierQuoter $quoter;

    public function __construct()
    {
        $this->castRenderer = new FakeCastRenderer();
        $this->quoter = new FakeIdentifierQuoter();
    }

    public function transform(string $sql, array $tables): string
    {
        if ($tables === []) {
            return $sql;
        }

        $ctes = [];
        foreach ($tables as $tableName => $tableData) {
            $ctes[] = $this->buildCte($tableName, $tableData);
        }

        return 'WITH ' . implode(', ', $ctes) . ' ' . $sql;
    }

    /**
     * @param array{rows: array<int, array<string, mixed>>, columns: array<int, string>, columnTypes: array<string, ColumnType>} $tableData
     */
    private function buildCte(string $tableName, array $tableData): string
    {
        $quotedName = $this->quoter->quote($tableName);
        $columns = $tableData['columns'];
        $rows = $tableData['rows'];
        $columnTypes = $tableData['columnTypes'];

        if ($rows === []) {
            $nullSelects = [];
            foreach ($columns as $col) {
                $type = $columnTypes[$col] ?? new ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::TEXT, 'TEXT');
                $nullSelects[] = $this->castRenderer->renderNullCast($type) . ' AS ' . $this->quoter->quote($col);
            }

            return $quotedName . ' AS (SELECT ' . implode(', ', $nullSelects) . ' WHERE FALSE)';
        }

        $rowSelects = [];
        foreach ($rows as $i => $row) {
            $colSelects = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? null;
                $type = $columnTypes[$col] ?? new ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::TEXT, 'TEXT');

                if ($value === null) {
                    $expr = $this->castRenderer->renderNullCast($type);
                } else {
                    if (is_string($value)) {
                        $literal = "'" . str_replace("'", "''", $value) . "'";
                    } elseif (is_int($value) || is_float($value)) {
                        $literal = (string) $value;
                    } elseif (is_bool($value)) {
                        $literal = $value ? '1' : '0';
                    } else {
                        $literal = "'" . str_replace("'", "''", (string) json_encode($value)) . "'";
                    }
                    $expr = $this->castRenderer->renderCast($literal, $type);
                }

                if ($i === 0) {
                    $colSelects[] = $expr . ' AS ' . $this->quoter->quote($col);
                } else {
                    $colSelects[] = $expr;
                }
            }
            $rowSelects[] = 'SELECT ' . implode(', ', $colSelects);
        }

        return $quotedName . ' AS (' . implode(' UNION ALL ', $rowSelects) . ')';
    }
}
