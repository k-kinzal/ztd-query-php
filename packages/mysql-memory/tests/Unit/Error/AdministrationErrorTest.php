<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\AdministrationError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdministrationError::class)]
#[Small]
final class AdministrationErrorTest extends TestCase
{
    public function testNumberAnswersTheBackingValue(): void
    {
        self::assertSame(1193, AdministrationError::UnknownSystemVariable->number());
    }

    public function testTryFromFindsTheCaseOfANumberOfTheFamily(): void
    {
        self::assertSame(AdministrationError::UnknownSystemVariable, AdministrationError::tryFrom(1193));
    }

    public function testSqlStateAnswersTheStateOfTheError(): void
    {
        self::assertSame('HY000', AdministrationError::UnknownSystemVariable->sqlState());
    }

    public function testMessageFillsTheArgumentsIntoTheFormat(): void
    {
        self::assertSame("Unknown system variable 'nope'", AdministrationError::UnknownSystemVariable->message('nope'));
    }

    public function testErrorAnswersTheNumberStateAndMessage(): void
    {
        $error = AdministrationError::UnknownSystemVariable->error('nope');

        self::assertSame([1193, 'HY000', "Unknown system variable 'nope'", AdministrationError::UnknownSystemVariable], [$error->getCode(), $error->sqlState(), $error->getMessage(), $error->error]);
    }
}
