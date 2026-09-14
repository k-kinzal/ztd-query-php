<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;
use ZtdQuery\Sql\TransactionStatement;

#[CoversClass(TransactionStatement::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ShadowTransactions::class)]
#[UsesClass(\ZtdQuery\Shadow\ShadowSavepoint::class)]
#[UsesClass(\ZtdQuery\Schema\RowSet::class)]
final class TransactionStatementTest extends TestCase
{
    public function testReleaseCommitAppliesNamedTransactionLifecycleWithoutSql(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactions($store);

        TransactionStatement::begin()->apply($transactions);
        $store->insert('items', [['id' => 2]]);
        TransactionStatement::savepoint('order item')->apply($transactions);
        $store->insert('items', [['id' => 3]]);
        TransactionStatement::rollbackTo('order item')->apply($transactions);
        TransactionStatement::release('order item')->apply($transactions);
        TransactionStatement::commit()->apply($transactions);

        self::assertSame([['id' => 1], ['id' => 2]], $store->get('items'));
    }

    public function testRollbackAppliesRollback(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactions($store);

        TransactionStatement::begin()->apply($transactions);
        $store->insert('items', [['id' => 2]]);
        TransactionStatement::rollback()->apply($transactions);

        self::assertSame([['id' => 1]], $store->get('items'));
    }

    public function testBeginStartsATransactionARollbackCanGoBackTo(): void
    {
        $store = new ShadowStore();
        $store->set('order', [['id' => 1]]);
        $transactions = new ShadowTransactions($store);

        TransactionStatement::begin()->apply($transactions);
        $store->set('order', []);
        TransactionStatement::rollback()->apply($transactions);

        self::assertSame([['id' => 1]], $store->get('order'));
    }

    public function testCommitKeepsEverythingTheTransactionDid(): void
    {
        $store = new ShadowStore();
        $store->set('order', [['id' => 1]]);
        $transactions = new ShadowTransactions($store);

        TransactionStatement::begin()->apply($transactions);
        $store->set('order', []);
        TransactionStatement::commit()->apply($transactions);
        TransactionStatement::rollback()->apply($transactions);

        self::assertSame([], $store->get('order'));
    }

    public function testRollbackToGoesBackToTheSavepointAndLeavesItDeclared(): void
    {
        $store = new ShadowStore();
        $transactions = new ShadowTransactions($store);
        TransactionStatement::begin()->apply($transactions);
        $store->set('order', [['id' => 1]]);
        TransactionStatement::savepoint('sp1')->apply($transactions);
        $store->set('order', []);

        TransactionStatement::rollbackTo('sp1')->apply($transactions);

        self::assertSame([['id' => 1]], $store->get('order'));
        self::assertNotNull($transactions->positionOf('sp1'));
    }

    public function testApplyDoesWhatEachOperationSays(): void
    {
        $store = new ShadowStore();
        $transactions = new ShadowTransactions($store);
        TransactionStatement::begin()->apply($transactions);
        TransactionStatement::savepoint('sp1')->apply($transactions);

        TransactionStatement::release('sp1')->apply($transactions);

        self::assertNull($transactions->positionOf('sp1'));
    }
    public function testRequiredNameAnswersTheNameTheStatementCarried(): void
    {
        self::assertSame('sp1', TransactionStatement::requiredName('sp1'));
    }

    public function testSavepointPreservesTheNamedTransactionBoundary(): void
    {
        $statement = TransactionStatement::savepoint('before_update');

        $transactions = new ShadowTransactions(new ShadowStore());
        $transactions->begin();
        $statement->apply($transactions);

        self::assertSame(1, $transactions->positionOf('before_update'));
    }

}
