<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\QueryError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryError::class)]
#[Small]
final class QueryErrorTest extends TestCase
{
    public function testNumberAnswersTheBackingValue(): void
    {
        self::assertSame(1146, QueryError::NoSuchTable->number());
    }

    public function testTryFromFindsTheCaseOfANumberOfTheFamily(): void
    {
        self::assertSame(QueryError::NoSuchTable, QueryError::tryFrom(1146));
    }

    public function testSqlStateAnswersTheStateOfTheError(): void
    {
        self::assertSame('42S02', QueryError::NoSuchTable->sqlState());
    }

    public function testMessageFillsTheArgumentsIntoTheFormat(): void
    {
        self::assertSame("Table 'shop.items' doesn't exist", QueryError::NoSuchTable->message('shop', 'items'));
    }

    public function testErrorAnswersTheNumberStateAndMessage(): void
    {
        $error = QueryError::NoSuchTable->error('shop', 'items');

        self::assertSame([1146, '42S02', "Table 'shop.items' doesn't exist", QueryError::NoSuchTable], [$error->getCode(), $error->sqlState(), $error->getMessage(), $error->error]);
    }
}
