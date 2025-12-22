<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\ColumnAlreadyExistsException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ColumnAlreadyExistsException::class)]
final class ColumnAlreadyExistsExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessage(): void
    {
        $exception = new ColumnAlreadyExistsException(
            'ALTER TABLE users ADD COLUMN email VARCHAR(255)',
            'users',
            'email'
        );

        self::assertSame("Column 'email' already exists in table 'users'.", $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'ALTER TABLE users ADD COLUMN email VARCHAR(255)';
        $exception = new ColumnAlreadyExistsException($sql, 'users', 'email');

        self::assertSame($sql, $exception->getSql());
    }

    public function testGetTableNameReturnsTableName(): void
    {
        $exception = new ColumnAlreadyExistsException(
            'ALTER TABLE users ADD COLUMN email VARCHAR(255)',
            'users',
            'email'
        );

        self::assertSame('users', $exception->getTableName());
    }

    public function testGetColumnNameReturnsColumnName(): void
    {
        $exception = new ColumnAlreadyExistsException(
            'ALTER TABLE users ADD COLUMN email VARCHAR(255)',
            'users',
            'email'
        );

        self::assertSame('email', $exception->getColumnName());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new ColumnAlreadyExistsException('sql', 'table', 'column');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
