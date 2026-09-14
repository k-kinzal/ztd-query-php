<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlSchemaFetcher as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[CoversClass(\SqlFixture\Platform\MySql\Schema\CreateTableQuery::class)]
#[CoversClass(\SqlFixture\Platform\MySql\Schema\IdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\MySqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaFetcherInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\DefinitionIntegrity::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\TypeParameters::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinitionInput::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
final class MySqlSchemaFetcherTest extends TestCase
{
    public function testFetchSchemaParsesLiveDatabaseDdl(): void
    {
        $pdo = new PDO(
            (string) getenv('SQL_FIXTURE_MYSQL_DSN'),
            (string) getenv('SQL_FIXTURE_MYSQL_USER'),
            (string) getenv('SQL_FIXTURE_MYSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
        $pdo->exec('CREATE TEMPORARY TABLE users (id INT PRIMARY KEY, name VARCHAR(30) NOT NULL)');
        $schema = (new Subject())->fetchSchema($pdo, 'users');
        self::assertSame('users', $schema->tableName);
        self::assertSame(['id'], $schema->primaryKeys);
        self::assertSame(30, $schema->columns['name']->length);
    }
}
