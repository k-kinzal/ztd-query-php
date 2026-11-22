<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\TransactionRule;
use SqlSemantics\Platform\MySql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TransactionRule::class)]
#[Medium]
final class TransactionRuleTest extends TestCase
{
    public function testStatementLowersEveryTransactionStatement(): void
    {
        self::assertSame('RELEASE SAVEPOINT s', (new Inspection('mysql-5.6.51'))->inspect('release savepoint s')->toString());
    }

    public function testBeginLowersBeginWork(): void
    {
        self::assertSame('BEGIN', (new Inspection())->inspect('begin work')->toString());
    }

    public function testStartRejectsReadOnlyWithReadWrite(): void
    {
        $this->expectException(AnalysisException::class);

        (new Inspection())->inspect('START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT, READ WRITE');
    }

    public function testCompletionRejectsChainWithRelease(): void
    {
        $this->expectException(AnalysisException::class);

        (new Inspection('mysql-5.7.44'))->inspect('COMMIT AND CHAIN RELEASE');
    }

    public function testRollbackToLowersTheSavepoint(): void
    {
        self::assertSame('ROLLBACK TO SAVEPOINT s', (new Inspection())->inspect('rollback work to savepoint s')->toString());
    }

    public function testChoiceLowersExplicitNo(): void
    {
        self::assertSame('COMMIT AND NO CHAIN NO RELEASE', (new Inspection())->inspect('commit and no chain no release')->toString());
    }

    public function testWordAcceptsAnAbsentWork(): void
    {
        self::assertSame('ROLLBACK', (new Inspection('mysql-9.1.0'))->inspect('rollback')->toString());
    }
}
