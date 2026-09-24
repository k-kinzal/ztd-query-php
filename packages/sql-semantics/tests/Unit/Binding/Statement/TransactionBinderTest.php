<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\TransactionBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TransactionBinder::class)]
#[Medium]
final class TransactionBinderTest extends TestCase
{
    public function testBindReadsTransactionStartsWithTheirMode(): void
    {
        $sqlite = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('BEGIN IMMEDIATE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\BeginTransactionStatement::class, $sqlite);
        self::assertSame(\SqlSemantics\Model\Transaction\Mode::Immediate, $sqlite->mode);
        self::assertSame('BEGIN IMMEDIATE', $sqlite->toString());
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\BeginTransactionStatement::class, $mysql);
        self::assertNull($mysql->mode);
        self::assertSame(\SqlSemantics\Model\Transaction\Access::ReadOnly, $mysql->characteristics->access);
        self::assertTrue($mysql->characteristics->consistentSnapshot);
    }

    public function testCharacteristicsReadIsolationAccessAndDeferrability(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('BEGIN ISOLATION LEVEL REPEATABLE READ READ WRITE NOT DEFERRABLE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\BeginTransactionStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Transaction\Isolation::RepeatableRead, $statement->characteristics->isolation);
        self::assertSame(\SqlSemantics\Model\Transaction\Access::ReadWrite, $statement->characteristics->access);
        self::assertFalse($statement->characteristics->deferrable);
        self::assertFalse($statement->characteristics->consistentSnapshot);
        $characteristics = (new TransactionBinder())->characteristics(['START', 'TRANSACTION', 'DEFERRABLE']);
        self::assertNull($characteristics->isolation);
        self::assertNull($characteristics->access);
        self::assertTrue($characteristics->deferrable);
    }

    public function testBindReadsSavepointOperations(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $savepoint = $binder->bind('SAVEPOINT s');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\SavepointStatement::class, $savepoint);
        self::assertSame('s', $savepoint->name);
        $release = $binder->bind('RELEASE SAVEPOINT s');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\ReleaseSavepointStatement::class, $release);
        self::assertSame('s', $release->name);
        $rollback = $binder->bind('ROLLBACK TO SAVEPOINT s');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\RollbackToSavepointStatement::class, $rollback);
        self::assertSame('ROLLBACK TO SAVEPOINT "s"', $rollback->toString());
    }

    public function testBindReadsChainingAndReleasePoliciesOnBoundaries(): void
    {
        $mysql = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $commit = $mysql->bind('COMMIT AND CHAIN NO RELEASE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\CommitTransactionStatement::class, $commit);
        self::assertSame(\SqlSemantics\Model\Transaction\Chaining::Chain, $commit->chaining);
        self::assertSame(\SqlSemantics\Model\Transaction\Release::NoRelease, $commit->release);
        $rollback = $mysql->bind('ROLLBACK AND NO CHAIN RELEASE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\RollbackTransactionStatement::class, $rollback);
        self::assertSame(\SqlSemantics\Model\Transaction\Chaining::NoChain, $rollback->chaining);
        self::assertSame(\SqlSemantics\Model\Transaction\Release::Release, $rollback->release);
        $postgres = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $end = $postgres->bind('END');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\CommitTransactionStatement::class, $end);
        self::assertSame(\SqlSemantics\Model\Transaction\Chaining::Default, $end->chaining);
        self::assertSame('COMMIT', $end->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\RollbackTransactionStatement::class, $postgres->bind('ABORT'));
    }

    public function testPreparedBindsTwoPhaseCommitOperationsByTheirIdentifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $prepare = $binder->bind("PREPARE TRANSACTION 'tx'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\PrepareTransactionStatement::class, $prepare);
        self::assertSame("'tx'", $prepare->transactionId->text);
        $commit = $binder->bind("COMMIT PREPARED 'tx'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\CommitPreparedStatement::class, $commit);
        self::assertSame("'tx'", $commit->transactionId->text);
        $rollback = $binder->bind("ROLLBACK PREPARED 'tx'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\RollbackPreparedStatement::class, $rollback);
        self::assertSame("ROLLBACK PREPARED 'tx'", $rollback->toString());
    }

    #[TestWith(["PREPARE TRANSACTION E'a\\nb'", \SqlSemantics\Model\Statement\Transaction\PrepareTransactionStatement::class])]
    #[TestWith(["PREPARE TRANSACTION U&'a!0041' UESCAPE '!'", \SqlSemantics\Model\Statement\Transaction\PrepareTransactionStatement::class])]
    #[TestWith(['COMMIT PREPARED $$tx$$', \SqlSemantics\Model\Statement\Transaction\CommitPreparedStatement::class])]
    #[TestWith(["ROLLBACK PREPARED e'tx'", \SqlSemantics\Model\Statement\Transaction\RollbackPreparedStatement::class])]
    public function testPreparedKeepsEveryStringConstantSpelling(string $sql, string $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($sql, $statement->toString());
        self::assertSame($sql, $binder->bind($statement->toString())->toString());
    }
}
