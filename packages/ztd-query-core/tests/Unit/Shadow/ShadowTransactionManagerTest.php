<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactionManager;

#[CoversClass(ShadowTransactionManager::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
final class ShadowTransactionManagerTest extends TestCase
{
    public function testRollbackRestoresRowsTablePresenceAndSchemaRegistry(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $transactions = new ShadowTransactionManager($store, $registry);

        $transactions->begin();
        $store->insert('users', [['id' => 2]]);
        $store->set('temporary_table', []);
        $registry->unregister('users');
        $transactions->rollBack();

        self::assertSame([['id' => 1]], $store->get('users'));
        self::assertFalse($store->has('temporary_table'));
        self::assertSame($definition, $registry->get('users'));
    }

    public function testRollbackToKeepsSavepointAndDiscardsLaterSavepoints(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactionManager($store);

        $transactions->begin();
        $store->insert('items', [['id' => 2]]);
        $transactions->savepoint('outer');
        $store->insert('items', [['id' => 3]]);
        $transactions->savepoint('inner');
        $store->insert('items', [['id' => 4]]);
        $transactions->rollBackTo('outer');
        $store->insert('items', [['id' => 5]]);
        $transactions->commit();

        self::assertSame([['id' => 1], ['id' => 2], ['id' => 5]], $store->get('items'));
    }

    public function testReleaseDropsNamedAndNestedSnapshotsWithoutUndoingRows(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactionManager($store);

        $transactions->begin();
        $transactions->savepoint('outer');
        $store->insert('items', [['id' => 2]]);
        $transactions->savepoint('inner');
        $transactions->release('outer');
        $transactions->rollBackTo('inner');
        $transactions->commit();

        self::assertSame([['id' => 1], ['id' => 2]], $store->get('items'));
    }

    public function testReplacingSavepointUsesMostRecentSnapshot(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactionManager($store);

        $transactions->begin();
        $transactions->savepoint('point');
        $store->insert('items', [['id' => 2]]);
        $transactions->savepoint('point');
        $store->insert('items', [['id' => 3]]);
        $transactions->rollBackTo('point');

        self::assertSame([['id' => 1], ['id' => 2]], $store->get('items'));
        $transactions->release('point');
        $store->insert('items', [['id' => 4]]);
        $transactions->rollBackTo('point');
        self::assertSame([['id' => 1], ['id' => 2], ['id' => 4]], $store->get('items'));
    }

    public function testRollbackToKeepsTargetForAnotherRollback(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactionManager($store);

        $transactions->begin();
        $store->insert('items', [['id' => 2]]);
        $transactions->savepoint('outer');
        $store->insert('items', [['id' => 3]]);
        $transactions->savepoint('inner');
        $store->insert('items', [['id' => 4]]);
        $transactions->rollBackTo('outer');
        $transactions->rollBackTo('inner');
        self::assertSame([['id' => 1], ['id' => 2]], $store->get('items'));
        $store->insert('items', [['id' => 5]]);
        $transactions->rollBackTo('outer');
        self::assertSame([['id' => 1], ['id' => 2]], $store->get('items'));
        $store->insert('items', [['id' => 6]]);
        $transactions->rollBack();
        self::assertSame([['id' => 1]], $store->get('items'));
    }

    public function testReleaseKeepsEarlierSavepointAndUnknownNameDoesNothing(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactionManager($store);

        $transactions->savepoint('outer');
        $store->insert('items', [['id' => 2]]);
        $transactions->savepoint('inner');
        $store->insert('items', [['id' => 3]]);
        $transactions->release('missing');
        $transactions->release('inner');
        $store->insert('items', [['id' => 4]]);
        $transactions->rollBackTo('inner');
        self::assertSame([['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]], $store->get('items'));
        $transactions->rollBackTo('outer');

        self::assertSame([['id' => 1]], $store->get('items'));
    }
}
