<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\TableDefinition as Subject;
use SqlParser\Parser\Node;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\ColumnConstraints::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\ColumnParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\Identifier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\StringLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration::class)]
final class TableDefinitionTest extends TestCase
{
    public function testExtractTableNameDropsTheSchemaQualifierAndQuotes(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS shop."order items" (id INT)';

        self::assertSame('order items', (new Subject())->extractTableName((new PostgreSqlParser())->parse($sql)->find('CreateStmt')[0], $sql));
    }

    public function testExtractTableNameRejectsAStatementWithoutAName(): void
    {
        $this->expectException(\SqlFixture\Schema\Exception\InvalidSqlException::class);
        $this->expectExceptionMessage('Could not extract table name');
        (new Subject())->extractTableName(new Node('CreateStmt', 0, []), 'CREATE TABLE');
    }

    public function testExtractColumnsKeepsDeclarationOrderAndSkipsConstraints(): void
    {
        $sql = 'CREATE TABLE t (id INT, PRIMARY KEY (id), name VARCHAR(10) NOT NULL, LIKE o, EXCLUDE USING gist (id WITH =))';
        $columns = (new Subject())->extractColumns((new PostgreSqlParser())->parse($sql)->find('CreateStmt')[0], 't');

        self::assertSame(['id', 'name'], array_keys($columns));
        self::assertFalse($columns['id']->nullable);
        self::assertSame(10, $columns['name']->length);
    }

    public function testExtractColumnsRejectsATableWithoutColumns(): void
    {
        $sql = 'CREATE TABLE t ()';

        $this->expectException(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class);
        (new Subject())->extractColumns((new PostgreSqlParser())->parse($sql)->find('CreateStmt')[0], 't');
    }

    public function testExtractPrimaryKeysCombinesColumnAndTableLevelKeysWithoutDuplicates(): void
    {
        $sql = 'CREATE TABLE t (a INT PRIMARY KEY, "b" INT, c INT, CONSTRAINT pk PRIMARY KEY (a, "b"), UNIQUE (c), FOREIGN KEY (c) REFERENCES o (id))';

        self::assertSame(['a', 'b'], (new Subject())->extractPrimaryKeys((new PostgreSqlParser())->parse($sql)->find('CreateStmt')[0]));
        self::assertSame([], (new Subject())->extractPrimaryKeys(new Node('CreateStmt', 0, [])));
    }
}
