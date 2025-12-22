<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\SqlParseException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SqlParseException::class)]
final class SqlParseExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessage(): void
    {
        $exception = new SqlParseException(
            'SELECT * FORM users',
            'Unexpected token "FORM" at position 9'
        );

        self::assertSame('SQL syntax error: Unexpected token "FORM" at position 9', $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'SELECT * FORM users';
        $exception = new SqlParseException($sql, 'error message');

        self::assertSame($sql, $exception->getSql());
    }

    public function testGetParseErrorReturnsParseError(): void
    {
        $parseError = 'Unexpected token "FORM" at position 9';
        $exception = new SqlParseException('sql', $parseError);

        self::assertSame($parseError, $exception->getParseError());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new SqlParseException('sql', 'error');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
