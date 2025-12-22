<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\MultiDeleteMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(ShadowStore::class)]
#[CoversClass(MultiDeleteMutation::class)]
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
            'users' => ['id'],
            'profiles' => ['user_id'],
        ]);

        $mutation->apply($store, [
            ['id' => 1, 'user_id' => 1],
        ]);

        self::assertSame([['id' => 2, 'name' => 'Bob']], $store->get('users'));
        self::assertSame([['user_id' => 2, 'bio' => 'Bob bio']], $store->get('profiles'));
    }

    public function testTableNameReturnsPrimaryTable(): void
    {
        $mutation = new MultiDeleteMutation([
            'users' => ['id'],
            'profiles' => ['user_id'],
        ]);

        self::assertSame('users', $mutation->tableName());
    }

    public function testTableNamesReturnsAllTableNames(): void
    {
        $mutation = new MultiDeleteMutation([
            'users' => ['id'],
            'profiles' => ['user_id'],
            'settings' => ['user_id'],
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
            'users' => ['id'],
            'orders' => ['user_id'],
        ]);

        $mutation->apply($store, [
            ['id' => 1, 'user_id' => 1],
            ['id' => 3, 'user_id' => 3],
        ]);

        self::assertSame([['id' => 2, 'name' => 'Bob']], $store->get('users'));
        self::assertSame([['order_id' => 20, 'user_id' => 2]], $store->get('orders'));
    }
}
