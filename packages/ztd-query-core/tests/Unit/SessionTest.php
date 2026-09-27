<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;
use ZtdQuery\Sql\TransactionStatement;

#[CoversClass(Session::class)]
#[UsesClass(\ZtdQuery\Schema\RowSet::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ShadowTransactions::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ViewDefinitionSet::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(\ZtdQuery\Schema\Key\CandidateKeySet::class)]
#[UsesClass(\ZtdQuery\Shadow\ShadowSavepoint::class)]
#[UsesClass(TransactionStatement::class)]
final class SessionTest extends TestCase
{
    public function testStoreRegistryViewsAndTableDefinitionBelongToOneSession(): void
    {
        $first = new Session();
        $second = new Session();
        $definition = new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], ['id'], []);
        $first->registry()->register('items', $definition);
        $first->store()->set('items', [['id' => 1]]);
        self::assertSame($definition, $first->tableDefinition('items'));
        self::assertNull($second->tableDefinition('items'));
        self::assertSame([], $second->store()->get('items'));
        self::assertNotSame($first->views(), $second->views());
    }

    public function testEnableDisableAndIsEnabledDoNotDiscardFixtures(): void
    {
        $session = new Session();
        $session->store()->set('items', [['id' => 1]]);
        $session->disable();
        self::assertFalse($session->isEnabled());
        $session->enable();
        self::assertTrue($session->isEnabled());
        self::assertSame([['id' => 1]], $session->store()->get('items'));
    }

    public function testBeginTransactionAndRollBackTransactionRestoreRowsAndSchema(): void
    {
        $session = new Session();
        $definition = new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], ['id'], []);
        $session->registry()->register('items', $definition);
        $session->store()->set('items', [['id' => 1]]);
        $session->beginTransaction();
        $session->registry()->unregister('items');
        $session->store()->set('items', [['id' => 2]]);
        $session->rollBackTransaction();
        self::assertSame($definition, $session->tableDefinition('items'));
        self::assertSame([['id' => 1]], $session->store()->get('items'));
    }

    public function testCommitTransactionKeepsVirtualWrites(): void
    {
        $session = new Session();
        $session->beginTransaction();
        $session->store()->set('items', [['id' => 1]]);
        $session->commitTransaction();
        $session->rollBackTransaction();
        self::assertSame([['id' => 1]], $session->store()->get('items'));
    }

    public function testTransactionsAndApplyTransactionStatementShareTheSameSnapshots(): void
    {
        $session = new Session();
        $session->applyTransactionStatement(TransactionStatement::begin());
        $session->transactions()->savepoint('before_write');
        $session->store()->set('items', [['id' => 1]]);
        $session->transactions()->rollBackTo('before_write');
        self::assertSame([], $session->store()->get('items'));
    }

    public function testRememberInsertIdAndLastInsertIdRetainTheLastGeneratedIdentity(): void
    {
        $session = new Session();
        self::assertFalse($session->lastInsertId());
        $session->rememberInsertId('42');
        $session->rememberInsertId(null);
        self::assertSame('42', $session->lastInsertId());
    }

    public function testRegistryReturnsTheSuppliedVirtualCatalog(): void
    {
        $registry = new TableDefinitionRegistry();
        self::assertSame($registry, (new Session(registry: $registry))->registry());
    }

    public function testViewsReturnsTheSuppliedViewDefinitions(): void
    {
        $views = new ViewDefinitionSet();
        self::assertSame($views, (new Session(views: $views))->views());
    }

    public function testTableDefinitionSeesCatalogChanges(): void
    {
        $session = new Session();
        $definition = new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], ['id'], []);
        $session->registry()->register('items', $definition);
        self::assertSame($definition, $session->tableDefinition('items'));
        $session->registry()->unregister('items');
        self::assertNull($session->tableDefinition('items'));
    }

    public function testDisableLeavesOtherSessionsEnabled(): void
    {
        $first = new Session();
        $second = new Session();
        $first->disable();
        self::assertFalse($first->isEnabled());
        self::assertTrue($second->isEnabled());
    }

    public function testIsEnabledStartsTrueForNewSessions(): void
    {
        self::assertTrue((new Session())->isEnabled());
    }

    public function testRollBackTransactionWithoutATransactionKeepsFixtures(): void
    {
        $session = new Session();
        $session->store()->set('items', [['id' => 1]]);
        $session->rollBackTransaction();
        self::assertSame([['id' => 1]], $session->store()->get('items'));
    }

    public function testApplyTransactionStatementRollsBackVirtualWrites(): void
    {
        $session = new Session();
        $session->applyTransactionStatement(TransactionStatement::begin());
        $session->store()->set('items', [['id' => 1]]);
        $session->applyTransactionStatement(TransactionStatement::rollback());
        self::assertSame([], $session->store()->get('items'));
    }

    public function testLastInsertIdDoesNotLeakBetweenSessions(): void
    {
        $first = new Session();
        $second = new Session();
        $first->rememberInsertId('7');
        self::assertFalse($second->lastInsertId());
    }
}
