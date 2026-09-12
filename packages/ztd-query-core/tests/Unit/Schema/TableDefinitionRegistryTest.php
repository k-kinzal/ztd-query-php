<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;

#[UsesClass(TableDefinition::class)]
#[CoversClass(TableDefinitionRegistry::class)]
final class TableDefinitionRegistryTest extends TestCase
{
    public function testSnapshotRestoreReplacesRegistryState(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $snapshot = $registry->snapshot();

        $registry->unregister('users');
        $registry->restore($snapshot);

        self::assertSame($definition, $registry->get('users'));
    }

    public function testRegisterAndGet(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], [], [], []);

        $registry->register('users', $definition);

        self::assertSame($definition, $registry->get('users'));
    }

    public function testRemovedDefinitionIsInactiveUntilRegisteredAgain(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], [], [], []);
        $replacement = new TableDefinition(['code'], ['code' => 'TEXT'], [], [], []);
        $registry->register('users', $definition);

        $registry->markRemoved('users');

        self::assertFalse($registry->has('users'));
        self::assertNull($registry->get('users'));
        self::assertTrue($registry->isRemoved('users'));
        self::assertSame(['users' => $definition], $registry->getAllRemoved());

        $registry->register('users', $replacement);

        self::assertSame($replacement, $registry->get('users'));
        self::assertFalse($registry->isRemoved('users'));
        self::assertSame([], $registry->getAllRemoved());
    }

    public function testSnapshotRestoreIncludesRemovedDefinitions(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], [], [], []);
        $registry->register('users', $definition);
        $registry->markRemoved('users');
        $snapshot = $registry->snapshot();

        $registry->unregister('users');
        self::assertFalse($registry->isRemoved('users'));

        $registry->restore($snapshot);

        self::assertTrue($registry->isRemoved('users'));
        self::assertSame(['users' => $definition], $registry->getAllRemoved());
    }

    public function testClearGetAllClearRemovesActiveAndRemovedDefinitions(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], [], [], []);
        $registry->register('active', $definition);
        $registry->register('removed', $definition);
        $registry->markRemoved('removed');

        $registry->clear();

        self::assertSame([], $registry->getAll());
        self::assertSame([], $registry->getAllRemoved());
    }

    public function testGetReturnsNullForUnknownTable(): void
    {
        $registry = new TableDefinitionRegistry();

        self::assertNull($registry->get('nonexistent'));
    }

    public function testHas(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], [], [], []);

        $registry->register('users', $definition);

        self::assertTrue($registry->has('users'));
        self::assertFalse($registry->has('nonexistent'));
    }

    public function testGetAllAnswersEveryTableStillDescribed(): void
    {
        $registry = new TableDefinitionRegistry();
        $order = new TableDefinition(['id'], [], ['id'], [], []);
        $registry->register('order', $order);

        self::assertSame(['order' => $order], $registry->getAll());
    }

    public function testGetAllRemovedAnswersTheTablesThatWereDropped(): void
    {
        $registry = new TableDefinitionRegistry();
        $order = new TableDefinition(['id'], [], ['id'], [], []);
        $registry->register('order', $order);
        $registry->markRemoved('order');

        self::assertSame(['order' => $order], $registry->getAllRemoved());
        self::assertSame([], $registry->getAll());
    }

    public function testMarkRemovedDoesNothingForATableNothingDescribed(): void
    {
        $registry = new TableDefinitionRegistry();

        $registry->markRemoved('order');

        self::assertSame([], $registry->getAllRemoved());
    }

    public function testIsRemovedTellsADroppedTableFromOneThatWasNeverThere(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('order', new TableDefinition(['id'], [], ['id'], [], []));
        $registry->markRemoved('order');

        self::assertTrue($registry->isRemoved('order'));
        self::assertFalse($registry->isRemoved('other'));
    }

    public function testHasAnyTablesIsFalseUntilOneIsRegistered(): void
    {
        $registry = new TableDefinitionRegistry();

        self::assertFalse($registry->hasAnyTables());

        $registry->register('order', new TableDefinition(['id'], [], ['id'], [], []));

        self::assertTrue($registry->hasAnyTables());
    }

    public function testUnregisterLeavesNoTraceOfTheTableAtAll(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('order', new TableDefinition(['id'], [], ['id'], [], []));
        $registry->markRemoved('order');

        $registry->unregister('order');

        self::assertFalse($registry->isRemoved('order'));
        self::assertNull($registry->get('order'));
    }

    public function testRestorePutsBackWhatWasDroppedAsWellAsWhatWasThere(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('order', new TableDefinition(['id'], [], ['id'], [], []));
        $registry->markRemoved('order');
        $snapshot = $registry->snapshot();
        $registry->clear();

        $registry->restore($snapshot);

        self::assertTrue($registry->isRemoved('order'));
    }
}
