<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\TableAlreadyExistsException;

final class TableAlreadyExistsExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessage(): void
    {
        $exception = new TableAlreadyExistsException(
            'CREATE TABLE users (id INT)',
            'users'
        );

        $this->assertSame("Table 'users' already exists.", $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'CREATE TABLE users (id INT)';
        $exception = new TableAlreadyExistsException($sql, 'users');

        $this->assertSame($sql, $exception->getSql());
    }

    public function testGetTableNameReturnsTableName(): void
    {
        $exception = new TableAlreadyExistsException('sql', 'users');

        $this->assertSame('users', $exception->getTableName());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new TableAlreadyExistsException('sql', 'table');

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }
}
