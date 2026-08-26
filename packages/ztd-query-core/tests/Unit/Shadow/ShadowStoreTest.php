<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\MissingPrimaryKeyException;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTableState;

#[CoversClass(ShadowStore::class)]
#[UsesClass(MissingPrimaryKeyException::class)]
#[UsesClass(ShadowTableState::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\RowMatch::class)]
class ShadowStoreTest extends TestCase
{
    public function testRestoreSnapshotRestoreSnapshotRestoreIncludesRowsAndInitializedPresence(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $snapshot = $store->snapshot();

        $store->insert('users', [['id' => 2]]);
        $store->set('temporary_table', []);
        $store->restore($snapshot);

        self::assertSame([['id' => 1]], $store->get('users'));
        self::assertFalse($store->has('temporary_table'));
    }

    public function testInsertAppendsRows(): void
    {
        $store = new ShadowStore();
        $store->insert('users', [['id' => 1, 'name' => 'Alice']]);
        $store->insert('users', [['id' => 2, 'name' => 'Bob']]);

        self::assertSame(
            [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']],
            $store->get('users')
        );
    }

    public function testGetReturnsEmptyArrayForMissingTable(): void
    {
        $store = new ShadowStore();

        self::assertSame([], $store->get('missing'));
    }

    public function testHasDistinguishesMissingFromIntentionallyEmptyTable(): void
    {
        $store = new ShadowStore();

        self::assertFalse($store->has('events'));

        $store->ensure('events');

        self::assertTrue($store->has('events'));
        self::assertSame([], $store->get('events'));
    }

    public function testStateDistinguishesMutationMaterializationFromInitialization(): void
    {
        $store = new ShadowStore();

        self::assertSame(ShadowTableState::Missing, $store->state('events'));

        $store->insert('events', [['id' => 1]]);
        self::assertSame(ShadowTableState::Materialized, $store->state('events'));

        $store->ensure('events');
        self::assertSame(ShadowTableState::Initialized, $store->state('events'));
    }

    public function testRemoveClearsRowsAndInitialization(): void
    {
        $store = new ShadowStore();
        $store->set('events', [['id' => 1]]);

        $store->remove('events');

        self::assertSame(ShadowTableState::Missing, $store->state('events'));
        self::assertSame([], $store->get('events'));
    }

    public function testGetAllReturnsCurrentTables(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $store->set('orders', [['id' => 10]]);

        self::assertSame(
            [
                'users' => [['id' => 1]],
                'orders' => [['id' => 10]],
            ],
            $store->getAll()
        );
    }

    public function testDeleteUsesPrimaryKeysWhenProvided(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $store->delete('users', [['id' => 1, 'name' => 'Alice']], ['id']);

        self::assertSame([['id' => 2, 'name' => 'Bob']], $store->get('users'));
    }

    public function testDeleteFallsBackToRowMatchWithoutPrimaryKeys(): void
    {
        $store = new ShadowStore();
        $store->set('logs', [
            ['id' => 1, 'payload' => 'A'],
            ['id' => 2, 'payload' => 'B'],
        ]);

        $store->delete('logs', [['id' => 2, 'payload' => 'B']]);

        self::assertSame([['id' => 1, 'payload' => 'A']], $store->get('logs'));
    }

    public function testUpdateReplacesMatchingRow(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $store->update('users', [['id' => 2, 'name' => 'Bobby']], ['id']);

        self::assertSame([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bobby'],
        ], $store->get('users'));
    }

    public function testUpdateWithoutPrimaryKeysThrows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $this->expectException(MissingPrimaryKeyException::class);
        $store->update('users', [['id' => 1, 'name' => 'Updated']], []);
    }

    public function testEnsureCreatesEmptyTable(): void
    {
        $store = new ShadowStore();
        $store->ensure('events');

        self::assertSame([], $store->get('events'));
    }

    public function testClearRemovesAllData(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $store->set('orders', [['id' => 10]]);

        $store->clear();

        self::assertSame([], $store->getAll());
    }

    public function testDeleteNoOpWhenTableMissing(): void
    {
        $store = new ShadowStore();
        $store->delete('missing', [['id' => 1]], ['id']);

        self::assertSame([], $store->get('missing'));
    }

    public function testUpdateNoOpWhenNoMatch(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $store->update('users', [['id' => 2, 'name' => 'Bob']], ['id']);

        self::assertSame([['id' => 1, 'name' => 'Alice']], $store->get('users'));
    }

    public function testSetKeepsTheRowsInOrderAndUnderNoKeysOfTheirOwn(): void
    {
        $store = new ShadowStore();

        $store->set('order', [4 => ['id' => 1], 9 => ['id' => 2]]);

        self::assertSame([['id' => 1], ['id' => 2]], $store->get('order'));
    }

    public function testSnapshotIsARecordOfTheRowsRatherThanAViewOfThem(): void
    {
        $store = new ShadowStore();
        $store->set('order', [['id' => 1]]);

        $snapshot = $store->snapshot();
        $store->set('order', []);

        self::assertSame([['id' => 1]], $snapshot->get('order'));
    }

    public function testUpdateIdentifiedWritesTheRowTheOldKeyPointsAt(): void
    {
        $store = new ShadowStore();
        $store->set('order', [['id' => 1, 'total' => 100]]);

        $store->updateIdentified(
            'order',
            [['identity' => ['id' => 1], 'row' => ['id' => 2, 'total' => 200]]],
            ['id'],
        );

        self::assertSame([['id' => 2, 'total' => 200]], $store->get('order'));
    }
}
