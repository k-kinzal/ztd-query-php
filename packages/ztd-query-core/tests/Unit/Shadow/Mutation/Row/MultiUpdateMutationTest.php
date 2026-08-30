<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Row;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\Row\MultiTableMutationRow;
use ZtdQuery\Shadow\Mutation\Row\MultiTableMutationTarget;
use ZtdQuery\Shadow\Mutation\Row\MultiUpdateMutation;
use ZtdQuery\Shadow\ShadowStore;

#[UsesClass(ShadowStore::class)]
#[UsesClass(MultiTableMutationRow::class)]
#[UsesClass(MultiTableMutationTarget::class)]
#[CoversClass(MultiUpdateMutation::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\RowMatch::class)]
final class MultiUpdateMutationTest extends TestCase
{
    public function testApplyUpdatesMultipleTables(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'status' => 'active'],
            ['id' => 2, 'name' => 'Bob', 'status' => 'active'],
        ]);
        $store->set('profiles', [
            ['user_id' => 1, 'verified' => false],
            ['user_id' => 2, 'verified' => false],
        ]);

        $mutation = new MultiUpdateMutation([
            new MultiTableMutationTarget('users', ['id', 'name', 'status'], ['id']),
            new MultiTableMutationTarget('profiles', ['user_id', 'verified'], ['user_id']),
        ]);

        $mutation->apply($store, [
            [
                '__ztd_multi_0_value_0' => 1,
                '__ztd_multi_0_value_1' => 'Alice Updated',
                '__ztd_multi_0_value_2' => 'inactive',
                '__ztd_multi_0_identity_0' => 1,
                '__ztd_multi_1_value_0' => 1,
                '__ztd_multi_1_value_1' => true,
                '__ztd_multi_1_identity_0' => 1,
            ],
        ]);

        self::assertSame('Alice Updated', $store->get('users')[0]['name']);
        self::assertSame('inactive', $store->get('users')[0]['status']);
        self::assertTrue($store->get('profiles')[0]['verified']);
    }

    public function testTableNameReturnsPrimaryTable(): void
    {
        $mutation = new MultiUpdateMutation([
            new MultiTableMutationTarget('users', ['id'], ['id']),
            new MultiTableMutationTarget('profiles', ['user_id'], ['user_id']),
        ]);

        self::assertSame('users', $mutation->tableName());
    }

    public function testTableNamesReturnsAllTableNames(): void
    {
        $mutation = new MultiUpdateMutation([
            new MultiTableMutationTarget('users', ['id'], ['id']),
            new MultiTableMutationTarget('profiles', ['user_id'], ['user_id']),
            new MultiTableMutationTarget('settings', ['user_id'], ['user_id']),
        ]);

        self::assertSame(['users', 'profiles', 'settings'], $mutation->tableNames());
    }

    public function testApplyWithEmptyTablesReturnsEmptyPrimaryTable(): void
    {
        $mutation = new MultiUpdateMutation([]);

        self::assertSame('', $mutation->tableName());
    }

    public function testApplyUpdatesMultipleRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Carol'],
        ]);

        $mutation = new MultiUpdateMutation([
            new MultiTableMutationTarget('users', ['id', 'name'], ['id']),
        ]);

        $mutation->apply($store, [
            [
                '__ztd_multi_0_value_0' => 1,
                '__ztd_multi_0_value_1' => 'Alice Updated',
                '__ztd_multi_0_identity_0' => 1,
            ],
            [
                '__ztd_multi_0_value_0' => 3,
                '__ztd_multi_0_value_1' => 'Carol Updated',
                '__ztd_multi_0_identity_0' => 3,
            ],
        ]);

        self::assertSame('Alice Updated', $store->get('users')[0]['name']);
        self::assertSame('Bob', $store->get('users')[1]['name']);
        self::assertSame('Carol Updated', $store->get('users')[2]['name']);
    }

    public function testApplySkipsRowsWithIncompleteValuesOrIdentity(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);
        $mutation = new MultiUpdateMutation([
            new MultiTableMutationTarget('users', ['id', 'name'], ['id']),
        ]);

        $mutation->apply($store, [
            [
                '__ztd_multi_0_identity_0' => 1,
            ],
            [
                '__ztd_multi_0_value_0' => 1,
                '__ztd_multi_0_value_1' => 'Incomplete',
            ],
        ]);

        self::assertSame([['id' => 1, 'name' => 'Alice']], $store->get('users'));
    }
}
