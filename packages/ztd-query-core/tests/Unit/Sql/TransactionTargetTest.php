<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\RecordingTransactionTarget;
use ZtdQuery\Sql\TransactionStatement;

#[CoversNothing]
final class TransactionTargetTest extends TestCase
{
    public function testBeginIsWhatABeginStatementAsksTheTargetFor(): void
    {
        $target = new RecordingTransactionTarget();

        TransactionStatement::begin()->apply($target);

        self::assertSame(['begin'], $target->asked);
    }

    public function testCommitIsWhatACommitStatementAsksTheTargetFor(): void
    {
        $target = new RecordingTransactionTarget();

        TransactionStatement::commit()->apply($target);

        self::assertSame(['commit'], $target->asked);
    }

    public function testRollBackIsWhatARollbackStatementAsksTheTargetFor(): void
    {
        $target = new RecordingTransactionTarget();

        TransactionStatement::rollback()->apply($target);

        self::assertSame(['rollBack'], $target->asked);
    }

    public function testSavepointCarriesTheNameTheStatementNamed(): void
    {
        $target = new RecordingTransactionTarget();

        TransactionStatement::savepoint('sp')->apply($target);

        self::assertSame(['savepoint sp'], $target->asked);
    }

    public function testRollBackToCarriesTheNameTheStatementNamed(): void
    {
        $target = new RecordingTransactionTarget();

        TransactionStatement::rollbackTo('sp')->apply($target);

        self::assertSame(['rollBackTo sp'], $target->asked);
    }

    public function testReleaseCarriesTheNameTheStatementNamed(): void
    {
        $target = new RecordingTransactionTarget();

        TransactionStatement::release('sp')->apply($target);

        self::assertSame(['release sp'], $target->asked);
    }
}
