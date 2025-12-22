<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\UnknownSchemaException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UnknownSchemaException::class)]
final class UnknownSchemaExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessageForTable(): void
    {
        $exception = new UnknownSchemaException(
            'SELECT * FROM users',
            'users',
            'table'
        );

        self::assertSame('Unknown table: users', $exception->getMessage());
    }

    public function testGetMessageReturnsFormattedMessageForColumn(): void
    {
        $exception = new UnknownSchemaException(
            'SELECT email FROM users',
            'email',
            'column'
        );

        self::assertSame('Unknown column: email', $exception->getMessage());
    }

    public function testGetMessageDefaultsToTable(): void
    {
        $exception = new UnknownSchemaException(
            'SELECT * FROM users',
            'users'
        );

        self::assertSame('Unknown table: users', $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'SELECT * FROM users';
        $exception = new UnknownSchemaException($sql, 'users');

        self::assertSame($sql, $exception->getSql());
    }

    public function testGetIdentifierReturnsIdentifier(): void
    {
        $exception = new UnknownSchemaException('sql', 'users');

        self::assertSame('users', $exception->getIdentifier());
    }

    public function testGetIdentifierTypeReturnsIdentifierType(): void
    {
        $exception = new UnknownSchemaException('sql', 'email', 'column');

        self::assertSame('column', $exception->getIdentifierType());
    }

    public function testGetIdentifierTypeDefaultsToTable(): void
    {
        $exception = new UnknownSchemaException('sql', 'users');

        self::assertSame('table', $exception->getIdentifierType());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new UnknownSchemaException('sql', 'identifier');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
