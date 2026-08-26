<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\MultiDeleteMutation;
use ZtdQuery\Shadow\Mutation\MultiTableMutationRow;
use ZtdQuery\Shadow\Mutation\MultiTableMutationTarget;
use ZtdQuery\Shadow\ShadowStore;

#[UsesClass(ShadowStore::class)]
#[UsesClass(MultiTableMutationRow::class)]
#[UsesClass(MultiTableMutationTarget::class)]
#[CoversClass(MultiDeleteMutation::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\RowMatch::class)]
final class MultiDeleteMutationTest extends TestCase
{
    public function testApplyDeletesFromMultipleTables(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $store->set('profiles', [
            ['user_id' => 1, 'bio' => 'Alice bio'],
            ['user_id' => 2, 'bio' => 'Bob bio'],
        ]);

        $mutation = new MultiDeleteMutation([
            new MultiTableMutationTarget('users', ['id', 'name'], ['id']),
            new MultiTableMutationTarget('profiles', ['user_id', 'bio'], ['user_id']),
        ]);

        $mutation->apply($store, [
            ['__ztd_multi_0_value_0' => 1, '__ztd_multi_1_value_0' => 1],
        ]);

        self::assertSame([['id' => 2, 'name' => 'Bob']], $store->get('users'));
        self::assertSame([['user_id' => 2, 'bio' => 'Bob bio']], $store->get('profiles'));
    }

    public function testTableNameReturnsPrimaryTable(): void
    {
        $mutation = new MultiDeleteMutation([
            new MultiTableMutationTarget('users', ['id'], ['id']),
            new MultiTableMutationTarget('profiles', ['user_id'], ['user_id']),
        ]);

        self::assertSame('users', $mutation->tableName());
    }

    public function testTableNamesReturnsAllTableNames(): void
    {
        $mutation = new MultiDeleteMutation([
            new MultiTableMutationTarget('users', ['id'], ['id']),
            new MultiTableMutationTarget('profiles', ['user_id'], ['user_id']),
            new MultiTableMutationTarget('settings', ['user_id'], ['user_id']),
        ]);

        self::assertSame(['users', 'profiles', 'settings'], $mutation->tableNames());
    }

    public function testApplyWithEmptyTablesReturnsEmptyPrimaryTable(): void
    {
        $mutation = new MultiDeleteMutation([]);

        self::assertSame('', $mutation->tableName());
    }

    public function testApplyDeletesMultipleRowsFromMultipleTables(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Carol'],
        ]);
        $store->set('orders', [
            ['order_id' => 10, 'user_id' => 1],
            ['order_id' => 20, 'user_id' => 2],
            ['order_id' => 30, 'user_id' => 3],
        ]);

        $mutation = new MultiDeleteMutation([
            new MultiTableMutationTarget('users', ['id', 'name'], ['id']),
            new MultiTableMutationTarget('orders', ['order_id', 'user_id'], ['user_id']),
        ]);

        $mutation->apply($store, [
            ['__ztd_multi_0_value_0' => 1, '__ztd_multi_1_value_0' => 1],
            ['__ztd_multi_0_value_0' => 3, '__ztd_multi_1_value_0' => 3],
        ]);

        self::assertSame([['id' => 2, 'name' => 'Bob']], $store->get('users'));
        self::assertSame([['order_id' => 20, 'user_id' => 2]], $store->get('orders'));
    }
}
