<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\PragmaSchema as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\PragmaColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
final class PragmaSchemaTest extends TestCase
{
    public function testFetchSchemaViaPragmaReadsLiveTable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(30) NOT NULL DEFAULT \'ready\')');
        $schema = (new Subject())->fetchSchemaViaPragma($pdo, 'users');
        self::assertSame(['id'], $schema->primaryKeys);
        self::assertSame(30, $schema->columns['name']->length);
        self::assertSame('ready', $schema->columns['name']->default);
    }

    public function testParseDefaultValueDecodesQuotedAndNumericLiterals(): void
    {
        $parser = new Subject();
        self::assertSame('ready', $parser->parseDefaultValue("'ready'"));
        self::assertSame(12.5, $parser->parseDefaultValue('12.5'));
        self::assertNull($parser->parseDefaultValue('NULL'));
        self::assertSame('CURRENT_DATE', $parser->parseDefaultValue('CURRENT_DATE'));
    }
}
