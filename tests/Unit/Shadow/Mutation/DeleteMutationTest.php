<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\ShadowStore;

final class DeleteMutationTest extends TestCase
{
    public function testApplyRemovesRowsByPrimaryKey(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $mutation = new DeleteMutation('users', ['id']);
        $mutation->apply($store, [['id' => 1]]);

        $this->assertSame([['id' => 2, 'name' => 'Bob']], $store->get('users'));
    }

    public function testTableNameReturnsTableName(): void
    {
        $mutation = new DeleteMutation('users', ['id']);

        $this->assertSame('users', $mutation->tableName());
    }

    public function testApplyRemovesMultipleRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Carol'],
        ]);

        $mutation = new DeleteMutation('users', ['id']);
        $mutation->apply($store, [['id' => 1], ['id' => 3]]);

        $this->assertSame([['id' => 2, 'name' => 'Bob']], $store->get('users'));
    }

    public function testApplyWithCompositePrimaryKey(): void
    {
        $store = new ShadowStore();
        $store->set('order_items', [
            ['order_id' => 1, 'product_id' => 100, 'quantity' => 1],
            ['order_id' => 1, 'product_id' => 200, 'quantity' => 2],
            ['order_id' => 2, 'product_id' => 100, 'quantity' => 3],
        ]);

        $mutation = new DeleteMutation('order_items', ['order_id', 'product_id']);
        $mutation->apply($store, [['order_id' => 1, 'product_id' => 100]]);

        $this->assertCount(2, $store->get('order_items'));
        $this->assertSame(200, $store->get('order_items')[0]['product_id']);
    }

    public function testApplyWithEmptyRowsDoesNothing(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new DeleteMutation('users', ['id']);
        $mutation->apply($store, []);

        $this->assertCount(1, $store->get('users'));
    }

    public function testApplyWithNonExistentRowDoesNothing(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new DeleteMutation('users', ['id']);
        $mutation->apply($store, [['id' => 999]]);

        $this->assertCount(1, $store->get('users'));
    }
}
