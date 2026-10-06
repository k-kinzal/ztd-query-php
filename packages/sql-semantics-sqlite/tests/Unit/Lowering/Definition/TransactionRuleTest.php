<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\TransactionRule;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Begin;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Commit;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Release;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Rollback;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\RollbackTo;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Savepoint;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\TransactionBehavior;

#[CoversClass(TransactionRule::class)]
#[Medium]
final class TransactionRuleTest extends TestCase
{
    public function testCommandLowersEachTransactionCommandToItsOwnRequest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(Begin::class, $semantics->analyze('BEGIN')->statement);
        self::assertInstanceOf(Commit::class, $semantics->analyze('COMMIT')->statement);
        self::assertInstanceOf(Commit::class, $semantics->analyze('END')->statement);
        self::assertInstanceOf(Rollback::class, $semantics->analyze('ROLLBACK')->statement);
        self::assertInstanceOf(Savepoint::class, $semantics->analyze('SAVEPOINT s')->statement);
        self::assertInstanceOf(Release::class, $semantics->analyze('RELEASE s')->statement);
        self::assertInstanceOf(RollbackTo::class, $semantics->analyze('ROLLBACK TO s')->statement);
    }

    public function testBehaviorKeepsTheWrittenWordAndItsAbsence(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('BEGIN')->statement;
        $deferred = $semantics->analyze('BEGIN DEFERRED')->statement;
        $immediate = $semantics->analyze('BEGIN IMMEDIATE')->statement;
        $exclusive = $semantics->analyze('BEGIN EXCLUSIVE')->statement;

        self::assertInstanceOf(Begin::class, $plain);
        self::assertInstanceOf(Begin::class, $deferred);
        self::assertInstanceOf(Begin::class, $immediate);
        self::assertInstanceOf(Begin::class, $exclusive);
        self::assertNull($plain->behavior);
        self::assertSame(TransactionBehavior::Deferred, $deferred->behavior);
        self::assertSame(TransactionBehavior::Immediate, $immediate->behavior);
        self::assertSame(TransactionBehavior::Exclusive, $exclusive->behavior);
    }

    public function testNameIsNullUnlessANameFollowsTheKeyword(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('ROLLBACK TRANSACTION')->statement;
        $named = $semantics->analyze('ROLLBACK TRANSACTION t1')->statement;

        self::assertInstanceOf(Rollback::class, $bare);
        self::assertInstanceOf(Rollback::class, $named);
        self::assertNull($bare->name);
        self::assertSame('t1', $named->name?->value);
    }

    public function testSavepointReadsTheNameWithOrWithoutTheKeyword(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $short = $semantics->analyze('RELEASE s1')->statement;
        $long = $semantics->analyze('RELEASE SAVEPOINT s1')->statement;

        self::assertInstanceOf(Release::class, $short);
        self::assertInstanceOf(Release::class, $long);
        self::assertSame('s1', $short->name->value);
        self::assertSame('s1', $long->name->value);
    }
}
