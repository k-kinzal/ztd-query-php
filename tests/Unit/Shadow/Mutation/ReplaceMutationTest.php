<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\ShadowStore;

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
        $this->assertCount(2, $rows);
        $this->assertSame('Bob', $rows[0]['name']);
        $this->assertSame('Alice Updated', $rows[1]['name']);
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
        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
    }

    public function testTableNameReturnsTableName(): void
    {
        $mutation = new ReplaceMutation('users', ['id']);

        $this->assertSame('users', $mutation->tableName());
    }

    public function testApplyWithoutPrimaryKeysComparesAllColumns(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $mutation = new ReplaceMutation('users');
        // Replaces exact match
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice']]);

        $rows = $store->get('users');
        // Original row deleted, same row re-inserted
        $this->assertCount(2, $rows);
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
        $this->assertCount(3, $rows);
        $this->assertSame('Bob', $rows[0]['name']);
        $this->assertSame('Alice Replaced', $rows[1]['name']);
        $this->assertSame('Carol Replaced', $rows[2]['name']);
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
        $this->assertCount(2, $rows);
        $this->assertSame(2, $rows[0]['quantity']);
        $this->assertSame(5, $rows[1]['quantity']);
    }

    public function testApplyWithMissingPrimaryKeyDoesNotMatch(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new ReplaceMutation('users', ['id']);
        // Missing 'id' key in new row
        $mutation->apply($store, [['name' => 'Bob']]);

        $rows = $store->get('users');
        // Original row kept, new row inserted
        $this->assertCount(2, $rows);
    }
}
