<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\TableDefinition as Subject;
use SqlFixture\Syntax\SqlText;
use SqlParser\Parser\Node;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnConstraints::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\Identifier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SqlText::class)]
final class TableDefinitionTest extends TestCase
{
    public function testExtractTableNameDropsTheDatabaseQualifierAndQuotes(): void
    {
        $qualified = 'CREATE TABLE main."order items" (id INT)';
        $plain = 'CREATE TEMP TABLE IF NOT EXISTS [orders] (id INT)';

        self::assertSame('order items', (new Subject())->extractTableName((new SqliteParser())->parse($qualified)->find('cmd')[0], $qualified));
        self::assertSame('orders', (new Subject())->extractTableName((new SqliteParser())->parse($plain)->find('cmd')[0], $plain));
    }

    public function testExtractTableNameRejectsACommandWithoutAName(): void
    {
        $this->expectException(\SqlFixture\Schema\Exception\InvalidSqlException::class);
        $this->expectExceptionMessage('Could not extract table name');
        (new Subject())->extractTableName(new Node('cmd', 0, []), 'CREATE TABLE');
    }

    public function testExtractColumnsKeepsDeclarationOrderAndMarksKeyColumns(): void
    {
        $sql = 'CREATE TABLE t (id INTEGER, name VARCHAR(10) NOT NULL, PRIMARY KEY (id))';
        $columns = (new Subject())->extractColumns((new SqliteParser())->parse($sql)->find('cmd')[0], 't');

        self::assertSame(['id', 'name'], array_keys($columns));
        self::assertFalse($columns['id']->nullable);
        self::assertSame(10, $columns['name']->length);
    }

    public function testExtractColumnsRejectsATableWithoutColumns(): void
    {
        $sql = 'CREATE TABLE t AS SELECT 1';

        $this->expectException(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class);
        (new Subject())->extractColumns((new SqliteParser())->parse($sql)->find('cmd')[0], 't');
    }

    public function testExtractPrimaryKeysCombinesColumnAndTableLevelKeysWithoutDuplicates(): void
    {
        $sql = 'CREATE TABLE t (a INT PRIMARY KEY, "b" INT, c INT, CONSTRAINT pk PRIMARY KEY (a, "b" DESC, c COLLATE nocase), UNIQUE (c), FOREIGN KEY (c) REFERENCES o (id))';

        self::assertSame(['a', 'b', 'c'], (new Subject())->extractPrimaryKeys((new SqliteParser())->parse($sql)->find('cmd')[0]));
        self::assertSame([], (new Subject())->extractPrimaryKeys(new Node('cmd', 0, [])));
    }

    public function testKeyColumnsSkipsExpressionsAndKeepsSimpleNames(): void
    {
        $sql = 'CREATE TABLE t (a INT, b INT, PRIMARY KEY (a + b, [b], a))';
        $sortlists = (new SqliteParser())->parse($sql)->find('sortlist');

        self::assertSame(['b', 'a'], (new Subject())->keyColumns($sortlists[0]));
        self::assertSame(['b'], (new Subject())->keyColumns($sortlists[1]));
        self::assertSame([], (new Subject())->keyColumns($sortlists[2]));
    }

    public function testKeyColumnReadsAColumnWrittenWithACollation(): void
    {
        $sql = 'CREATE TABLE t (a INT, b INT, PRIMARY KEY (a COLLATE nocase, b + 1))';
        $sortlist = (new SqliteParser())->parse($sql)->find('sortlist')[0];
        $collated = $sortlist->find('expr')[0];
        $summed = $sortlist->find('expr')[2];

        self::assertSame('a COLLATE nocase', (new SqlText())->ofNode($collated));
        self::assertSame('a', (new Subject())->keyColumn($collated));
        self::assertSame('b + 1', (new SqlText())->ofNode($summed));
        self::assertNull((new Subject())->keyColumn($summed));
    }
}
