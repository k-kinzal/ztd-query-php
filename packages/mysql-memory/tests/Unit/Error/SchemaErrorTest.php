<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\SchemaError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaError::class)]
#[Small]
final class SchemaErrorTest extends TestCase
{
    public function testNumberAnswersTheBackingValue(): void
    {
        self::assertSame(1050, SchemaError::TableExists->number());
    }

    public function testTryFromFindsTheCaseOfANumberOfTheFamily(): void
    {
        self::assertSame(SchemaError::TableExists, SchemaError::tryFrom(1050));
    }

    public function testSqlStateAnswersTheStateOfTheError(): void
    {
        self::assertSame('42S01', SchemaError::TableExists->sqlState());
    }

    public function testMessageFillsTheArgumentsIntoTheFormat(): void
    {
        self::assertSame("Table 't' already exists", SchemaError::TableExists->message('t'));
    }

    public function testErrorAnswersTheNumberStateAndMessage(): void
    {
        $error = SchemaError::TableExists->error('t');

        self::assertSame([1050, '42S01', "Table 't' already exists", SchemaError::TableExists], [$error->getCode(), $error->sqlState(), $error->getMessage(), $error->error]);
    }
}
