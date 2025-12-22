<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Abstract contract test for ShadowMutation implementations.
 *
 * Tests universal mutation properties that must hold for any platform.
 * Enforces contracts defined in quality-standards.md Section 1.2 and properties P-SM-1 through P-SM-6.
 */
abstract class MutationContractTest extends TestCase
{
    /**
     * Create initial rows to seed the shadow store for testing.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function initialRows(): array;

    /**
     * Create rows to insert for testing.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function insertRows(): array;

    /**
     * Create rows representing a delete result set (rows that were deleted).
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function deleteRows(): array;

    /**
     * Create rows representing an update result set (rows after update).
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function updateRows(): array;

    /**
     * Return the primary key column names for the test table.
     *
     * @return array<int, string>
     */
    abstract protected function primaryKeys(): array;

    /**
     * Return the table name used in tests.
     */
    protected function tableName(): string
    {
        return 'users';
    }

    /**
     * Return a different table name for isolation tests.
     */
    protected function otherTableName(): string
    {
        return 'orders';
    }

    /**
     * InsertMutation increases row count by at most count(rows) (P-SM-1).
     */
    public function testInsertIncreasesRowCount(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), $this->initialRows());
        $countBefore = count($store->get($this->tableName()));

        $insertRows = $this->insertRows();
        $mutation = new InsertMutation($this->tableName());
        $mutation->apply($store, $insertRows);

        $countAfter = count($store->get($this->tableName()));

        self::assertGreaterThanOrEqual($countBefore, $countAfter);
        self::assertLessThanOrEqual($countBefore + count($insertRows), $countAfter);
    }

    /**
     * DeleteMutation decreases row count by at most count(rows) (P-SM-2).
     */
    public function testDeleteDecreasesRowCount(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), $this->initialRows());
        $countBefore = count($store->get($this->tableName()));

        $deleteRows = $this->deleteRows();
        $mutation = new DeleteMutation($this->tableName(), $this->primaryKeys());
        $mutation->apply($store, $deleteRows);

        $countAfter = count($store->get($this->tableName()));

        self::assertLessThanOrEqual($countBefore, $countAfter + count($deleteRows));
        self::assertLessThanOrEqual($countBefore, $countAfter + count($deleteRows));
        self::assertGreaterThanOrEqual(0, $countAfter);
    }

    /**
     * UpdateMutation preserves row count (P-SM-3).
     */
    public function testUpdatePreservesRowCount(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), $this->initialRows());
        $countBefore = count($store->get($this->tableName()));

        $updateRows = $this->updateRows();
        $mutation = new UpdateMutation($this->tableName(), $this->primaryKeys());
        $mutation->apply($store, $updateRows);

        $countAfter = count($store->get($this->tableName()));

        self::assertSame($countBefore, $countAfter);
    }

    /**
     * TruncateMutation empties the table (P-SM-4).
     */
    public function testTruncateEmptiesTable(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), $this->initialRows());

        self::assertNotEmpty($store->get($this->tableName()));

        $mutation = new TruncateMutation($this->tableName());
        $mutation->apply($store, []);

        self::assertSame([], $store->get($this->tableName()));
    }

    /**
     * Any mutation on table T must not modify store.get(T') for T' != T (P-SM-5).
     */
    public function testMutationTableIsolation(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), $this->initialRows());

        $otherRows = [['id' => 100, 'product' => 'Widget']];
        $store->set($this->otherTableName(), $otherRows);

        $mutation = new InsertMutation($this->tableName());
        $mutation->apply($store, $this->insertRows());

        self::assertSame($otherRows, $store->get($this->otherTableName()));

        $deleteMutation = new DeleteMutation($this->tableName(), $this->primaryKeys());
        $deleteMutation->apply($store, $this->deleteRows());

        self::assertSame($otherRows, $store->get($this->otherTableName()));

        $truncateMutation = new TruncateMutation($this->tableName());
        $truncateMutation->apply($store, []);

        self::assertSame($otherRows, $store->get($this->otherTableName()));
    }

    /**
     * tableName() must return the same value across multiple calls.
     */
    public function testTableNameIsConsistent(): void
    {
        $mutation = new InsertMutation($this->tableName());

        $name1 = $mutation->tableName();
        $name2 = $mutation->tableName();
        $name3 = $mutation->tableName();

        self::assertSame($name1, $name2);
        self::assertSame($name2, $name3);
    }

    /**
     * Applying TruncateMutation twice yields the same result as once (P-SM-6).
     */
    public function testIdempotentTruncate(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), $this->initialRows());

        $mutation = new TruncateMutation($this->tableName());
        $mutation->apply($store, []);

        self::assertSame([], $store->get($this->tableName()));

        $mutation->apply($store, []);

        self::assertSame([], $store->get($this->tableName()));
    }

    /**
     * InsertMutation with NULL values in rows must not crash.
     */
    public function testInsertWithNullValues(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), []);

        $rowsWithNull = [
            ['id' => 10, 'name' => null, 'email' => 'test@example.com'],
        ];

        $mutation = new InsertMutation($this->tableName());
        $mutation->apply($store, $rowsWithNull);

        $stored = $store->get($this->tableName());
        self::assertCount(1, $stored);
        self::assertNull($stored[0]['name']);
    }

    /**
     * InsertMutation with empty string values must preserve them.
     */
    public function testInsertWithEmptyStringValues(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), []);

        $rowsWithEmpty = [
            ['id' => 10, 'name' => '', 'email' => 'test@example.com'],
        ];

        $mutation = new InsertMutation($this->tableName());
        $mutation->apply($store, $rowsWithEmpty);

        $stored = $store->get($this->tableName());
        self::assertCount(1, $stored);
        self::assertSame('', $stored[0]['name']);
    }

    /**
     * InsertMutation into empty table must work.
     */
    public function testInsertIntoEmptyTable(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), []);

        $mutation = new InsertMutation($this->tableName());
        $mutation->apply($store, $this->insertRows());

        $stored = $store->get($this->tableName());
        self::assertCount(count($this->insertRows()), $stored);
    }

    /**
     * DeleteMutation on empty table must not crash.
     */
    public function testDeleteOnEmptyTable(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), []);

        $mutation = new DeleteMutation($this->tableName(), $this->primaryKeys());
        $mutation->apply($store, $this->deleteRows());

        self::assertSame([], $store->get($this->tableName()));
    }

    /**
     * UpdateMutation on empty table must not crash.
     */
    public function testUpdateOnEmptyTable(): void
    {
        $store = new ShadowStore();
        $store->set($this->tableName(), []);

        $mutation = new UpdateMutation($this->tableName(), $this->primaryKeys());
        $mutation->apply($store, $this->updateRows());

        self::assertSame(0, count($store->get($this->tableName())));
    }
}
