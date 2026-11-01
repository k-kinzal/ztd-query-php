<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\CatalogedError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CatalogedError::class)]
#[Small]
final class CatalogedErrorTest extends TestCase
{
    public function testNumberAnswersTheBackingValueOfTheCase(): void
    {
        self::assertSame([1146, 1365], [QueryError::NoSuchTable->number(), DataError::DivisionByZero->number()]);
    }

    public function testSqlStateReadsTheStateFromTheCatalog(): void
    {
        self::assertSame(['42S02', '22012', 'HY000'], [QueryError::NoSuchTable->sqlState(), DataError::DivisionByZero->sqlState(), StatementError::UnknownError->sqlState()]);
    }

    public function testMessageFillsTheArgumentsIntoTheFormatOfTheCatalog(): void
    {
        self::assertSame("Table 'd.t' doesn't exist", QueryError::NoSuchTable->message('d', 't'));
    }

    public function testMessageAnswersTheFirstArgumentWhenTheFormatHasNoPlaceholder(): void
    {
        self::assertSame('Something went wrong', StatementError::UnknownError->message('Something went wrong'));
    }

    public function testErrorBuildsTheSqlErrorOfTheCase(): void
    {
        $error = DataError::DivisionByZero->error();

        self::assertSame([1365, '22012', 'Division by 0', DataError::DivisionByZero], [$error->getCode(), $error->sqlState(), $error->getMessage(), $error->error]);
    }
}
