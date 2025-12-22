<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Abstract contract test for SqlTransformer implementations.
 *
 * Enforces contracts for the SqlTransformer interface:
 * - P-TF-1: Empty table context returns original SQL unchanged.
 * - P-TF-2: CTE-injected SQL starts with WITH.
 * - P-TF-3: Table names appear as CTE names in transformed output.
 * - P-TF-4: Transform is deterministic.
 * - P-TF-5: Output is always non-empty.
 */
abstract class TransformerContractTest extends TestCase
{
    abstract protected function createTransformer(): SqlTransformer;

    /**
     * A valid SELECT statement referencing the "users" table.
     */
    abstract protected function selectSql(): string;

    /**
     * Empty table context must return the original SQL unchanged (P-TF-1).
     */
    public function testEmptyTableContextReturnsOriginalSql(): void
    {
        $transformer = $this->createTransformer();
        $sql = $this->selectSql();

        $result = $transformer->transform($sql, []);

        self::assertSame($sql, $result);
    }

    /**
     * When table context contains data, the result must start with WITH (P-TF-2).
     */
    public function testCteInjectedSqlStartsWithWith(): void
    {
        $transformer = $this->createTransformer();
        $sql = $this->selectSql();
        $tables = $this->singleRowTableContext();

        $result = $transformer->transform($sql, $tables);

        self::assertStringStartsWith('WITH', ltrim($result));
    }

    /**
     * Table name must appear as a CTE name in the transformed output (P-TF-3).
     */
    public function testTableNameUsedAsCte(): void
    {
        $transformer = $this->createTransformer();
        $sql = $this->selectSql();
        $tables = $this->singleRowTableContext();

        $result = $transformer->transform($sql, $tables);

        self::assertStringContainsString('users', $result);
        self::assertStringContainsString('SELECT', strtoupper($result));
    }

    /**
     * Transform must be deterministic: same inputs produce identical outputs (P-TF-4).
     */
    public function testTransformIsDeterministic(): void
    {
        $transformer = $this->createTransformer();
        $sql = $this->selectSql();
        $tables = $this->singleRowTableContext();

        $result1 = $transformer->transform($sql, $tables);
        $result2 = $transformer->transform($sql, $tables);

        self::assertSame($result1, $result2);
    }

    /**
     * Transform output must always be non-empty (P-TF-5).
     */
    public function testTransformOutputIsNonEmpty(): void
    {
        $transformer = $this->createTransformer();
        $sql = $this->selectSql();
        $tables = $this->singleRowTableContext();

        $result = $transformer->transform($sql, $tables);

        self::assertNotEmpty($result);
    }

    /**
     * CTE-injected output must contain SELECT and UNION ALL structure for data rows (P-TF-3).
     */
    public function testCteContainsSelectUnionStructure(): void
    {
        $transformer = $this->createTransformer();
        $sql = $this->selectSql();
        $tables = [
            'users' => [
                'rows' => [
                    ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
                    ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
                ],
                'columns' => ['id', 'name', 'email'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, $this->nativeIntegerType()),
                    'name' => new ColumnType(ColumnTypeFamily::STRING, $this->nativeStringType()),
                    'email' => new ColumnType(ColumnTypeFamily::STRING, $this->nativeStringType()),
                ],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        $upper = strtoupper($result);

        self::assertTrue(
            str_contains($upper, 'UNION ALL') || str_contains($upper, 'VALUES'),
            'Multiple data rows should produce UNION ALL or VALUES in the CTE, got: ' . $result
        );
        self::assertStringContainsString('SELECT', $upper);
        self::assertStringContainsString(' AS ', $upper, 'CTE must contain AS keyword');
    }

    /**
     * CTE output must contain CAST expressions for typed columns.
     */
    public function testCteContainsCastExpressions(): void
    {
        $transformer = $this->createTransformer();
        $sql = $this->selectSql();
        $tables = $this->singleRowTableContext();

        $result = $transformer->transform($sql, $tables);
        $upper = strtoupper($result);

        self::assertStringContainsString('CAST(', $upper, 'CTE output must contain CAST expressions for typed columns');
    }

    /**
     * Transform with empty rows but known columns should still produce valid output.
     */
    public function testEmptyRowsWithColumnsReturnsWithClause(): void
    {
        $transformer = $this->createTransformer();
        $sql = $this->selectSql();
        $tables = $this->emptyRowsTableContext();

        $result = $transformer->transform($sql, $tables);

        self::assertStringStartsWith('WITH', ltrim($result));
        self::assertNotEmpty($result);
    }

    /**
     * Build a single-row table context for the "users" table.
     *
     * @return array<string, array{rows: array<int, array<string, mixed>>, columns: array<int, string>, columnTypes: array<string, ColumnType>}>
     */
    protected function singleRowTableContext(): array
    {
        return [
            'users' => [
                'rows' => [
                    ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
                ],
                'columns' => ['id', 'name', 'email'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, $this->nativeIntegerType()),
                    'name' => new ColumnType(ColumnTypeFamily::STRING, $this->nativeStringType()),
                    'email' => new ColumnType(ColumnTypeFamily::STRING, $this->nativeStringType()),
                ],
            ],
        ];
    }

    /**
     * Build an empty-rows table context for the "users" table (columns known, no data).
     *
     * @return array<string, array{rows: array<int, array<string, mixed>>, columns: array<int, string>, columnTypes: array<string, ColumnType>}>
     */
    protected function emptyRowsTableContext(): array
    {
        return [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name', 'email'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, $this->nativeIntegerType()),
                    'name' => new ColumnType(ColumnTypeFamily::STRING, $this->nativeStringType()),
                    'email' => new ColumnType(ColumnTypeFamily::STRING, $this->nativeStringType()),
                ],
            ],
        ];
    }

    /**
     * Return the platform-specific native type for INTEGER.
     */
    protected function nativeIntegerType(): string
    {
        return 'INTEGER';
    }

    /**
     * Return the platform-specific native type for VARCHAR/STRING.
     */
    protected function nativeStringType(): string
    {
        return 'VARCHAR(255)';
    }
}
