<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactionManager;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;
use ZtdQuery\Sql\TransactionStatement;

#[CoversClass(TransactionStatement::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ShadowTransactionManager::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenStream::class)]
final class TransactionStatementTest extends TestCase
{
    public function testParsesAndAppliesQuotedSavepointLifecycle(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1]]);
        $transactions = new ShadowTransactionManager($store);
        $begin = TransactionStatement::parse('BEGIN;');
        $savepoint = TransactionStatement::parse('SAVEPOINT "order item";');
        $rollback = TransactionStatement::parse('ROLLBACK TO SAVEPOINT "order item"');
        $commit = TransactionStatement::parse('COMMIT');
        self::assertNotNull($begin);
        self::assertNotNull($savepoint);
        self::assertNotNull($rollback);
        self::assertNotNull($commit);

        $begin->apply($transactions);
        $store->insert('items', [['id' => 2]]);
        $savepoint->apply($transactions);
        $store->insert('items', [['id' => 3]]);
        $rollback->apply($transactions);
        $commit->apply($transactions);

        self::assertSame([['id' => 1], ['id' => 2]], $store->get('items'));
    }

    public function testRejectsNonTransactionAndTrailingTokens(): void
    {
        self::assertNull(TransactionStatement::parse('SELECT 1'));
        self::assertNull(TransactionStatement::parse('SAVEPOINT one extra'));
        self::assertNull(TransactionStatement::parse('ROLLBACK TO'));
    }

    public function testAcceptsDialectBeginAndCommitForms(): void
    {
        self::assertNotNull(TransactionStatement::parse('BEGIN IMMEDIATE TRANSACTION'));
        self::assertNotNull(TransactionStatement::parse('START TRANSACTION'));
        self::assertNotNull(TransactionStatement::parse('END TRANSACTION'));
        self::assertNotNull(TransactionStatement::parse('ROLLBACK TRANSACTION'));
    }
}
