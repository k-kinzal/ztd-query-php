<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\TestCase;

class ShadowStoreTest extends TestCase
{
    public function testInsertAppendsRows(): void
    {
        $store = new ShadowStore();
        $store->insert('users', [['id' => 1, 'name' => 'Alice']]);
        $store->insert('users', [['id' => 2, 'name' => 'Bob']]);

        $this->assertSame(
            [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']],
            $store->get('users')
        );
    }

    public function testGetReturnsEmptyArrayForMissingTable(): void
    {
        $store = new ShadowStore();

        $this->assertSame([], $store->get('missing'));
    }

    public function testGetAllReturnsCurrentTables(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $store->set('orders', [['id' => 10]]);

        $this->assertSame(
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

        $this->assertSame([['id' => 2, 'name' => 'Bob']], $store->get('users'));
    }

    public function testDeleteFallsBackToRowMatchWithoutPrimaryKeys(): void
    {
        $store = new ShadowStore();
        $store->set('logs', [
            ['id' => 1, 'payload' => 'A'],
            ['id' => 2, 'payload' => 'B'],
        ]);

        $store->delete('logs', [['id' => 2, 'payload' => 'B']]);

        $this->assertSame([['id' => 1, 'payload' => 'A']], $store->get('logs'));
    }

    public function testUpdateReplacesMatchingRow(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $store->update('users', [['id' => 2, 'name' => 'Bobby']], ['id']);

        $this->assertSame([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bobby'],
        ], $store->get('users'));
    }

    public function testUpdateWithoutPrimaryKeysThrows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $this->expectException(\RuntimeException::class);
        $store->update('users', [['id' => 1, 'name' => 'Updated']], []);
    }

    public function testEnsureCreatesEmptyTable(): void
    {
        $store = new ShadowStore();
        $store->ensure('events');

        $this->assertSame([], $store->get('events'));
    }

    public function testClearRemovesAllData(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $store->set('orders', [['id' => 10]]);

        $store->clear();

        $this->assertSame([], $store->getAll());
    }

    public function testDeleteNoOpWhenTableMissing(): void
    {
        $store = new ShadowStore();
        $store->delete('missing', [['id' => 1]], ['id']);

        $this->assertSame([], $store->get('missing'));
    }

    public function testUpdateNoOpWhenNoMatch(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $store->update('users', [['id' => 2, 'name' => 'Bob']], ['id']);

        $this->assertSame([['id' => 1, 'name' => 'Alice']], $store->get('users'));
    }
}
