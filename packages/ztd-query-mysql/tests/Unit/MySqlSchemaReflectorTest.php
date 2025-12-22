<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\MySql\MySqlSchemaReflector;

#[CoversClass(MySqlSchemaReflector::class)]
final class MySqlSchemaReflectorTest extends TestCase
{
    public function testGetCreateStatementReturnsNullWhenQueryFails(): void
    {
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn(false);

        $reflector = new MySqlSchemaReflector($connection);
        self::assertNull($reflector->getCreateStatement('users'));
    }

    public function testGetCreateStatementReturnsNullWhenNoRows(): void
    {
        $statement = self::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn([]);

        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn($statement);

        $reflector = new MySqlSchemaReflector($connection);
        self::assertNull($reflector->getCreateStatement('users'));
    }

    public function testGetCreateStatementReturnsSql(): void
    {
        $createSql = 'CREATE TABLE users (id INT PRIMARY KEY)';
        $statement = self::createStub(StatementInterface::class);
        $statement->method('fetchAll')->willReturn([['Create Table' => $createSql]]);

        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn($statement);

        $reflector = new MySqlSchemaReflector($connection);
        self::assertSame($createSql, $reflector->getCreateStatement('users'));
    }

    public function testReflectAllReturnsEmptyWhenQueryFails(): void
    {
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn(false);

        $reflector = new MySqlSchemaReflector($connection);
        self::assertSame([], $reflector->reflectAll());
    }
}
