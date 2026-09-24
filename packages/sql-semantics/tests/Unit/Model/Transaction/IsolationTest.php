<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Transaction\BeginTransactionStatement;
use SqlSemantics\Model\Transaction\Isolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Isolation::class)]
#[Medium]
final class IsolationTest extends TestCase
{
    public function testRepresentsEveryIsolationLevel(): void
    {
        self::assertSame(['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'], array_column(Isolation::cases(), 'value'));
    }

    #[TestWith(['BEGIN ISOLATION LEVEL READ UNCOMMITTED', Isolation::ReadUncommitted])]
    #[TestWith(['BEGIN ISOLATION LEVEL READ COMMITTED', Isolation::ReadCommitted])]
    #[TestWith(['BEGIN ISOLATION LEVEL REPEATABLE READ', Isolation::RepeatableRead])]
    #[TestWith(['BEGIN ISOLATION LEVEL SERIALIZABLE', Isolation::Serializable])]
    public function testClassifiesTheRequestedIsolationLevel(string $sql, Isolation $isolation): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BeginTransactionStatement::class, $statement);
        self::assertSame($isolation, $statement->characteristics->isolation);
        self::assertSame($sql, $statement->toString());
        self::assertSame($sql, $binder->bind($sql)->toString());
    }
}
