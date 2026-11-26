<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\TransactionRule;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Begin;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Commit;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Release;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Rollback;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\RollbackTo;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Savepoint;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\TransactionBehavior;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(TransactionRule::class)]
#[Medium]
final class TransactionRuleTest extends TestCase
{
    public function testCommandLowersEachTransactionCommandToItsOwnRequest(): void
    {
        $semantics = new Inspection();

        self::assertInstanceOf(Begin::class, $semantics->inspect('BEGIN')->statement);
        self::assertInstanceOf(Commit::class, $semantics->inspect('COMMIT')->statement);
        self::assertInstanceOf(Commit::class, $semantics->inspect('END')->statement);
        self::assertInstanceOf(Rollback::class, $semantics->inspect('ROLLBACK')->statement);
        self::assertInstanceOf(Savepoint::class, $semantics->inspect('SAVEPOINT s')->statement);
        self::assertInstanceOf(Release::class, $semantics->inspect('RELEASE s')->statement);
        self::assertInstanceOf(RollbackTo::class, $semantics->inspect('ROLLBACK TO s')->statement);
    }

    public function testBehaviorKeepsTheWrittenWordAndItsAbsence(): void
    {
        $semantics = new Inspection();
        $plain = $semantics->inspect('BEGIN')->statement;
        $deferred = $semantics->inspect('BEGIN DEFERRED')->statement;
        $immediate = $semantics->inspect('BEGIN IMMEDIATE')->statement;
        $exclusive = $semantics->inspect('BEGIN EXCLUSIVE')->statement;

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
        $semantics = new Inspection();
        $bare = $semantics->inspect('ROLLBACK TRANSACTION')->statement;
        $named = $semantics->inspect('ROLLBACK TRANSACTION t1')->statement;

        self::assertInstanceOf(Rollback::class, $bare);
        self::assertInstanceOf(Rollback::class, $named);
        self::assertNull($bare->name);
        self::assertSame('t1', $named->name?->value);
    }

    public function testSavepointReadsTheNameWithOrWithoutTheKeyword(): void
    {
        $semantics = new Inspection();
        $short = $semantics->inspect('RELEASE s1')->statement;
        $long = $semantics->inspect('RELEASE SAVEPOINT s1')->statement;

        self::assertInstanceOf(Release::class, $short);
        self::assertInstanceOf(Release::class, $long);
        self::assertSame('s1', $short->name->value);
        self::assertSame('s1', $long->name->value);
    }
}
