<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\ProgramError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProgramError::class)]
#[Small]
final class ProgramErrorTest extends TestCase
{
    public function testNumberAnswersTheBackingValue(): void
    {
        self::assertSame(1305, ProgramError::RoutineMissing->number());
    }

    public function testTryFromFindsTheCaseOfANumberOfTheFamily(): void
    {
        self::assertSame(ProgramError::RoutineMissing, ProgramError::tryFrom(1305));
    }

    public function testSqlStateAnswersTheStateOfTheError(): void
    {
        self::assertSame('42000', ProgramError::RoutineMissing->sqlState());
    }

    public function testMessageFillsTheArgumentsIntoTheFormat(): void
    {
        self::assertSame('PROCEDURE d.p does not exist', ProgramError::RoutineMissing->message('PROCEDURE', 'd.p'));
    }

    public function testErrorAnswersTheNumberStateAndMessage(): void
    {
        $error = ProgramError::RoutineMissing->error('PROCEDURE', 'd.p');

        self::assertSame([1305, '42000', 'PROCEDURE d.p does not exist', ProgramError::RoutineMissing], [$error->getCode(), $error->sqlState(), $error->getMessage(), $error->error]);
    }
}
