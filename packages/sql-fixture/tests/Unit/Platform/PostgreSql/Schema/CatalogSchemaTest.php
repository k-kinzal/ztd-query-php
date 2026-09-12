<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\CatalogSchema as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
final class CatalogSchemaTest extends TestCase
{
    public function testFetchSchemaFromInformationSchemaRetainsColumnMetadata(): void
    {
        $pdo = \Tests\Fixture\Database::postgres();
        $pdo->exec('CREATE TEMPORARY TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(30) NOT NULL DEFAULT \'ready\')');
        $statement = $pdo->query('SELECT pg_my_temp_schema()::regnamespace::text');
        self::assertNotFalse($statement);
        $namespace = $statement->fetchColumn();
        self::assertIsString($namespace);
        $schema = (new Subject())->fetchSchemaFromInformationSchema($pdo, $namespace . '.users');
        self::assertSame('users', $schema->tableName);
        self::assertFalse($schema->columns['id']->nullable);
        self::assertSame(30, $schema->columns['name']->length);
        self::assertSame('ready', $schema->columns['name']->default);
    }
}
