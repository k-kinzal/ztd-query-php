<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\ErrorNumbers;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\PartitionError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\Family\TransactionError;
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
        self::assertSame([AccountError::class, AdministrationError::class, ConstraintError::class, DataError::class, PartitionError::class, ProgramError::class, QueryError::class, SchemaError::class, StatementError::class, TransactionError::class], ErrorNumbers::FAMILIES);
    }
}
