<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\TableAlreadyExistsException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TableAlreadyExistsException::class)]
final class TableAlreadyExistsExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessage(): void
    {
        $exception = new TableAlreadyExistsException(
            'CREATE TABLE users (id INT)',
            'users'
        );

        self::assertSame("Table 'users' already exists.", $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'CREATE TABLE users (id INT)';
        $exception = new TableAlreadyExistsException($sql, 'users');

        self::assertSame($sql, $exception->getSql());
    }

    public function testGetTableNameReturnsTableName(): void
    {
        $exception = new TableAlreadyExistsException('sql', 'users');

        self::assertSame('users', $exception->getTableName());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new TableAlreadyExistsException('sql', 'table');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
