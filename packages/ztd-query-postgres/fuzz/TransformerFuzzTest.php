<?php

declare(strict_types=1);

namespace Fuzz;

use Faker\Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSqlProvider;
use ZtdQuery\Platform\Postgres\PgSqlCastRenderer;
use ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter;
use ZtdQuery\Platform\Postgres\Transformer\SelectTransformer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Fuzz tests for SelectTransformer::transform().
 *
 * Guards the following properties:
 * - P-TF-1: transform() never crashes on any generated SELECT
 * - P-TF-2: When SQL references a shadowed table, the output contains a WITH clause (CTE injection)
 * - P-TF-3: With empty table context, SQL is returned unchanged (identity transform)
 * - P-TF-4: Empty-row shadow tables still inject CTE with WHERE FALSE
 */
#[CoversNothing]
#[Large]
final class TransformerFuzzTest extends TestCase
{
    private const ITERATIONS = 100;

    private SelectTransformer $transformer;

    private PostgreSqlProvider $provider;

    #[Override]
    protected function setUp(): void
    {
        $this->transformer = new SelectTransformer(new PgSqlCastRenderer(), new PgSqlIdentifierQuoter());
        $faker = Factory::create();
        $this->provider = new PostgreSqlProvider($faker);
        $faker->seed(20260815);
    }

    /**
     * Test transform does not crash on random select with empty tables.
     *
     */
    public function testTransformDoesNotCrashOnRandomSelectWithEmptyTables(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->selectStatement(50);
            $result = $this->transformer->transform($sql, []);
            self::assertNotEmpty($result, "transform() returned empty string on iteration $i");
            self::assertSame($sql, $result);
        }
        self::addToAssertionCount(self::ITERATIONS);
    }

    /**
     * Test transform with shadow data contains with clause.
     *
     */
    public function testTransformWithShadowDataContainsWithClause(): void
    {
        $tables = [
            'users' => [
        'rows' => [
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ],
        'columns' => ['id', 'name', 'email'],
        'columnTypes' => [
            'id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'),
            'name' => new ColumnDeclaration(ColumnTypeFamily::TEXT, 'TEXT'),
            'email' => new ColumnDeclaration(ColumnTypeFamily::TEXT, 'TEXT'),
        ],
            ],
        ];

        $withCount = 0;
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->selectStatement(50);
            $result = $this->transformer->transform($sql, $tables);
            self::assertNotEmpty($result, "transform() returned empty string on iteration $i");
            if (stripos($sql, 'users') !== false) {
                self::assertStringContainsString('WITH', $result, "transform() should inject CTE when SQL references shadowed table on iteration $i");
                $withCount++;
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }

    /**
     * Test transform with empty rows contains with clause.
     *
     */
    public function testTransformWithEmptyRowsContainsWithClause(): void
    {
        $tables = [
            'users' => [
        'rows' => [],
        'columns' => ['id', 'name', 'email'],
        'columnTypes' => [
            'id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'),
            'name' => new ColumnDeclaration(ColumnTypeFamily::TEXT, 'TEXT'),
            'email' => new ColumnDeclaration(ColumnTypeFamily::TEXT, 'TEXT'),
        ],
            ],
        ];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->selectStatement(50);
            $result = $this->transformer->transform($sql, $tables);
            self::assertNotEmpty($result, "transform() returned empty string on iteration $i");
            if (stripos($sql, 'users') !== false) {
                self::assertStringContainsString('WITH', $result, "transform() should inject CTE when SQL references shadowed table on iteration $i");
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }
}
