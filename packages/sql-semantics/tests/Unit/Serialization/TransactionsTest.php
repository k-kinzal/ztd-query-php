<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Transaction\BeginTransactionStatement;
use SqlSemantics\Model\Statement\Transaction\CommitTransactionStatement;
use SqlSemantics\Model\Transaction\Chaining;
use SqlSemantics\Model\Transaction\Release;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Transactions;

#[CoversClass(Transactions::class)]
#[Medium]
final class TransactionsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'BEGIN ISOLATION LEVEL SERIALIZABLE READ ONLY DEFERRABLE', 'BEGIN ISOLATION LEVEL SERIALIZABLE, READ ONLY, DEFERRABLE'])]
    #[TestWith([Dialect::PostgreSql, 'START TRANSACTION READ WRITE NOT DEFERRABLE', 'BEGIN READ WRITE, NOT DEFERRABLE'])]
    #[TestWith([Dialect::MySql, 'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY', 'START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT'])]
    #[TestWith([Dialect::MySql, 'BEGIN WORK', 'START TRANSACTION'])]
    #[TestWith([Dialect::Sqlite, 'BEGIN IMMEDIATE TRANSACTION', 'BEGIN IMMEDIATE'])]
    #[TestWith([Dialect::PostgreSql, 'COMMIT AND CHAIN', 'COMMIT AND CHAIN'])]
    #[TestWith([Dialect::PostgreSql, 'ROLLBACK AND NO CHAIN', 'ROLLBACK AND NO CHAIN'])]
    #[TestWith([Dialect::MySql, 'COMMIT AND CHAIN RELEASE', 'COMMIT AND CHAIN RELEASE'])]
    #[TestWith([Dialect::MySql, 'ROLLBACK NO RELEASE', 'ROLLBACK NO RELEASE'])]
    #[TestWith([Dialect::Sqlite, 'END TRANSACTION', 'COMMIT'])]
    #[TestWith([Dialect::PostgreSql, 'SAVEPOINT sp', 'SAVEPOINT "sp"'])]
    #[TestWith([Dialect::PostgreSql, 'RELEASE SAVEPOINT sp', 'RELEASE SAVEPOINT "sp"'])]
    #[TestWith([Dialect::Sqlite, 'ROLLBACK TO sp', 'ROLLBACK TO SAVEPOINT "sp"'])]
    #[TestWith([Dialect::PostgreSql, "PREPARE TRANSACTION 'tx'", "PREPARE TRANSACTION 'tx'"])]
    #[TestWith([Dialect::PostgreSql, "COMMIT PREPARED 'tx'", "COMMIT PREPARED 'tx'"])]
    #[TestWith([Dialect::PostgreSql, "ROLLBACK PREPARED 'tx'", "ROLLBACK PREPARED 'tx'"])]
    public function testWriteSerializesEachTransactionControlFromItsOperands(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($expected);
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testWriteKeepsChainingAndReleasePoliciesOnRebinding(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('COMMIT AND CHAIN RELEASE');
        self::assertInstanceOf(CommitTransactionStatement::class, $statement);
        $rebound = $binder->bind(Transactions::write($statement)->toString());
        self::assertInstanceOf(CommitTransactionStatement::class, $rebound);
        self::assertSame(Chaining::Chain, $rebound->chaining);
        self::assertSame(Release::Release, $rebound->release);
    }

    public function testBeginUsesStartTransactionForEveryMysqlTransactionStart(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $plain = $binder->bind('BEGIN');
        self::assertInstanceOf(BeginTransactionStatement::class, $plain);
        self::assertSame('START TRANSACTION', Transactions::begin($plain)->toString());
        $characterized = $binder->bind('START TRANSACTION READ ONLY');
        self::assertInstanceOf(BeginTransactionStatement::class, $characterized);
        self::assertSame('START TRANSACTION READ ONLY', Transactions::begin($characterized)->toString());
    }

    public function testBeginWritesAStartThatShowParseTreeAccepts(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build());
        $statement = $binder->bind('SHOW PARSE_TREE START TRANSACTION');
        self::assertSame('SHOW PARSE_TREE START TRANSACTION', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
