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

    public function testSqlStateOfAStatementThatFails(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $this->expectExceptionMessage('No database selected');

        $session->query('SELECT * FROM nowhere');
    }
}
