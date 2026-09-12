<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogColumn::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogDdl::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogQuery::class)]
#[CoversClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaFetcherInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\ColumnParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefinitionList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\TableSyntax::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\DefinitionSegments::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
final class PostgreSqlSchemaFetcherTest extends TestCase
{
    public function testFetchSchemaCombinesLiveCatalogMetadata(): void
    {
        $pdo = \Tests\Fixture\Database::postgres();
        $pdo->exec('CREATE TEMPORARY TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(30) NOT NULL DEFAULT \'ready\')');
        $statement = $pdo->query('SELECT pg_my_temp_schema()::regnamespace::text');
        self::assertNotFalse($statement);
        $namespace = $statement->fetchColumn();
        self::assertIsString($namespace);
        $schema = (new Subject())->fetchSchema($pdo, $namespace . '.users');
        self::assertSame('users', $schema->tableName);
        self::assertSame(['id'], $schema->primaryKeys);
        self::assertSame('INTEGER', $schema->columns['id']->type);
        self::assertSame(30, $schema->columns['name']->length);
    }
}
