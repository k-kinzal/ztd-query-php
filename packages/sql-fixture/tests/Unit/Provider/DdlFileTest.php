<?php

declare(strict_types=1);

namespace Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Provider\DdlFile as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\SqliteSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnParser::class)]
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
final class DdlFileTest extends TestCase
{
    public function testLoadSchemaFileReadsDdlAndIgnoresNonSchemaSql(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sql-fixture-ddl-');
        self::assertNotFalse($file);
        try {
            file_put_contents($file, 'CREATE TABLE users (id INT PRIMARY KEY)');
            $schema = (new Subject())->loadSchemaFile($file, new \SqlFixture\Platform\Sqlite\SqliteSchemaParser());
            self::assertNotNull($schema);
            self::assertSame('users', $schema->tableName);
            file_put_contents($file, 'SELECT 1');
            self::assertNull((new Subject())->loadSchemaFile($file, new \SqlFixture\Platform\Sqlite\SqliteSchemaParser()));
        } finally {
            unlink($file);
        }
    }
}
