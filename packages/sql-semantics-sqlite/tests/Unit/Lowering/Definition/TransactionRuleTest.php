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

        self::assertInstanceOf(Begin::class, $semantics->analyze('BEGIN'));
        self::assertInstanceOf(Commit::class, $semantics->analyze('COMMIT'));
        self::assertInstanceOf(Commit::class, $semantics->analyze('END'));
        self::assertInstanceOf(Rollback::class, $semantics->analyze('ROLLBACK'));
        self::assertInstanceOf(Savepoint::class, $semantics->analyze('SAVEPOINT s'));
        self::assertInstanceOf(Release::class, $semantics->analyze('RELEASE s'));
        self::assertInstanceOf(RollbackTo::class, $semantics->analyze('ROLLBACK TO s'));
    }

    public function testBehaviorKeepsTheWrittenWordAndItsAbsence(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('BEGIN');
        $deferred = $semantics->analyze('BEGIN DEFERRED');
        $immediate = $semantics->analyze('BEGIN IMMEDIATE');
        $exclusive = $semantics->analyze('BEGIN EXCLUSIVE');

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
        $bare = $semantics->analyze('ROLLBACK TRANSACTION');
        $named = $semantics->analyze('ROLLBACK TRANSACTION t1');

        self::assertInstanceOf(Rollback::class, $bare);
        self::assertInstanceOf(Rollback::class, $named);
        self::assertNull($bare->name);
        self::assertSame('t1', $named->name?->value);
    }

    public function testSavepointReadsTheNameWithOrWithoutTheKeyword(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $short = $semantics->analyze('RELEASE s1');
        $long = $semantics->analyze('RELEASE SAVEPOINT s1');

        self::assertInstanceOf(Release::class, $short);
        self::assertInstanceOf(Release::class, $long);
        self::assertSame('s1', $short->name->value);
        self::assertSame('s1', $long->name->value);
    }
}
