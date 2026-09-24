<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Statement\Transaction\RollbackTransactionStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RollbackTransactionStatement::class)]
#[Medium]
final class RollbackTransactionStatementTest extends TestCase
{
    public function testBindsChainingAndReleasePolicies(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $plain = $binder->bind('ROLLBACK');
        $explicit = $binder->bind('ROLLBACK AND NO CHAIN NO RELEASE');
        self::assertInstanceOf(RollbackTransactionStatement::class, $plain);
        self::assertInstanceOf(RollbackTransactionStatement::class, $explicit);
        self::assertSame(\SqlSemantics\Model\Transaction\Chaining::Default, $plain->chaining);
        self::assertSame(\SqlSemantics\Model\Transaction\Release::Default, $plain->release);
        self::assertSame(\SqlSemantics\Model\Transaction\Chaining::NoChain, $explicit->chaining);
        self::assertSame(\SqlSemantics\Model\Transaction\Release::NoRelease, $explicit->release);
        self::assertSame(StatementKind::Rollback, $plain->kind);
        self::assertSame('ROLLBACK', $plain->toString());
        self::assertSame('ROLLBACK AND NO CHAIN NO RELEASE', $explicit->toString());
    }

    public function testWithOriginPreservesThePolicies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ROLLBACK AND NO CHAIN');
        self::assertInstanceOf(RollbackTransactionStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::PostgreSql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame(\SqlSemantics\Model\Transaction\Chaining::NoChain, $copy->chaining);
        self::assertSame($statement->release, $copy->release);
        self::assertSame('ROLLBACK AND NO CHAIN', $copy->toString());
    }
}
