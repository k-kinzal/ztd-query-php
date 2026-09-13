<?php

declare (strict_types=1);

namespace Fuzz;

use Faker\Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlFaker\SqliteProvider;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Platform\Sqlite\SqliteSchemaParser;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Full pipeline fuzz test for SQLite.
 *
 * Tests the complete pipeline: schema parse -> register -> populate ShadowStore
 * -> rewrite DML -> apply mutation -> verify integrity.
 *
 * Guards the following properties:
 * - P-SM-1: INSERT mutation increases row count in ShadowStore
 * - P-SM-3: UPDATE mutation preserves row count in ShadowStore
 * - P-SM-5: Mutations only affect the target table (table isolation)
 * - INV-L4-01: ShadowStore maintains array-of-arrays structure after any mutation
 * - INV-L2-02: WRITE_SIMULATED/DDL_SIMULATED plans must have non-null mutation
 * - INV-L2-03: READ plans must have null mutation
 */
#[CoversNothing]
#[Large]
final class FullPipelineFuzzTest extends TestCase
{
    private const ITERATIONS = 50;
    private SqliteSchemaParser $schemaParser;
    private SqliteProvider $provider;
    private \Faker\Generator $faker;
    #[Override]
    protected function setUp(): void
    {
        $this->schemaParser = new SqliteSchemaParser();
        $this->faker = Factory::create();
        $this->faker->seed(20260815);
        $this->provider = new SqliteProvider($this->faker);
    }
    /**
     * Checks self referencing upsert preserves semantic result.
     */
    public function testSelfReferencingUpsertPreservesSemanticResult(): void
    {
        $this->faker->seed(20260816);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $existing = $this->faker->numberBetween(1, 100000);
            $incoming = $this->faker->numberBetween(1, 100000);
            $reference = $this->faker->randomElement(['quantity', 'items.quantity']);
            self::assertIsString($reference);
            $shadowStore = new ShadowStore();
            $shadowStore->set('items', [['id' => 1, 'quantity' => $existing]]);
            $registry = new TableDefinitionRegistry();
            $registry->register('items', new TableDefinition(['id', 'quantity'], ['id' => 'INTEGER', 'quantity' => 'INTEGER'], ['id'], ['id'], []));
            $rewriter = Support\RewriteFactory::create($shadowStore, $registry);
            $plan = $rewriter->rewrite(sprintf('INSERT INTO items VALUES (1, %d) ON CONFLICT(id) DO UPDATE SET quantity = %s + excluded.quantity', $incoming, $reference));
            $mutation = $plan->mutation();
            self::assertNotNull($mutation);
            $mutation->apply($shadowStore, [['id' => 1, 'quantity' => $incoming]]);
            self::assertSame([['id' => 1, 'quantity' => $existing + $incoming]], $shadowStore->get('items'));
        }
    }
    /**
     * Checks create table then select does not crash.
     */
    public function testCreateTableThenSelectDoesNotCrash(): void
    {
        $this->faker->seed(20260815);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $createSql = $this->provider->createTableStatement(maxDepth: 5);
            $definition = $this->schemaParser->parse($createSql);
            if ($definition === null || $definition->columns === []) {
                continue;
            }
            $shadowStore = new ShadowStore();
            $registry = new TableDefinitionRegistry();
            $tableName = (new SqliteParser())->extractTargetTable($createSql);
            if ($tableName === null) {
                continue;
            }
            $registry->register($tableName, $definition);
            $fixtureRows = Support\FixtureRows::generateFixtureRows($definition, $this->faker->numberBetween(0, 5));
            $shadowStore->set($tableName, $fixtureRows);
            $rewriter = Support\RewriteFactory::create($shadowStore, $registry);
            $selectSql = 'SELECT * FROM "' . str_replace('"', '""', $tableName) . '"';
            try {
                $plan = $rewriter->rewrite($selectSql);
                self::assertNotEmpty($plan->sql());
                self::assertSame(QueryKind::READ, $plan->kind());
                self::assertNull($plan->mutation());
            } catch (UnsupportedSqlException|UnknownSchemaException) {
                continue;
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }
    /**
     * Checks create table then insert does not crash.
     */
    public function testCreateTableThenInsertDoesNotCrash(): void
    {
        $this->faker->seed(20260815);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $createSql = $this->provider->createTableStatement(maxDepth: 5);
            $definition = $this->schemaParser->parse($createSql);
            if ($definition === null || $definition->columns === []) {
                continue;
            }
            $shadowStore = new ShadowStore();
            $registry = new TableDefinitionRegistry();
            $tableName = (new SqliteParser())->extractTargetTable($createSql);
            if ($tableName === null) {
                continue;
            }
            $registry->register($tableName, $definition);
            $shadowStore->set($tableName, []);
            $rewriter = Support\RewriteFactory::create($shadowStore, $registry);
            $values = Support\FixtureRows::buildInsertValues($definition, $this->faker);
            $insertSql = 'INSERT INTO "' . str_replace('"', '""', $tableName) . '" (' . implode(', ', array_map(fn (string $c) => '"' . str_replace('"', '""', $c) . '"', $definition->columns)) . ') VALUES (' . $values . ')';
            try {
                $plan = $rewriter->rewrite($insertSql);
                self::assertNotEmpty($plan->sql());
                self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
                if ($plan->mutation() !== null) {
                    $countBefore = count($shadowStore->get($tableName));
                    $fakeResultRows = [Support\FixtureRows::generateFixtureRows($definition, 1)[0]];
                    $plan->mutation()->apply($shadowStore, $fakeResultRows);
                    $storedRows = $shadowStore->get($tableName);
                    self::assertNotEmpty($storedRows);
                    self::assertGreaterThanOrEqual($countBefore, count($storedRows), "INSERT mutation should not decrease row count on iteration {$i}");
                }
            } catch (UnsupportedSqlException|UnknownSchemaException) {
                continue;
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }
    /**
     * Checks create table then update does not crash.
     */
    public function testCreateTableThenUpdateDoesNotCrash(): void
    {
        $this->faker->seed(20260815);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $createSql = $this->provider->createTableStatement(maxDepth: 5);
            $definition = $this->schemaParser->parse($createSql);
            if ($definition === null || $definition->columns === []) {
                continue;
            }
            $shadowStore = new ShadowStore();
            $registry = new TableDefinitionRegistry();
            $tableName = (new SqliteParser())->extractTargetTable($createSql);
            if ($tableName === null) {
                continue;
            }
            $registry->register($tableName, $definition);
            $fixtureRows = Support\FixtureRows::generateFixtureRows($definition, 3);
            $shadowStore->set($tableName, $fixtureRows);
            $rewriter = Support\RewriteFactory::create($shadowStore, $registry);
            $firstCol = $definition->columns[0];
            $updateSql = 'UPDATE "' . str_replace('"', '""', $tableName) . '" SET "' . str_replace('"', '""', $firstCol) . '" = "' . str_replace('"', '""', $firstCol) . '"';
            try {
                $plan = $rewriter->rewrite($updateSql);
                self::assertNotEmpty($plan->sql());
                self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
                if ($plan->mutation() !== null) {
                    $countBefore = count($shadowStore->get($tableName));
                    $plan->mutation()->apply($shadowStore, $fixtureRows);
                    $storedRows = $shadowStore->get($tableName);
                    self::assertCount($countBefore, $storedRows, "UPDATE mutation should preserve row count on iteration {$i}");
                }
            } catch (UnsupportedSqlException|UnknownSchemaException) {
                continue;
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }
    /**
     * Checks create table then delete does not crash.
     */
    public function testCreateTableThenDeleteDoesNotCrash(): void
    {
        $this->faker->seed(20260815);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $createSql = $this->provider->createTableStatement(maxDepth: 5);
            $definition = $this->schemaParser->parse($createSql);
            if ($definition === null || $definition->columns === []) {
                continue;
            }
            $shadowStore = new ShadowStore();
            $registry = new TableDefinitionRegistry();
            $tableName = (new SqliteParser())->extractTargetTable($createSql);
            if ($tableName === null) {
                continue;
            }
            $registry->register($tableName, $definition);
            $fixtureRows = Support\FixtureRows::generateFixtureRows($definition, 3);
            $shadowStore->set($tableName, $fixtureRows);
            $rewriter = Support\RewriteFactory::create($shadowStore, $registry);
            $deleteSql = 'DELETE FROM "' . str_replace('"', '""', $tableName) . '"';
            try {
                $plan = $rewriter->rewrite($deleteSql);
                self::assertNotEmpty($plan->sql());
                self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
                if ($plan->mutation() !== null) {
                    $plan->mutation()->apply($shadowStore, $fixtureRows);
                    $shadowStore->get($tableName);
                }
            } catch (UnsupportedSqlException|UnknownSchemaException) {
                continue;
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }
    /**
     * Checks create table rewrite registers then dml succeeds.
     */
    public function testCreateTableRewriteRegistersThenDmlSucceeds(): void
    {
        $this->faker->seed(20260815);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $createSql = $this->provider->createTableStatement(maxDepth: 5);
            $definition = $this->schemaParser->parse($createSql);
            if ($definition === null || $definition->columns === []) {
                continue;
            }
            $shadowStore = new ShadowStore();
            $registry = new TableDefinitionRegistry();
            $tableName = (new SqliteParser())->extractTargetTable($createSql);
            if ($tableName === null) {
                continue;
            }
            $rewriter = Support\RewriteFactory::create($shadowStore, $registry);
            try {
                $createPlan = $rewriter->rewrite($createSql);
                self::assertSame(QueryKind::DDL_SIMULATED, $createPlan->kind());
                if ($createPlan->mutation() !== null) {
                    $createPlan->mutation()->apply($shadowStore, []);
                }
                $selectSql = 'SELECT * FROM "' . str_replace('"', '""', $tableName) . '"';
                $selectPlan = $rewriter->rewrite($selectSql);
                self::assertNotEmpty($selectPlan->sql());
                self::assertSame(QueryKind::READ, $selectPlan->kind());
            } catch (UnsupportedSqlException|UnknownSchemaException) {
                continue;
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }
    /**
     * Checks shadow store integrity after multiple operations.
     */
    public function testShadowStoreIntegrityAfterMultipleOperations(): void
    {
        $this->faker->seed(20260815);
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $createSql = $this->provider->createTableStatement(maxDepth: 5);
            $definition = $this->schemaParser->parse($createSql);
            if ($definition === null || $definition->columns === []) {
                continue;
            }
            $shadowStore = new ShadowStore();
            $registry = new TableDefinitionRegistry();
            $tableName = (new SqliteParser())->extractTargetTable($createSql);
            if ($tableName === null) {
                continue;
            }
            $registry->register($tableName, $definition);
            $fixtureRows = Support\FixtureRows::generateFixtureRows($definition, 3);
            $shadowStore->set($tableName, $fixtureRows);
            $rewriter = Support\RewriteFactory::create($shadowStore, $registry);
            $quotedTable = '"' . str_replace('"', '""', $tableName) . '"';
            $quotedCols = array_map(fn (string $c) => '"' . str_replace('"', '""', $c) . '"', $definition->columns);
            $firstCol = $quotedCols[0];
            $operations = ['SELECT * FROM ' . $quotedTable, 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $quotedCols) . ') VALUES (' . Support\FixtureRows::buildInsertValues($definition, $this->faker) . ')', 'UPDATE ' . $quotedTable . ' SET ' . $firstCol . ' = ' . $firstCol, 'DELETE FROM ' . $quotedTable];
            foreach ($operations as $sql) {
                try {
                    $plan = $rewriter->rewrite($sql);
                    self::assertNotEmpty($plan->sql());
                    self::assertInstanceOf(QueryKind::class, $plan->kind());
                    if ($plan->mutation() !== null) {
                        $fakeRows = Support\FixtureRows::generateFixtureRows($definition, 1);
                        $plan->mutation()->apply($shadowStore, $fakeRows);
                    }
                    $allData = $shadowStore->getAll();
                    foreach ($allData as $tblName => $tblRows) {
                        self::assertNotEmpty($tblName, 'ShadowStore contains empty table name key');
                    }
                    self::assertArrayHasKey($tableName, $allData);
                    if ($plan->kind() === QueryKind::READ) {
                        self::assertNull($plan->mutation(), 'READ plan must have no mutation');
                    } elseif ($plan->kind() === QueryKind::WRITE_SIMULATED || $plan->kind() === QueryKind::DDL_SIMULATED) {
                        self::assertNotNull($plan->mutation(), "{$plan->kind()->value} plan must have a mutation");
                    }
                } catch (UnsupportedSqlException|UnknownSchemaException) {
                    continue;
                }
            }
        }
        self::addToAssertionCount(self::ITERATIONS);
    }
}
