<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(ShadowStore::class)]
#[CoversClass(ReplaceMutation::class)]
final class ReplaceMutationTest extends TestCase
{
    public function testApplyReplacesExistingRowByPrimaryKey(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $mutation = new ReplaceMutation('users', ['id']);
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice Updated']]);

        $rows = $store->get('users');
        self::assertCount(2, $rows);
        self::assertSame('Bob', $rows[0]['name']);
        self::assertSame('Alice Updated', $rows[1]['name']);
    }

    public function testApplyInsertsNewRowWhenNoDuplicate(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new ReplaceMutation('users', ['id']);
        $mutation->apply($store, [['id' => 2, 'name' => 'Bob']]);

        $rows = $store->get('users');
        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame('Bob', $rows[1]['name']);
    }

    public function testTableNameReturnsTableName(): void
    {
        $mutation = new ReplaceMutation('users', ['id']);

        self::assertSame('users', $mutation->tableName());
    }

    public function testApplyWithoutPrimaryKeysComparesAllColumns(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $mutation = new ReplaceMutation('users');
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice']]);

        $rows = $store->get('users');
        self::assertCount(2, $rows);
    }

    public function testApplyReplacesMultipleRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Carol'],
        ]);

        $mutation = new ReplaceMutation('users', ['id']);
        $mutation->apply($store, [
            ['id' => 1, 'name' => 'Alice Replaced'],
            ['id' => 3, 'name' => 'Carol Replaced'],
        ]);

        $rows = $store->get('users');
        self::assertCount(3, $rows);
        self::assertSame('Bob', $rows[0]['name']);
        self::assertSame('Alice Replaced', $rows[1]['name']);
        self::assertSame('Carol Replaced', $rows[2]['name']);
    }

    public function testApplyWithCompositePrimaryKey(): void
    {
        $store = new ShadowStore();
        $store->set('order_items', [
            ['order_id' => 1, 'product_id' => 100, 'quantity' => 1],
            ['order_id' => 1, 'product_id' => 200, 'quantity' => 2],
        ]);

        $mutation = new ReplaceMutation('order_items', ['order_id', 'product_id']);
        $mutation->apply($store, [['order_id' => 1, 'product_id' => 100, 'quantity' => 5]]);

        $rows = $store->get('order_items');
        self::assertCount(2, $rows);
        self::assertSame(2, $rows[0]['quantity']);
        self::assertSame(5, $rows[1]['quantity']);
    }

    public function testApplyWithMissingPrimaryKeyDoesNotMatch(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new ReplaceMutation('users', ['id']);
        $mutation->apply($store, [['name' => 'Bob']]);

        $rows = $store->get('users');
        self::assertCount(2, $rows);
    }
}
