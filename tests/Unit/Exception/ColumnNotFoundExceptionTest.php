<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\ColumnNotFoundException;

final class ColumnNotFoundExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessage(): void
    {
        $exception = new ColumnNotFoundException(
            'SELECT email FROM users',
            'users',
            'email'
        );

        $this->assertSame("Column 'email' does not exist in table 'users'.", $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'SELECT email FROM users';
        $exception = new ColumnNotFoundException($sql, 'users', 'email');

        $this->assertSame($sql, $exception->getSql());
    }

    public function testGetTableNameReturnsTableName(): void
    {
        $exception = new ColumnNotFoundException(
            'SELECT email FROM users',
            'users',
            'email'
        );

        $this->assertSame('users', $exception->getTableName());
    }

    public function testGetColumnNameReturnsColumnName(): void
    {
        $exception = new ColumnNotFoundException(
            'SELECT email FROM users',
            'users',
            'email'
        );

        $this->assertSame('email', $exception->getColumnName());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new ColumnNotFoundException('sql', 'table', 'column');

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }
}
