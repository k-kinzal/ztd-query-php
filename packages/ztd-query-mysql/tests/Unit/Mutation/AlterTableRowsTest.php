<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\AlterTableRows;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(AlterTableRows::class)]
#[UsesClass(ShadowStore::class)]
final class AlterTableRowsTest extends TestCase
{
    public function testRemoveColumnTakesItOffEveryRow(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']]);

        (new AlterTableRows())->removeColumn($store, 'users', 'name');

        self::assertSame([['id' => 1], ['id' => 2]], $store->get('users'));
    }

    public function testRemoveColumnLeavesATableWithNoRowsAlone(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);

        (new AlterTableRows())->removeColumn($store, 'users', 'name');

        self::assertSame([], $store->get('users'));
    }

    public function testRenameColumnCarriesTheValuesOverToTheNewName(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'a']]);

        (new AlterTableRows())->renameColumn($store, 'users', 'name', 'full_name');

        self::assertSame([['id' => 1, 'full_name' => 'a']], $store->get('users'));
    }

    public function testRenameColumnInventsNothingForARowThatNeverCarriedIt(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        (new AlterTableRows())->renameColumn($store, 'users', 'name', 'full_name');

        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testRenameColumnLeavesATableWithNoRowsAlone(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);

        (new AlterTableRows())->renameColumn($store, 'users', 'name', 'full_name');

        self::assertSame([], $store->get('users'));
    }
}
