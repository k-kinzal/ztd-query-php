<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
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
        $viewList = self::createStub(StatementInterface::class);
        $viewList->method('fetchAll')->willReturn([
            ['name' => null],
            ['name' => ''],
            ['name' => 'query_failed'],
            ['name' => 'missing_row'],
            ['name' => 'non_string'],
            ['name' => 'invalid'],
            ['name' => 'active`users'],
            ['name' => 'all_users'],
        ]);
        $missingRow = self::createStub(StatementInterface::class);
        $missingRow->method('fetchAll')->willReturn([]);
        $nonString = self::createStub(StatementInterface::class);
        $nonString->method('fetchAll')->willReturn([['Create View' => null]]);
        $invalid = self::createStub(StatementInterface::class);
        $invalid->method('fetchAll')->willReturn([['Create View' => 'CREATE VIEW invalid']]);
        $valid = self::createStub(StatementInterface::class);
        $valid->method('fetchAll')->willReturn([
            ['Create View' => 'CREATE VIEW `active``users` AS SELECT * FROM app.users'],
        ]);
        $secondValid = self::createStub(StatementInterface::class);
        $secondValid->method('fetchAll')->willReturn([
            ['Create View' => 'CREATE VIEW all_users AS SELECT * FROM app.users'],
        ]);
        $connection = self::createMock(ConnectionInterface::class);
        $connection->expects(self::exactly(7))->method('query')->willReturnCallback(
            static fn (string $sql): StatementInterface|false => match ($sql) {
                "SHOW FULL TABLES WHERE Table_type = 'VIEW'" => $viewList,
                'SHOW CREATE VIEW `query_failed`' => false,
                'SHOW CREATE VIEW `missing_row`' => $missingRow,
                'SHOW CREATE VIEW `non_string`' => $nonString,
                'SHOW CREATE VIEW `invalid`' => $invalid,
                'SHOW CREATE VIEW `active``users`' => $valid,
                'SHOW CREATE VIEW `all_users`' => $secondValid,
                default => false,
            },
        );

        $definitions = (new MySqlSchemaReflector($connection))->reflectViews();

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
