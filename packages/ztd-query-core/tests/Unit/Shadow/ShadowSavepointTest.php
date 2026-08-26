<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowSavepoint;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ShadowSavepoint::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ColumnDeclaration::class)]
final class ShadowSavepointTest extends TestCase
{
    public function testOfRemembersTheRowsAsTheyWereRatherThanAsTheyBecome(): void
    {
        $store = new ShadowStore();
        $store->set('order', [['id' => 1]]);

        $savepoint = ShadowSavepoint::of(null, $store, null);
        $store->set('order', [['id' => 2]]);
        $savepoint->restoreInto($store, null);

        self::assertSame([['id' => 1]], $store->get('order'));
    }

    public function testOfRemembersWhichTablesThereWere(): void
    {
        $store = new ShadowStore();
        $registry = new TableDefinitionRegistry();
        $registry->register('order', new TableDefinition(['id'], [], ['id'], [], []));

        $savepoint = ShadowSavepoint::of(null, $store, $registry);
        $registry->register('extra', new TableDefinition(['id'], [], ['id'], [], []));
        $savepoint->restoreInto($store, $registry);

        self::assertNull($registry->get('extra'));
        self::assertNotNull($registry->get('order'));
    }

    public function testRestoreIntoLeavesTheTablesAloneWhereNothingIsDescribingThem(): void
    {
        $store = new ShadowStore();
        $store->set('order', [['id' => 1]]);
        $savepoint = ShadowSavepoint::of(null, $store, null);
        $store->set('order', []);

        $savepoint->restoreInto($store, null);

        self::assertSame([['id' => 1]], $store->get('order'));
    }

    public function testIsNamedAnswersForTheNameItWasDeclaredUnder(): void
    {
        $savepoint = ShadowSavepoint::of('sp1', new ShadowStore(), null);

        self::assertTrue($savepoint->isNamed('sp1'));
        self::assertFalse($savepoint->isNamed('sp2'));
    }

    public function testIsNamedIsFalseForTheTransactionItself(): void
    {
        self::assertFalse(ShadowSavepoint::of(null, new ShadowStore(), null)->isNamed('sp1'));
    }

    public function testTheNameIsReadableWithoutBeingGuessedAt(): void
    {
        self::assertSame('sp1', ShadowSavepoint::of('sp1', new ShadowStore(), null)->name);
    }
}
