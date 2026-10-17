<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\ColumnConstraints as Subject;
use SqlFixture\Platform\Sqlite\Schema\CreateTableStatement;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(CreateTableStatement::class)]
final class ColumnConstraintsTest extends TestCase
{
    public function testReadDefaultsToANullableColumn(): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE t (id INTEGER)');
        $constraints = (new Subject())->read($tree->find('carglist')[0]);

        self::assertTrue($constraints->nullable);
        self::assertFalse($constraints->primaryKey);
        self::assertFalse($constraints->autoIncrement);
        self::assertFalse($constraints->generated);
        self::assertNull($constraints->default);
    }

    public function testReadRecognizesNotNullDefaultAndPrimaryKeyWithAutoincrement(): void
    {
        $sql = "CREATE TABLE t (id INTEGER CONSTRAINT nn NOT NULL DEFAULT 'x' PRIMARY KEY AUTOINCREMENT UNIQUE COLLATE NOCASE)";
        $tree = (new SqliteParser())->parse($sql);
        $constraints = (new Subject())->read($tree->find('carglist')[0]);

        self::assertFalse($constraints->nullable);
        self::assertTrue($constraints->primaryKey);
        self::assertTrue($constraints->autoIncrement);
        self::assertFalse($constraints->generated);
        self::assertNotNull($constraints->default);
        self::assertSame("DEFAULT 'x'", $constraints->default->text($sql));
    }

    public function testReadKeepsPrimaryKeyWithoutAutoincrement(): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE t (id INTEGER PRIMARY KEY, b INT NOT NULL NULL, c INT NOT NULL)');
        $columns = (new CreateTableStatement())->columns($tree->find('cmd')[0]);

        self::assertTrue((new Subject())->read($columns[0][1])->primaryKey);
        self::assertFalse((new Subject())->read($columns[0][1])->autoIncrement);
        self::assertTrue((new Subject())->read($columns[1][1])->nullable);
        self::assertFalse((new Subject())->read($columns[2][1])->nullable);
    }

    public function testReadMarksGeneratedColumns(): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE t (a INT, b INT AS (a + 1), c TEXT GENERATED ALWAYS AS (a) STORED)');
        $columns = (new CreateTableStatement())->columns($tree->find('cmd')[0]);

        self::assertFalse((new Subject())->read($columns[0][1])->generated);
        self::assertTrue((new Subject())->read($columns[1][1])->generated);
        self::assertTrue((new Subject())->read($columns[2][1])->generated);
    }
}
