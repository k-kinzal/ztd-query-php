<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\ShadowStore;

final class UpsertMutationTest extends TestCase
{
    public function testApplyInsertsNewRowWhenNoDuplicate(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'visits' => 10],
        ]);

        $mutation = new UpsertMutation('users', ['id']);
        $mutation->apply($store, [['id' => 2, 'name' => 'Bob', 'visits' => 1]]);

        $rows = $store->get('users');
        $this->assertCount(2, $rows);
        $this->assertSame('Bob', $rows[1]['name']);
    }

    public function testApplyUpdatesExistingRowOnDuplicate(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'visits' => 10],
        ]);

        $mutation = new UpsertMutation('users', ['id'], ['visits']);
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice', 'visits' => 11]]);

        $rows = $store->get('users');
        $this->assertCount(1, $rows);
        $this->assertSame(11, $rows[0]['visits']);
    }

    public function testTableNameReturnsTableName(): void
    {
        $mutation = new UpsertMutation('users', ['id']);

        $this->assertSame('users', $mutation->tableName());
    }

    public function testApplyWithUpdateValuesExpression(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'visits' => 10],
        ]);

        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['visits'],
            ['visits' => 'VALUES(`visits`)']
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice', 'visits' => 15]]);

        $rows = $store->get('users');
        $this->assertSame(15, $rows[0]['visits']);
    }

    public function testApplyWithLiteralUpdateValue(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'status' => 'pending'],
        ]);

        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['status'],
            ['status' => "'updated'"]
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice', 'status' => 'ignored']]);

        $rows = $store->get('users');
        $this->assertSame('updated', $rows[0]['status']);
    }

    public function testApplyUpdatesAllNonPrimaryColumnsWhenNoUpdateColumnsSpecified(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'visits' => 10],
        ]);

        $mutation = new UpsertMutation('users', ['id']);
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice Updated', 'visits' => 20]]);

        $rows = $store->get('users');
        $this->assertSame('Alice Updated', $rows[0]['name']);
        $this->assertSame(20, $rows[0]['visits']);
    }

    public function testApplyHandlesMixedInsertAndUpdate(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new UpsertMutation('users', ['id'], ['name']);
        $mutation->apply($store, [
            ['id' => 1, 'name' => 'Alice Updated'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $rows = $store->get('users');
        $this->assertCount(2, $rows);
        $this->assertSame('Alice Updated', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
    }

    public function testApplyWithCompositePrimaryKey(): void
    {
        $store = new ShadowStore();
        $store->set('order_items', [
            ['order_id' => 1, 'product_id' => 100, 'quantity' => 1],
        ]);

        $mutation = new UpsertMutation('order_items', ['order_id', 'product_id'], ['quantity']);
        $mutation->apply($store, [['order_id' => 1, 'product_id' => 100, 'quantity' => 5]]);

        $rows = $store->get('order_items');
        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows[0]['quantity']);
    }

    public function testApplyWithMissingPrimaryKeyInsertsNewRow(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new UpsertMutation('users', ['id']);
        $mutation->apply($store, [['name' => 'Bob']]);

        $rows = $store->get('users');
        $this->assertCount(2, $rows);
    }
}
