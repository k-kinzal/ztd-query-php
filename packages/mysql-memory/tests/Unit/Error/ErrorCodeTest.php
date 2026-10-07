<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\ErrorCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorCode::class)]
#[Small]
final class ErrorCodeTest extends TestCase
{
    public function testSqlStateAnswersTheStateOfTheErrorNumber(): void
    {
        self::assertSame('23000', ErrorCode::DuplicateEntry->sqlState());
        self::assertSame('42S02', ErrorCode::NoSuchTable->sqlState());
        self::assertSame('3D000', ErrorCode::NoDatabase->sqlState());
        self::assertSame('HY000', ErrorCode::UnknownError->sqlState());
    }

    public function testMessageFillsTheArgumentsIntoTheFormat(): void
    {
        self::assertSame("Unknown column 'a' in 'field list'", ErrorCode::BadField->message('a', 'field list'));
        self::assertSame("Column count doesn't match value count at row 2", ErrorCode::WrongValueCountOnRow->message(2));
    }

    public function testMessageAnswersTheFormatOfAnErrorWithoutArguments(): void
    {
        self::assertSame('Query was empty', ErrorCode::EmptyQuery->message());
    }

    public function testMessageAnswersTheFirstArgumentWhenTheFormatHasNoPlaceholder(): void
    {
        self::assertSame('Window name is defined more than once', ErrorCode::UnknownError->message('Window name is defined more than once'));
    }

    public function testErrorAnswersTheNumberStateAndMessage(): void
    {
        $error = ErrorCode::NoSuchTable->error('shop', 'items');

        self::assertSame(1146, $error->getCode());
        self::assertSame('42S02', $error->sqlState());
        self::assertSame("Table 'shop.items' doesn't exist", $error->getMessage());
        self::assertSame(ErrorCode::NoSuchTable, $error->error);
    }
}
