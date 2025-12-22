<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeStatement;
use ZtdQuery\GenericExecuteResult;
use ZtdQuery\Rewrite\QueryKind;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GenericExecuteResult::class)]
final class GenericExecuteResultTest extends TestCase
{
    public function testPassthroughResult(): void
    {
        $result = GenericExecuteResult::passthrough();

        self::assertTrue($result->isPassthrough());
        self::assertTrue($result->isSuccess());
        self::assertSame(QueryKind::READ, $result->kind());
        self::assertTrue($result->hasResultSet());
    }

    public function testFailureResult(): void
    {
        $result = GenericExecuteResult::failure(QueryKind::WRITE_SIMULATED);

        self::assertFalse($result->isPassthrough());
        self::assertFalse($result->isSuccess());
        self::assertSame(QueryKind::WRITE_SIMULATED, $result->kind());
    }

    public function testFromBufferedRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = GenericExecuteResult::fromBufferedRows($rows);

        self::assertFalse($result->isPassthrough());
        self::assertTrue($result->isSuccess());
        self::assertSame(QueryKind::WRITE_SIMULATED, $result->kind());
        self::assertSame(2, $result->rowCount());
    }

    public function testFetchFromBufferedRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = GenericExecuteResult::fromBufferedRows($rows);

        self::assertSame(['id' => 1, 'name' => 'Alice'], $result->fetch());
        self::assertSame(['id' => 2, 'name' => 'Bob'], $result->fetch());
        self::assertFalse($result->fetch());
    }

    public function testFetchAllFromBufferedRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = GenericExecuteResult::fromBufferedRows($rows);

        self::assertSame($rows, $result->fetchAll());
    }

    public function testFetchAllAfterPartialFetch(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Carol'],
        ];

        $result = GenericExecuteResult::fromBufferedRows($rows);
        $result->fetch();

        self::assertSame(
            [['id' => 2, 'name' => 'Bob'], ['id' => 3, 'name' => 'Carol']],
            $result->fetchAll()
        );
    }

    public function testFromStatement(): void
    {
        $statement = new FakeStatement([['id' => 1]]);
        $result = GenericExecuteResult::fromStatement($statement);

        self::assertFalse($result->isPassthrough());
        self::assertTrue($result->isSuccess());
        self::assertSame(QueryKind::READ, $result->kind());
        self::assertTrue($result->hasResultSet());
    }

    public function testFetchFromStatement(): void
    {
        $statement = new FakeStatement([['id' => 1, 'name' => 'Alice']]);
        $result = GenericExecuteResult::fromStatement($statement);

        self::assertSame(['id' => 1, 'name' => 'Alice'], $result->fetch());
    }

    public function testFetchFromStatementReturnsSecondRowOnSubsequentCall(): void
    {
        $statement = new FakeStatement([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);
        $result = GenericExecuteResult::fromStatement($statement);

        $result->fetch();
        self::assertSame(['id' => 2, 'name' => 'Bob'], $result->fetch());
    }

    public function testFetchFromEmptyStatementReturnsFalse(): void
    {
        $statement = new FakeStatement([]);
        $result = GenericExecuteResult::fromStatement($statement);

        self::assertFalse($result->fetch());
    }

    public function testRowCountFromStatement(): void
    {
        $statement = new FakeStatement([['id' => 1]]);
        $result = GenericExecuteResult::fromStatement($statement);

        self::assertSame(1, $result->rowCount());
    }

    public function testPassthroughRowCountIsZero(): void
    {
        $result = GenericExecuteResult::passthrough();

        self::assertSame(0, $result->rowCount());
    }

    public function testResetBuffer(): void
    {
        $rows = [['id' => 1], ['id' => 2]];
        $result = GenericExecuteResult::fromBufferedRows($rows);
        $result->fetch();
        $result->fetch();

        $result->resetBuffer();

        self::assertSame(['id' => 1], $result->fetch());
    }

    public function testHasResultSetForWriteIsFalse(): void
    {
        $result = GenericExecuteResult::fromBufferedRows([]);

        self::assertFalse($result->hasResultSet());
    }

    public function testPassthroughFetchReturnsFalse(): void
    {
        $result = GenericExecuteResult::passthrough();

        self::assertFalse($result->fetch());
    }

    public function testPassthroughFetchAllReturnsEmpty(): void
    {
        $result = GenericExecuteResult::passthrough();

        self::assertSame([], $result->fetchAll());
    }

    public function testFromStatementAndRows(): void
    {
        $statement = new FakeStatement([['id' => 1]]);
        $rows = [['id' => 1, 'name' => 'Alice']];

        $result = GenericExecuteResult::fromStatementAndRows($statement, $rows);

        self::assertFalse($result->isPassthrough());
        self::assertTrue($result->isSuccess());
        self::assertSame(1, $result->rowCount());
    }
}
