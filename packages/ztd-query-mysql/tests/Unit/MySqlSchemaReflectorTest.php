<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeConnection;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\MySql\MySqlSchemaReflector;

#[CoversClass(MySqlSchemaReflector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlViewDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class MySqlSchemaReflectorTest extends TestCase
{
    public function testReflectViewsReturnsEmptyWhenQueryFails(): void
    {
        $connection = self::createStub(ConnectionInterface::class);
        $connection->method('query')->willReturn(false);

        self::assertSame([], (new MySqlSchemaReflector($connection))->reflectViews());
    }

    public function testReflectViewsSkipsMalformedDefinitions(): void
    {
        $connection = new FakeConnection([
            "SHOW FULL TABLES WHERE Table_type = 'VIEW'" => [
                ['name' => null],
                ['name' => ''],
                ['name' => 'query_failed'],
                ['name' => 'missing_row'],
                ['name' => 'non_string'],
                ['name' => 'invalid'],
                ['name' => 'active`users'],
                ['name' => 'all_users'],
            ],
            'SHOW CREATE VIEW `missing_row`' => [],
            'SHOW CREATE VIEW `non_string`' => [['Create View' => null]],
            'SHOW CREATE VIEW `invalid`' => [['Create View' => 'CREATE VIEW invalid']],
            'SHOW CREATE VIEW `active``users`' => [
                ['Create View' => 'CREATE VIEW `active``users` AS SELECT * FROM app.users'],
            ],
            'SHOW CREATE VIEW `all_users`' => [
                ['Create View' => 'CREATE VIEW all_users AS SELECT * FROM app.users'],
            ],
        ]);
        $connection->failOnQuery('SHOW CREATE VIEW `query_failed`');

        $definitions = (new MySqlSchemaReflector($connection))->reflectViews();

        self::assertCount(7, $connection->queries);
        self::assertSame(['active`users', 'all_users'], array_keys($definitions));
        self::assertSame(['users'], $definitions['active`users']->dependencies);
    }

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
