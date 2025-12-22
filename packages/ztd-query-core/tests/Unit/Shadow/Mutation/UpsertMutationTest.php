<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(ShadowStore::class)]
#[CoversClass(UpsertMutation::class)]
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
        self::assertCount(2, $rows);
        self::assertSame('Bob', $rows[1]['name']);
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
        self::assertCount(1, $rows);
        self::assertSame(11, $rows[0]['visits']);
    }

    public function testTableNameReturnsTableName(): void
    {
        $mutation = new UpsertMutation('users', ['id']);

        self::assertSame('users', $mutation->tableName());
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
        self::assertSame(15, $rows[0]['visits']);
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
        self::assertSame('updated', $rows[0]['status']);
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
        self::assertSame('Alice Updated', $rows[0]['name']);
        self::assertSame(20, $rows[0]['visits']);
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
        self::assertCount(2, $rows);
        self::assertSame('Alice Updated', $rows[0]['name']);
        self::assertSame('Bob', $rows[1]['name']);
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
        self::assertCount(1, $rows);
        self::assertSame(5, $rows[0]['quantity']);
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
        self::assertCount(2, $rows);
    }

    public function testApplyWithExcludedColumnReference(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'visits' => 10],
        ]);

        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['name'],
            ['name' => 'EXCLUDED.name']
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Bob', 'visits' => 15]]);

        $rows = $store->get('users');
        self::assertSame('Bob', $rows[0]['name']);
    }

    public function testApplyWithExcludedQuotedColumnReference(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['name'],
            ['name' => 'EXCLUDED."name"']
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Charlie']]);

        $rows = $store->get('users');
        self::assertSame('Charlie', $rows[0]['name']);
    }
}
