<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\ColumnParser as Subject;
use SqlFixture\Platform\Sqlite\Schema\CreateTableStatement;
use SqlParser\Parser\Node;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnConstraints::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\Identifier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
final class ColumnParserTest extends TestCase
{
    public function testParseColumnDefinitionReadsTypeConstraintsAndDefault(): void
    {
        $sql = 'CREATE TABLE t ("amount" DECIMAL(8, 2) NOT NULL DEFAULT 12.5 COLLATE NOCASE)';
        $tree = (new SqliteParser())->parse($sql);
        $column = (new Subject())->parseColumnDefinition($tree->find('columnname')[0], $tree->find('carglist')[0], $sql, []);

        self::assertNotNull($column);
        self::assertSame('amount', $column->name);
        self::assertSame('DECIMAL', $column->type);
        self::assertSame(8, $column->precision);
        self::assertSame(2, $column->scale);
        self::assertNull($column->length);
        self::assertFalse($column->nullable);
        self::assertFalse($column->unsigned);
        self::assertSame(12.5, $column->default);
        self::assertFalse($column->autoIncrement);
        self::assertFalse($column->generated);
        self::assertNull($column->enumValues);
    }

    public function testParseColumnDefinitionMarksPrimaryKeysAutoincrementAndGeneratedColumns(): void
    {
        $sql = 'CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, a INT, g INT AS (a + 1), b INT)';
        $columns = (new CreateTableStatement())->columns((new SqliteParser())->parse($sql)->find('cmd')[0]);
        $id = (new Subject())->parseColumnDefinition($columns[0][0], $columns[0][1], $sql, []);
        $a = (new Subject())->parseColumnDefinition($columns[1][0], $columns[1][1], $sql, ['a']);
        $g = (new Subject())->parseColumnDefinition($columns[2][0], $columns[2][1], $sql, []);
        $b = (new Subject())->parseColumnDefinition($columns[3][0], $columns[3][1], $sql, []);

        self::assertNotNull($id);
        self::assertTrue($id->autoIncrement);
        self::assertFalse($id->nullable);
        self::assertNotNull($a);
        self::assertFalse($a->nullable);
        self::assertNotNull($g);
        self::assertTrue($g->generated);
        self::assertNotNull($b);
        self::assertTrue($b->nullable);
        self::assertSame('BLOB', (new Subject())->parseColumnDefinition((new SqliteParser())->parse('CREATE TABLE t (v)')->find('columnname')[0], $columns[3][1], $sql, [])?->type);
    }

    public function testParseColumnDefinitionReturnsNullWithoutANameOrType(): void
    {
        $sql = 'CREATE TABLE t (id INT)';
        $tree = (new SqliteParser())->parse($sql);
        $carglist = $tree->find('carglist')[0];
        $typetoken = $tree->find('typetoken')[0];
        $nm = $tree->find('columnname')[0]->find('nm')[0];

        self::assertNull((new Subject())->parseColumnDefinition(new Node('columnname', 0, [$typetoken]), $carglist, $sql, []));
        self::assertNull((new Subject())->parseColumnDefinition(new Node('columnname', 0, [$nm]), $carglist, $sql, []));
        self::assertNull((new Subject())->parseColumnDefinition(new Node('columnname', 0, [new Node('nm', 0, []), $typetoken]), $carglist, $sql, []));
    }
}
