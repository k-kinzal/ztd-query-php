<?php

declare(strict_types=1);

namespace Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Provider\DdlDirectory as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\SqliteSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Provider\DdlFile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefinitionList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\TableSyntax::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\DefinitionSegments::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
final class DdlDirectoryTest extends TestCase
{
    public function testLoadSchemasUsesOnlySqlFiles(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sql-fixture-ddl-');
        self::assertNotFalse($file);
        unlink($file);
        mkdir($file);
        try {
            file_put_contents($file . '/users.sql', 'CREATE TABLE users (id INT, PRIMARY KEY (id))');
            file_put_contents($file . '/ignored.txt', 'CREATE TABLE ignored (id INT)');
            $schemas = (new Subject())->loadSchemas($file, new \SqlFixture\Platform\Sqlite\SqliteSchemaParser());
            self::assertSame(['users'], array_keys($schemas));
            self::assertSame(['id'], $schemas['users']->primaryKeys);
        } finally {
            unlink($file . '/users.sql');
            unlink($file . '/ignored.txt');
            rmdir($file);
        }
    }
}
