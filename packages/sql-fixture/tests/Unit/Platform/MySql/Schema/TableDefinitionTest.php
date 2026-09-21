<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\TableDefinition as Subject;
use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\Node;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnAttributes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\Identifier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\StringLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\TypeParameters::class)]
final class TableDefinitionTest extends TestCase
{
    public function testExtractTableNameDropsTheDatabaseQualifierAndQuotes(): void
    {
        $sql = 'CREATE TABLE `shop`.`order items` (id INT)';
        $statement = (new MySqlParser())->parse($sql)->find('create_table_stmt')[0];

        self::assertSame('order items', (new Subject())->extractTableName($statement, $sql));
    }

    public function testExtractTableNameRejectsAStatementWithoutAName(): void
    {
        $this->expectException(\SqlFixture\Schema\Exception\InvalidSqlException::class);
        $this->expectExceptionMessage('Table name not found');
        (new Subject())->extractTableName(new Node('create_table_stmt', 0, []), 'CREATE TABLE');
    }

    public function testExtractColumnsKeepsDeclarationOrderAndSkipsConstraints(): void
    {
        $sql = 'CREATE TABLE t (id INT, PRIMARY KEY (id), name VARCHAR(10) NOT NULL, KEY k (name))';
        $statement = (new MySqlParser())->parse($sql)->find('create_table_stmt')[0];
        $columns = (new Subject())->extractColumns($statement, $sql, 't');

        self::assertSame(['id', 'name'], array_keys($columns));
        self::assertFalse($columns['id']->nullable);
        self::assertSame(10, $columns['name']->length);
    }

    public function testExtractColumnsRejectsATableWithoutColumns(): void
    {
        $sql = 'CREATE TABLE t LIKE o';
        $statement = (new MySqlParser())->parse($sql)->find('create_table_stmt')[0];

        $this->expectException(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class);
        (new Subject())->extractColumns($statement, $sql, 't');
    }

    public function testExtractPrimaryKeysCombinesColumnAndTableLevelKeysWithoutDuplicates(): void
    {
        $sql = 'CREATE TABLE t (a INT PRIMARY KEY, b INT KEY, `c` INT, d INT, PRIMARY KEY (a, `c`(10), d DESC), UNIQUE KEY u (d), FOREIGN KEY (b) REFERENCES o (id))';
        $statement = (new MySqlParser())->parse($sql)->find('create_table_stmt')[0];

        self::assertSame(['a', 'b', 'c', 'd'], (new Subject())->extractPrimaryKeys($statement));
    }

    public function testExtractPrimaryKeysSkipsExpressionKeyParts(): void
    {
        $sql = 'CREATE TABLE t (a INT, b INT, PRIMARY KEY ((a + b), b))';
        $statement = (new MySqlParser())->parse($sql)->find('create_table_stmt')[0];

        self::assertSame(['b'], (new Subject())->extractPrimaryKeys($statement));
        self::assertSame([], (new Subject())->extractPrimaryKeys(new Node('create_table_stmt', 0, [])));
    }
}
