<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\ColumnParser as Subject;
use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\Node;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnAttributes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\Identifier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\StringLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\TypeParameters::class)]
final class ColumnParserTest extends TestCase
{
    public function testParseColumnDefinitionReadsTypeAttributesAndDefault(): void
    {
        $sql = "CREATE TABLE t (`amount` DECIMAL(8, 2) UNSIGNED NOT NULL DEFAULT 12.5 COMMENT 'money')";
        $tree = (new MySqlParser())->parse($sql);
        $column = (new Subject())->parseColumnDefinition($tree->find('column_def')[0], $sql, []);

        self::assertNotNull($column);
        self::assertSame('amount', $column->name);
        self::assertSame('DECIMAL', $column->type);
        self::assertSame(8, $column->precision);
        self::assertSame(2, $column->scale);
        self::assertNull($column->length);
        self::assertFalse($column->nullable);
        self::assertTrue($column->unsigned);
        self::assertSame(12.5, $column->default);
        self::assertFalse($column->autoIncrement);
        self::assertFalse($column->generated);
        self::assertNull($column->enumValues);
    }

    public function testParseColumnDefinitionReadsEnumValuesAndPrimaryKeyNullability(): void
    {
        $sql = "CREATE TABLE t (status ENUM('a', 'b') DEFAULT 'a', id INT AUTO_INCREMENT, other INT)";
        $tree = (new MySqlParser())->parse($sql);
        $definitions = $tree->find('column_def');
        $status = (new Subject())->parseColumnDefinition($definitions[0], $sql, ['id']);
        $id = (new Subject())->parseColumnDefinition($definitions[1], $sql, ['id']);
        $other = (new Subject())->parseColumnDefinition($definitions[2], $sql, ['id']);

        self::assertNotNull($status);
        self::assertSame(['a', 'b'], $status->enumValues);
        self::assertSame('a', $status->default);
        self::assertTrue($status->nullable);
        self::assertNotNull($id);
        self::assertTrue($id->autoIncrement);
        self::assertFalse($id->nullable);
        self::assertNotNull($other);
        self::assertTrue($other->nullable);
        self::assertFalse($other->unsigned);
    }

    public function testParseColumnDefinitionMarksGeneratedColumns(): void
    {
        $sql = 'CREATE TABLE t (a INT, b INT GENERATED ALWAYS AS (a + 1) STORED, c INT AS (a) VIRTUAL NOT NULL)';
        $tree = (new MySqlParser())->parse($sql);
        $definitions = $tree->find('column_def');
        $b = (new Subject())->parseColumnDefinition($definitions[1], $sql, []);
        $c = (new Subject())->parseColumnDefinition($definitions[2], $sql, []);

        self::assertNotNull($b);
        self::assertTrue($b->generated);
        self::assertTrue($b->nullable);
        self::assertNotNull($c);
        self::assertTrue($c->generated);
        self::assertFalse($c->nullable);
    }

    public function testParseColumnDefinitionTreatsSerialAsUnsignedAutoIncrement(): void
    {
        $sql = 'CREATE TABLE t (id SERIAL)';
        $tree = (new MySqlParser())->parse($sql);
        $column = (new Subject())->parseColumnDefinition($tree->find('column_def')[0], $sql, []);

        self::assertNotNull($column);
        self::assertSame('BIGINT', $column->type);
        self::assertTrue($column->unsigned);
        self::assertTrue($column->autoIncrement);
        self::assertFalse($column->nullable);
    }

    public function testParseColumnDefinitionReturnsNullWithoutANameOrType(): void
    {
        $sql = 'CREATE TABLE t (id INT)';
        $tree = (new MySqlParser())->parse($sql);
        $columnDef = $tree->find('column_def')[0];
        $ident = $tree->find('ident')[1];
        $fieldDef = $tree->find('field_def')[0];

        self::assertNull((new Subject())->parseColumnDefinition(new Node('column_def', 0, [$fieldDef]), $sql, []));
        self::assertNull((new Subject())->parseColumnDefinition(new Node('column_def', 0, [$ident]), $sql, []));
        self::assertNull((new Subject())->parseColumnDefinition(new Node('column_def', 0, [$ident, new Node('field_def', 0, [])]), $sql, []));
        self::assertNotNull((new Subject())->parseColumnDefinition($columnDef, $sql, []));
    }
}
