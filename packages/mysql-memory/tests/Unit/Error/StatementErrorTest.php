<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\StatementError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementError::class)]
#[Small]
final class StatementErrorTest extends TestCase
{
    public function testNumberAnswersTheBackingValue(): void
    {
        self::assertSame(1235, StatementError::NotSupportedYet->number());
    }

    public function testTryFromFindsTheCaseOfANumberOfTheFamily(): void
    {
        self::assertSame(StatementError::NotSupportedYet, StatementError::tryFrom(1235));
    }

    public function testSqlStateAnswersTheStateOfTheError(): void
    {
        self::assertSame('42000', StatementError::NotSupportedYet->sqlState());
    }

    public function testMessageFillsTheArgumentsIntoTheFormat(): void
    {
        self::assertSame("This version of MySQL doesn't yet support 'this'", StatementError::NotSupportedYet->message('this'));
    }

    public function testErrorAnswersTheNumberStateAndMessage(): void
    {
        $error = StatementError::NotSupportedYet->error('this');

        self::assertSame([1235, '42000', "This version of MySQL doesn't yet support 'this'", StatementError::NotSupportedYet], [$error->getCode(), $error->sqlState(), $error->getMessage(), $error->error]);
    }
}
