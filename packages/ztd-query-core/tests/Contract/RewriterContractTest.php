<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Abstract contract test for SqlRewriter implementations.
 *
 * Each platform provides a concrete subclass with platform-specific SQL fixtures.
 * Enforces contracts defined in quality-standards.md Section 1.1 and Section 5.1.
 */
abstract class RewriterContractTest extends TestCase
{
    abstract protected function createRewriter(ShadowStore $store, TableDefinitionRegistry $registry): SqlRewriter;

    abstract protected function createSchemaParser(): SchemaParser;

    abstract protected function selectSql(): string;

    abstract protected function insertSql(): string;

    abstract protected function updateSql(): string;

    abstract protected function deleteSql(): string;

    abstract protected function createTableSql(): string;

    abstract protected function dropTableSql(): string;

    abstract protected function unsupportedSql(): string;

    abstract protected function usersCreateTableSql(): string;

    /**
     * Build a rewriter pre-loaded with the users table schema.
     */
    protected function buildRewriter(?ShadowStore $store = null, ?TableDefinitionRegistry $registry = null): SqlRewriter
    {
        $store = $store ?? new ShadowStore();
        $registry = $registry ?? new TableDefinitionRegistry();

        $parser = $this->createSchemaParser();
        $definition = $parser->parse($this->usersCreateTableSql());
        if ($definition !== null) {
            $registry->register('users', $definition);
        }

        return $this->createRewriter($store, $registry);
    }

    /**
     * SELECT queries must return READ kind.
     */
    public function testSelectReturnsReadKind(): void
    {
        $rewriter = $this->buildRewriter();
        $plan = $rewriter->rewrite($this->selectSql());

        self::assertSame(QueryKind::READ, $plan->kind());
    }

    /**
     * INSERT queries must return WRITE_SIMULATED with an InsertMutation (P-RW-1, P-RW-2).
     */
    public function testInsertReturnsWriteSimulatedWithMutation(): void
    {
        $rewriter = $this->buildRewriter();
        $plan = $rewriter->rewrite($this->insertSql());

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(InsertMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
    }

    /**
     * UPDATE queries must return WRITE_SIMULATED with an UpdateMutation (P-RW-1, P-RW-2).
     */
    public function testUpdateReturnsWriteSimulatedWithMutation(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);

        $rewriter = $this->buildRewriter($store);
        $plan = $rewriter->rewrite($this->updateSql());

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(UpdateMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
    }

    /**
     * DELETE queries must return WRITE_SIMULATED with a DeleteMutation (P-RW-1, P-RW-2).
     */
    public function testDeleteReturnsWriteSimulatedWithMutation(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);

        $rewriter = $this->buildRewriter($store);
        $plan = $rewriter->rewrite($this->deleteSql());

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(DeleteMutation::class, $plan->mutation());
        self::assertSame('users', $plan->mutation()->tableName());
    }

    /**
     * CREATE TABLE must return DDL_SIMULATED kind.
     */
    public function testCreateTableReturnsDdlSimulated(): void
    {
        $rewriter = $this->buildRewriter();
        $plan = $rewriter->rewrite($this->createTableSql());

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
    }

    /**
     * DROP TABLE must return DDL_SIMULATED kind.
     */
    public function testDropTableReturnsDdlSimulated(): void
    {
        $rewriter = $this->buildRewriter();
        $plan = $rewriter->rewrite($this->dropTableSql());

        self::assertSame(QueryKind::DDL_SIMULATED, $plan->kind());
    }

    /**
     * Unsupported SQL must throw UnsupportedSqlException.
     */
    public function testUnsupportedSqlThrowsException(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        $rewriter = $this->buildRewriter();
        $rewriter->rewrite($this->unsupportedSql());
    }

    /**
     * Empty input must throw UnsupportedSqlException.
     */
    public function testEmptyInputThrowsException(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        $rewriter = $this->buildRewriter();
        $rewriter->rewrite('');
    }

    /**
     * Rewrite must be deterministic: same input, same output (P-RW-6).
     */
    public function testRewriteIsDeterministic(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);

        $rewriter = $this->buildRewriter($store);

        $plan1 = $rewriter->rewrite($this->selectSql());
        $plan2 = $rewriter->rewrite($this->selectSql());

        self::assertSame($plan1->sql(), $plan2->sql());
        self::assertSame($plan1->kind(), $plan2->kind());
    }

    /**
     * READ plans must have no mutation (P-RW-3).
     */
    public function testReadPlanHasNoMutation(): void
    {
        $rewriter = $this->buildRewriter();
        $plan = $rewriter->rewrite($this->selectSql());

        self::assertSame(QueryKind::READ, $plan->kind());
        self::assertNull($plan->mutation());
    }

    /**
     * WRITE_SIMULATED plans must have a non-null InsertMutation for INSERT (P-RW-2).
     */
    public function testWritePlanHasNonNullMutation(): void
    {
        $rewriter = $this->buildRewriter();
        $plan = $rewriter->rewrite($this->insertSql());

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertInstanceOf(InsertMutation::class, $plan->mutation());
    }

    /**
     * Rewrite output SQL must be non-empty and contain SELECT for read queries (P-RW-4).
     */
    public function testRewriteOutputIsNonEmpty(): void
    {
        $rewriter = $this->buildRewriter();
        $plan = $rewriter->rewrite($this->selectSql());

        self::assertNotEmpty($plan->sql());
        self::assertStringContainsString('SELECT', strtoupper($plan->sql()));
    }

    /**
     * INSERT rewrite output SQL must be a SELECT (result-select pattern) (P-RW-5).
     */
    public function testInsertRewriteOutputContainsSelect(): void
    {
        $rewriter = $this->buildRewriter();
        $plan = $rewriter->rewrite($this->insertSql());

        self::assertSame(QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertMatchesRegularExpression(
            '/^(?:WITH\b|SELECT\b)/i',
            $plan->sql(),
            'INSERT rewrite must produce a result-select query starting with SELECT or WITH...SELECT'
        );
    }
}
