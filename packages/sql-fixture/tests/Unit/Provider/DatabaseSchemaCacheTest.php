<?php

declare(strict_types=1);

namespace Tests\Unit\Provider;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Provider\DatabaseSchemaCache as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\SqliteSchemaFetcher::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\SqliteSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaFetcherInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\CreateTableQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefinitionList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\PragmaColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\PragmaSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\TableSyntax::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\DefinitionSegments::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
final class DatabaseSchemaCacheTest extends TestCase
{
    public function testGetSchemaReusesTheParsedTableUntilInvalidated(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INT)');
        $cache = new Subject($pdo, new \SqlFixture\Platform\Sqlite\SqliteSchemaFetcher());
        $first = $cache->getSchema('users');
        $pdo->exec('ALTER TABLE users ADD COLUMN name TEXT');
        self::assertSame($first, $cache->getSchema('users'));
        self::assertSame(['id'], array_keys($cache->getSchema('users')->columns));
    }

    public function testClearReflectsSubsequentDdlChanges(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INT)');
        $cache = new Subject($pdo, new \SqlFixture\Platform\Sqlite\SqliteSchemaFetcher());
        $cache->getSchema('users');
        $pdo->exec('ALTER TABLE users ADD COLUMN name TEXT');
        $cache->clear();
        self::assertSame(['id', 'name'], array_keys($cache->getSchema('users')->columns));
    }
}
