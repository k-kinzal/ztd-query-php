<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\NotNullViolationException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NotNullViolationException::class)]
final class NotNullViolationExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessage(): void
    {
        $exception = new NotNullViolationException(
            'INSERT INTO users (name) VALUES (NULL)',
            'users',
            'name'
        );

        self::assertSame("Column 'name' in table 'users' cannot be NULL.", $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'INSERT INTO users (name) VALUES (NULL)';
        $exception = new NotNullViolationException($sql, 'users', 'name');

        self::assertSame($sql, $exception->getSql());
    }

    public function testGetTableNameReturnsTableName(): void
    {
        $exception = new NotNullViolationException('sql', 'users', 'name');

        self::assertSame('users', $exception->getTableName());
    }

    public function testGetColumnNameReturnsColumnName(): void
    {
        $exception = new NotNullViolationException('sql', 'users', 'name');

        self::assertSame('name', $exception->getColumnName());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new NotNullViolationException('sql', 'table', 'column');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
