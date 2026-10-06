<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\TransactionRule;

#[CoversClass(TransactionRule::class)]
#[Medium]
final class TransactionRuleTest extends TestCase
{
    public function testStatementLowersEveryTransactionStatement(): void
    {
        self::assertSame('RELEASE SAVEPOINT s', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('release savepoint s')->toString());
    }

    public function testBeginLowersBeginWork(): void
    {
        self::assertSame('BEGIN', (new Semantics(Dialect::MySql))->analyze('begin work')->toString());
    }

    public function testStartRejectsReadOnlyWithReadWrite(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql))->analyze('START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT, READ WRITE');
    }

    public function testCompletionRejectsChainWithRelease(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('COMMIT AND CHAIN RELEASE');
    }

    public function testRollbackToLowersTheSavepoint(): void
    {
        self::assertSame('ROLLBACK TO SAVEPOINT s', (new Semantics(Dialect::MySql))->analyze('rollback work to savepoint s')->toString());
    }

    public function testChoiceLowersExplicitNo(): void
    {
        self::assertSame('COMMIT AND NO CHAIN NO RELEASE', (new Semantics(Dialect::MySql))->analyze('commit and no chain no release')->toString());
    }

    public function testWordAcceptsAnAbsentWork(): void
    {
        self::assertSame('ROLLBACK', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('rollback')->toString());
    }
}
