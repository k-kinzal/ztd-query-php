<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\Alter\StoredColumns;

#[CoversClass(StoredColumns::class)]
final class StoredColumnsTest extends TestCase
{
    public function testRemoveColumnFromStore(): void
    {
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('t', [['id' => 1, 'name' => null], ['id' => 2]]);
        (new StoredColumns('t'))->removeColumnFromStore($store, 'name');
        self::assertSame([['id' => 1], ['id' => 2]], $store->get('t'));
        (new StoredColumns('missing'))->removeColumnFromStore($store, 'name');
        self::assertSame([], $store->get('missing'));
    }

    public function testRenameColumnInStore(): void
    {
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('t', [['id' => 1, 'name' => null], ['id' => 2]]);
        (new StoredColumns('t'))->renameColumnInStore($store, 'name', 'label');
        self::assertSame([['id' => 1, 'label' => null], ['id' => 2]], $store->get('t'));
        (new StoredColumns('missing'))->renameColumnInStore($store, 'name', 'label');
        self::assertSame([], $store->get('missing'));
    }

}
