<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Sql\TransactionOperation;

#[CoversClass(TransactionOperation::class)]
final class TransactionOperationTest extends TestCase
{
    public function testDefinesEveryTransactionOperation(): void
    {
        self::assertSame([
            TransactionOperation::Begin,
            TransactionOperation::Commit,
            TransactionOperation::Rollback,
            TransactionOperation::Savepoint,
            TransactionOperation::RollbackTo,
            TransactionOperation::Release,
        ], TransactionOperation::cases());
        self::assertSame('begin', TransactionOperation::Begin->value);
        self::assertSame('commit', TransactionOperation::Commit->value);
        self::assertSame('rollback', TransactionOperation::Rollback->value);
        self::assertSame('savepoint', TransactionOperation::Savepoint->value);
        self::assertSame('rollback_to', TransactionOperation::RollbackTo->value);
        self::assertSame('release', TransactionOperation::Release->value);
    }
}
