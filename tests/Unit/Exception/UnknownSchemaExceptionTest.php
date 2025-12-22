<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\UnknownSchemaException;

final class UnknownSchemaExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessageForTable(): void
    {
        $exception = new UnknownSchemaException(
            'SELECT * FROM users',
            'users',
            'table'
        );

        $this->assertSame('Unknown table: users', $exception->getMessage());
    }

    public function testGetMessageReturnsFormattedMessageForColumn(): void
    {
        $exception = new UnknownSchemaException(
            'SELECT email FROM users',
            'email',
            'column'
        );

        $this->assertSame('Unknown column: email', $exception->getMessage());
    }

    public function testGetMessageDefaultsToTable(): void
    {
        $exception = new UnknownSchemaException(
            'SELECT * FROM users',
            'users'
        );

        $this->assertSame('Unknown table: users', $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'SELECT * FROM users';
        $exception = new UnknownSchemaException($sql, 'users');

        $this->assertSame($sql, $exception->getSql());
    }

    public function testGetIdentifierReturnsIdentifier(): void
    {
        $exception = new UnknownSchemaException('sql', 'users');

        $this->assertSame('users', $exception->getIdentifier());
    }

    public function testGetIdentifierTypeReturnsIdentifierType(): void
    {
        $exception = new UnknownSchemaException('sql', 'email', 'column');

        $this->assertSame('column', $exception->getIdentifierType());
    }

    public function testGetIdentifierTypeDefaultsToTable(): void
    {
        $exception = new UnknownSchemaException('sql', 'users');

        $this->assertSame('table', $exception->getIdentifierType());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new UnknownSchemaException('sql', 'identifier');

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }
}
