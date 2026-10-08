<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\AccountError;
use MySqlMemory\Error\AdministrationError;
use MySqlMemory\Error\DataError;
use MySqlMemory\Error\ErrorNumbers;
use MySqlMemory\Error\ProgramError;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Error\SchemaError;
use MySqlMemory\Error\StatementError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorNumbers::class)]
#[Small]
final class ErrorNumbersTest extends TestCase
{
    public function testTryFromFindsTheErrorOfANumberInEveryFamily(): void
    {
        self::assertSame(
            [AccountError::CannotUser, AdministrationError::UnknownSystemVariable, DataError::DivisionByZero, ProgramError::RoutineMissing, QueryError::NoSuchTable, SchemaError::TableExists, StatementError::EmptyQuery, AccountError::FactorIdentical, StatementError::HypergraphRequired, AdministrationError::FileStat],
            [ErrorNumbers::tryFrom(1396), ErrorNumbers::tryFrom(1193), ErrorNumbers::tryFrom(1365), ErrorNumbers::tryFrom(1305), ErrorNumbers::tryFrom(1146), ErrorNumbers::tryFrom(1050), ErrorNumbers::tryFrom(1065), ErrorNumbers::tryFrom(4063), ErrorNumbers::tryFrom(6037), ErrorNumbers::tryFrom(13)],
        );
    }

    public function testTryFromAnswersNullForANumberTheEmulatorDoesNotRaise(): void
    {
        self::assertSame([null, null], [ErrorNumbers::tryFrom(0), ErrorNumbers::tryFrom(1000)]);
    }

    public function testFromFindsTheErrorOfANumber(): void
    {
        self::assertSame(AdministrationError::IncorrectGlobalLocalVariable, ErrorNumbers::from(1238));
    }

    public function testFamiliesListsTheEnumsOfTheErrorFamilies(): void
    {
        self::assertSame([AccountError::class, AdministrationError::class, DataError::class, ProgramError::class, QueryError::class, SchemaError::class, StatementError::class], ErrorNumbers::FAMILIES);
    }
}
