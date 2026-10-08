<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Family;

use MySqlMemory\Error\Family\DataError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataError::class)]
#[Small]
final class DataErrorTest extends TestCase
{
    public function testNumberAnswersTheBackingValue(): void
    {
        self::assertSame(1062, DataError::DuplicateEntry->number());
    }

    public function testTryFromFindsTheCaseOfANumberOfTheFamily(): void
    {
        self::assertSame(DataError::DuplicateEntry, DataError::tryFrom(1062));
    }

    public function testSqlStateAnswersTheStateOfTheError(): void
    {
        self::assertSame('23000', DataError::DuplicateEntry->sqlState());
    }

    public function testMessageFillsTheArgumentsIntoTheFormat(): void
    {
        self::assertSame("Duplicate entry '1' for key 't.PRIMARY'", DataError::DuplicateEntry->message('1', 't.PRIMARY'));
    }

    public function testErrorAnswersTheNumberStateAndMessage(): void
    {
        $error = DataError::DuplicateEntry->error('1', 't.PRIMARY');

        self::assertSame([1062, '23000', "Duplicate entry '1' for key 't.PRIMARY'", DataError::DuplicateEntry], [$error->getCode(), $error->sqlState(), $error->getMessage(), $error->error]);
    }
}
