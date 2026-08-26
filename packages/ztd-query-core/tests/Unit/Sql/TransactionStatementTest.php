<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactionManager;
use ZtdQuery\Sql\TransactionStatement;

#[CoversClass(TransactionStatement::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ShadowTransactionManager::class)]
final class TransactionStatementTest extends TestCase
{
    public function testCommitAppliesNamedTransactionLifecycleWithoutSql(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactionManager($store);

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
        $transactions = new ShadowTransactionManager($store);

        TransactionStatement::begin()->apply($transactions);
        $store->insert('items', [['id' => 2]]);
        TransactionStatement::rollback()->apply($transactions);

        self::assertSame([['id' => 1]], $store->get('items'));
    }

    public function testSavepointRejectsEmptySavepointName(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Savepoint name must not be empty.');

        TransactionStatement::savepoint('');
    }
}
