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
use SqlSemantics\Model\Statement\Transaction\CommitTransactionStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CommitTransactionStatement::class)]
#[Medium]
final class CommitTransactionStatementTest extends TestCase
{
    public function testBindsChainingAndReleasePolicies(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $plain = $binder->bind('COMMIT');
        $chained = $binder->bind('COMMIT AND CHAIN RELEASE');
        $kept = $binder->bind('COMMIT WORK NO RELEASE');
        self::assertInstanceOf(CommitTransactionStatement::class, $plain);
        self::assertInstanceOf(CommitTransactionStatement::class, $chained);
        self::assertInstanceOf(CommitTransactionStatement::class, $kept);
        self::assertSame(\SqlSemantics\Model\Transaction\Chaining::Default, $plain->chaining);
        self::assertSame(\SqlSemantics\Model\Transaction\Release::Default, $plain->release);
        self::assertSame(\SqlSemantics\Model\Transaction\Chaining::Chain, $chained->chaining);
        self::assertSame(\SqlSemantics\Model\Transaction\Release::Release, $chained->release);
        self::assertSame(\SqlSemantics\Model\Transaction\Release::NoRelease, $kept->release);
        self::assertSame(StatementKind::Commit, $plain->kind);
        self::assertSame('COMMIT AND CHAIN RELEASE', $chained->toString());
        self::assertSame('COMMIT NO RELEASE', $kept->toString());
    }

    public function testSqliteEndIsACommit(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('END');
        self::assertInstanceOf(CommitTransactionStatement::class, $statement);
        self::assertSame('COMMIT', $statement->toString());
    }

    public function testWithOriginPreservesThePolicies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('COMMIT AND CHAIN');
        self::assertInstanceOf(CommitTransactionStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::PostgreSql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame(\SqlSemantics\Model\Transaction\Chaining::Chain, $copy->chaining);
        self::assertSame($statement->release, $copy->release);
        self::assertSame('COMMIT AND CHAIN', $copy->toString());
    }
}
