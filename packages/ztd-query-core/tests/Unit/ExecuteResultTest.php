<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\GenericExecuteResult;
use ZtdQuery\Rewrite\QueryKind;

#[CoversNothing]
final class ExecuteResultTest extends TestCase
{
    public function testIsPassthroughSaysWhetherTheOriginalStatementStillHasToRun(): void
    {
        self::assertTrue(GenericExecuteResult::passthrough()->isPassthrough());
        self::assertFalse(GenericExecuteResult::fromBufferedRows([], QueryKind::WRITE_SIMULATED)->isPassthrough());
    }

    public function testIsSuccessSaysWhetherTheStatementGotAsFarAsAResult(): void
    {
        self::assertTrue(GenericExecuteResult::fromBufferedRows([], QueryKind::READ)->isSuccess());
        self::assertFalse(GenericExecuteResult::failure(QueryKind::READ)->isSuccess());
    }

    public function testKindSaysWhatWasRun(): void
    {
        self::assertSame(
            QueryKind::WRITE_SIMULATED,
            GenericExecuteResult::fromBufferedRows([], QueryKind::WRITE_SIMULATED)->kind(),
        );
    }

    public function testFetchAnswersEachRowInTurnAndThenNothing(): void
    {
        $result = GenericExecuteResult::fromBufferedRows([['id' => 1], ['id' => 2]], QueryKind::READ);

        self::assertSame(['id' => 1], $result->fetch());
        self::assertSame(['id' => 2], $result->fetch());
        self::assertFalse($result->fetch());
    }

    public function testFetchAllAnswersEveryRowLeft(): void
    {
        $result = GenericExecuteResult::fromBufferedRows([['id' => 1], ['id' => 2]], QueryKind::READ);
        $result->fetch();

        self::assertSame([['id' => 2]], $result->fetchAll());
    }

    public function testRowCountAnswersHowManyRowsTheStatementCameTo(): void
    {
        $result = GenericExecuteResult::fromBufferedRows([['id' => 1]], QueryKind::WRITE_SIMULATED, 4);

        self::assertSame(4, $result->rowCount());
    }

    public function testHasResultSetSaysWhetherThereIsAnythingToFetch(): void
    {
        self::assertTrue(GenericExecuteResult::fromBufferedRows([['id' => 1]], QueryKind::READ)->hasResultSet());
        self::assertFalse(
            GenericExecuteResult::fromBufferedRows([], QueryKind::WRITE_SIMULATED)->hasResultSet(),
        );
    }
}
