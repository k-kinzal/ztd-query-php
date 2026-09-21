<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\SchemaFetcherInterface as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\SqliteSchemaFetcher::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\SqliteSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\CreateTableQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnConstraints::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\Identifier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
final class SchemaFetcherInterfaceTest extends TestCase
{
    public function testFetchSchemaReflectsDeclaredColumns(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INT, name TEXT, PRIMARY KEY (id))');
        $schema = (new \SqlFixture\Platform\Sqlite\SqliteSchemaFetcher())->fetchSchema($pdo, 'users');
        self::assertSame(['id'], $schema->primaryKeys);
        self::assertSame('TEXT', $schema->columns['name']->type);
    }
}
