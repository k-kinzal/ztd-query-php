<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SqlError::class)]
#[Small]
final class SqlErrorTest extends TestCase
{
    public function testSqlStateAnswersTheStateOfTheError(): void
    {
        $error = new SqlError(ErrorCode::BadDatabase, "Unknown database 'shop'");

        self::assertSame('42000', $error->sqlState());
        self::assertSame(1049, $error->getCode());
        self::assertSame("Unknown database 'shop'", $error->getMessage());
    }

    public function testSqlStateKeepsTheFailureTheErrorReports(): void
    {
        $previous = new RuntimeException('cause');
        $error = new SqlError(ErrorCode::UnknownError, 'Unknown error', $previous);

        self::assertSame('HY000', $error->sqlState());
        self::assertSame($previous, $error->getPrevious());
    }

    public function testSqlStateAnswersTheStateASignalGave(): void
    {
        $error = new SqlError(ErrorCode::SignalException, 'boom', null, [], ['RETURNED_SQLSTATE' => '45001'], 5001);

        self::assertSame(['45001', 5001, 'boom'], [$error->sqlState(), $error->getCode(), $error->getMessage()]);
    }

    public function testSqlStateOfAStatementThatFails(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $this->expectExceptionMessage('No database selected');

        $session->query('SELECT * FROM nowhere');
    }

    public function testSqlStateOfAnErrorFollowedByOtherErrors(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE w (p INT NOT NULL, q INT NOT NULL)');

        $session->run('INSERT INTO w (p) SELECT NULL');

        self::assertSame([[1048, "Column 'p' cannot be null"], [1364, "Field 'q' doesn't have a default value"]], array_map(static fn (array $condition): array => [$condition[1], $condition[2]], $session->diagnostics->conditions));
        self::assertSame(['22032', [[3140, 'x']]], [(new SqlError(ErrorCode::JsonDocumentTooDeep, 'y', null, [[3140, 'x']]))->sqlState(), (new SqlError(ErrorCode::JsonDocumentTooDeep, 'y', null, [[3140, 'x']]))->following]);
    }

    public function testSqlStateIsTheStateOfAnErrorRecordedWhileParsing(): void
    {
        $error = new SqlError(ErrorCode::UnknownCollation, "Unknown collation: 'x'", null, [], null, null, true);

        self::assertSame('HY000', $error->sqlState());
        self::assertTrue($error->recorded);
    }

}
