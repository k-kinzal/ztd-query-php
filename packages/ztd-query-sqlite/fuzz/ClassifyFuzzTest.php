<?php

declare(strict_types=1);

namespace Fuzz;

use Faker\Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlFaker\SqliteProvider;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Platform\Sqlite\SqliteQueryGuard;
use ZtdQuery\Rewrite\QueryKind;

/**
 * Fuzz tests for SqliteQueryGuard::classify().
 *
 * Guards the following properties:
 * - INV-L1-01: classify() never throws on any input
 * - INV-L1-02: classify() is deterministic (same input -> same output)
 * - Kind correctness: SELECT->READ, INSERT/UPDATE/DELETE->WRITE_SIMULATED, DDL->DDL_SIMULATED
 */
#[CoversNothing]
#[Large]
final class ClassifyFuzzTest extends TestCase
{
    private const ITERATIONS = 100;

    private SqliteQueryGuard $guard;

    private SqliteProvider $provider;

    #[Override]
    protected function setUp(): void
    {
        $this->guard = new SqliteQueryGuard(new SqliteParser());
        $faker = Factory::create();
        $this->provider = new SqliteProvider($faker);
        $faker->seed(20260815);
    }

    /**
     * INV-L1-01: classify() must never throw on any generated SQL.
     * INV-L1-02: classify() must be deterministic (same SQL -> same result).
     */
    public function testClassifyNeverThrowsAndIsDeterministic(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->sql(maxDepth: 8);
            $result1 = $this->guard->classify($sql);
            $result2 = $this->guard->classify($sql);
            self::assertSame($result1, $result2, "classify() returned different results for the same SQL on iteration $i: $sql");
        }
        self::addToAssertionCount(self::ITERATIONS);
    }

    /**
     * Test classify select returns read or null.
     *
     */
    public function testClassifySelectReturnsReadOrNull(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->selectStatement(maxDepth: 8);
            $result = $this->guard->classify($sql);
            if ($result !== null) {
                self::assertSame(
                    QueryKind::READ,
                    $result,
                    "SELECT should classify as READ on iteration $i with SQL: $sql"
                );
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }

    /**
     * Test classify insert returns write simulated or null.
     *
     */
    public function testClassifyInsertReturnsWriteSimulatedOrNull(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->insertStatement(maxDepth: 8);
            $result = $this->guard->classify($sql);
            if ($result !== null) {
                self::assertSame(
                    QueryKind::WRITE_SIMULATED,
                    $result,
                    "INSERT should classify as WRITE_SIMULATED on iteration $i with SQL: $sql"
                );
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }

    /**
     * Test classify update returns write simulated or null.
     *
     */
    public function testClassifyUpdateReturnsWriteSimulatedOrNull(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->updateStatement(maxDepth: 8);
            $result = $this->guard->classify($sql);
            if ($result !== null) {
                self::assertSame(
                    QueryKind::WRITE_SIMULATED,
                    $result,
                    "UPDATE should classify as WRITE_SIMULATED on iteration $i with SQL: $sql"
                );
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }

    /**
     * Test classify delete returns write simulated or null.
     *
     */
    public function testClassifyDeleteReturnsWriteSimulatedOrNull(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->deleteStatement(maxDepth: 8);
            $result = $this->guard->classify($sql);
            if ($result !== null) {
                self::assertSame(
                    QueryKind::WRITE_SIMULATED,
                    $result,
                    "DELETE should classify as WRITE_SIMULATED on iteration $i with SQL: $sql"
                );
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }

    /**
     * Test classify create table returns ddl simulated or null.
     *
     */
    public function testClassifyCreateTableReturnsDdlSimulatedOrNull(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->createTableStatement(maxDepth: 5);
            $result = $this->guard->classify($sql);
            if ($result !== null) {
                self::assertSame(
                    QueryKind::DDL_SIMULATED,
                    $result,
                    "CREATE TABLE should classify as DDL_SIMULATED on iteration $i with SQL: $sql"
                );
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }

    /**
     * Test classify drop table returns ddl simulated or null.
     *
     */
    public function testClassifyDropTableReturnsDdlSimulatedOrNull(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->dropTableStatement(maxDepth: 3);
            $result = $this->guard->classify($sql);
            if ($result !== null) {
                self::assertSame(
                    QueryKind::DDL_SIMULATED,
                    $result,
                    "DROP TABLE should classify as DDL_SIMULATED on iteration $i with SQL: $sql"
                );
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }

    /**
     * Test classify alter table returns ddl simulated or null.
     *
     */
    public function testClassifyAlterTableReturnsDdlSimulatedOrNull(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sql = $this->provider->alterTableStatement(maxDepth: 5);
            $result = $this->guard->classify($sql);
            if ($result !== null) {
                self::assertSame(
                    QueryKind::DDL_SIMULATED,
                    $result,
                    "ALTER TABLE should classify as DDL_SIMULATED on iteration $i with SQL: $sql"
                );
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }
}
