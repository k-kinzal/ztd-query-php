<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\SchemaNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SchemaNotFoundException::class)]
final class SchemaNotFoundExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessage(): void
    {
        $exception = new SchemaNotFoundException(
            'SELECT * FROM users',
            'users'
        );

        self::assertSame("Table 'users' does not exist.", $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'SELECT * FROM users';
        $exception = new SchemaNotFoundException($sql, 'users');

        self::assertSame($sql, $exception->getSql());
    }

    public function testGetTableNameReturnsTableName(): void
    {
        $exception = new SchemaNotFoundException('sql', 'users');

        self::assertSame('users', $exception->getTableName());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new SchemaNotFoundException('sql', 'table');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
