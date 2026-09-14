<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\MySql\Schema\MySqlSchemaReflector;

#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\ReferenceReader::class)]
#[CoversClass(MySqlSchemaReflector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\MySqlSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Schema\View\MySqlViewDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
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
        $views = self::createStub(StatementInterface::class);
        $views->method('fetchAll')->willReturn([
            ['name' => null], ['name' => ''], ['name' => 'query_failed'],
            ['name' => 'missing_row'], ['name' => 'non_string'], ['name' => 'invalid'],
            ['name' => 'active`users'], ['name' => 'all_users'],
        ]);
        $missing = self::createStub(StatementInterface::class);
        $missing->method('fetchAll')->willReturn([]);
        $nonString = self::createStub(StatementInterface::class);
        $nonString->method('fetchAll')->willReturn([['Create View' => null]]);
        $invalid = self::createStub(StatementInterface::class);
        $invalid->method('fetchAll')->willReturn([['Create View' => 'CREATE VIEW invalid']]);
        $active = self::createStub(StatementInterface::class);
        $active->method('fetchAll')->willReturn([['Create View' => 'CREATE VIEW `active``users` AS SELECT * FROM app.users']]);
        $all = self::createStub(StatementInterface::class);
        $all->method('fetchAll')->willReturn([['Create View' => 'CREATE VIEW all_users AS SELECT * FROM app.users']]);
        $responses = [
            "SHOW FULL TABLES WHERE Table_type = 'VIEW'" => $views,
            'SHOW CREATE VIEW `query_failed`' => false,
            'SHOW CREATE VIEW `missing_row`' => $missing,
            'SHOW CREATE VIEW `non_string`' => $nonString,
            'SHOW CREATE VIEW `invalid`' => $invalid,
            'SHOW CREATE VIEW `active``users`' => $active,
            'SHOW CREATE VIEW `all_users`' => $all,
        ];
        $queries = [];
        $connection = self::createMock(ConnectionInterface::class);
        $connection->expects(self::exactly(7))->method('query')->willReturnCallback(
            static function (string $sql) use ($responses, &$queries): StatementInterface|false {
                $queries[] = $sql;
                return $responses[$sql];
            },
        );

        $definitions = (new MySqlSchemaReflector($connection))->reflectViews();

        self::assertSame(['active`users', 'all_users'], array_keys($definitions));
        self::assertSame(['users'], $definitions['active`users']->dependencies);
        self::assertSame([
            "SHOW FULL TABLES WHERE Table_type = 'VIEW'",
            'SHOW CREATE VIEW `query_failed`', 'SHOW CREATE VIEW `missing_row`',
            'SHOW CREATE VIEW `non_string`', 'SHOW CREATE VIEW `invalid`',
            'SHOW CREATE VIEW `active``users`', 'SHOW CREATE VIEW `all_users`',
        ], $queries);
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
