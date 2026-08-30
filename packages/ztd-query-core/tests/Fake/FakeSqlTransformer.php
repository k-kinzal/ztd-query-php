<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\TableDefinition;

/**
 * Fake SqlTransformer that injects CTEs for shadow tables.
 *
 * Generates simplified CTE syntax using double-quoted identifiers
 * and generic CAST expressions.
 *
 * @phpstan-import-type Row from TableDefinition
 * @phpstan-import-type ShadowRows from SqlTransformer
 */
final class FakeSqlTransformer implements SqlTransformer
{
    private FakeCastRenderer $castRenderer;

    private FakeIdentifierQuoter $quoter;

    /**
     * Binds the instance to what it will work from.
     *
     */
    public function __construct()
    {
        $this->castRenderer = new FakeCastRenderer();
        $this->quoter = new FakeIdentifierQuoter();
    }

    /**
     * Transform.
     *
     * @param string $sql
     * @return string
     */
    public function transform(string $sql, array $tables): string
    {
        if ($tables === []) {
            return $sql;
        }

        $ctes = [];
        foreach ($tables as $tableName => $tableData) {
            if (isset($tableData['viewSql'])) {
                $ctes[] = $this->quoter->quote($tableName) . ' AS (' . $tableData['viewSql'] . ')';
                continue;
            }

            $ctes[] = $this->buildCte($tableName, $tableData);
        }

        return 'WITH ' . implode(', ', $ctes) . ' ' . $sql;
    }

    /**
     * @param ShadowRows $tableData
     */
    public function buildCte(string $tableName, array $tableData): string
    {
        $quotedName = $this->quoter->quote($tableName);
        $columns = $tableData['columns'];
        $rows = $tableData['rows'];
        $columnTypes = $tableData['columnTypes'];

        if ($rows === []) {
            $nullSelects = [];
            foreach ($columns as $col) {
                $type = $columnTypes[$col] ?? new ColumnDeclaration(\ZtdQuery\Schema\ColumnTypeFamily::TEXT, 'TEXT');
                $nullSelects[] = $this->castRenderer->renderNullCast($type) . ' AS ' . $this->quoter->quote($col);
            }

            return $quotedName . ' AS (SELECT ' . implode(', ', $nullSelects) . ' WHERE FALSE)';
        }

        $rowSelects = [];
        foreach ($rows as $i => $row) {
            $colSelects = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? null;
                $type = $columnTypes[$col] ?? new ColumnDeclaration(\ZtdQuery\Schema\ColumnTypeFamily::TEXT, 'TEXT');

                if ($value === null) {
                    $expr = $this->castRenderer->renderNullCast($type);
                } else {
                    if (is_string($value)) {
                        $literal = "'" . str_replace("'", "''", $value) . "'";
                    } elseif (is_bool($value)) {
                        $literal = $value ? '1' : '0';
                    } else {
                        $literal = (string) $value;
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
