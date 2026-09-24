<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Transaction\CommitTransactionStatement;
use SqlSemantics\Model\Statement\Transaction\RollbackTransactionStatement;
use SqlSemantics\Model\Transaction\Chaining;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Chaining::class)]
#[Medium]
final class ChainingTest extends TestCase
{
    public function testRepresentsEveryChainingChoice(): void
    {
        self::assertSame(['default', 'chain', 'no-chain'], array_column(Chaining::cases(), 'value'));
    }

    #[TestWith([Dialect::PostgreSql, 'COMMIT', Chaining::Default])]
    #[TestWith([Dialect::PostgreSql, 'COMMIT AND CHAIN', Chaining::Chain])]
    #[TestWith([Dialect::MySql, 'COMMIT AND NO CHAIN', Chaining::NoChain])]
    public function testClassifiesTheChainingOfACommit(Dialect $dialect, string $sql, Chaining $chaining): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CommitTransactionStatement::class, $statement);
        self::assertSame($chaining, $statement->chaining);
        self::assertSame($sql, $statement->toString());
        self::assertSame($sql, $binder->bind($sql)->toString());
    }

    public function testClassifiesTheChainingOfARollback(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ROLLBACK AND NO CHAIN RELEASE');
        self::assertInstanceOf(RollbackTransactionStatement::class, $statement);
        self::assertSame(Chaining::NoChain, $statement->chaining);
    }
}
