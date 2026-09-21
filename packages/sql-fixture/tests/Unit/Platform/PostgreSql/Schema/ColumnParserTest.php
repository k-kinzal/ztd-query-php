<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\ColumnParser as Subject;
use SqlParser\Parser\Node;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\ColumnConstraints::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\Identifier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\StringLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration::class)]
final class ColumnParserTest extends TestCase
{
    public function testParseColumnDefinitionReadsTypeConstraintsAndDefault(): void
    {
        $sql = 'CREATE TABLE t ("amount" NUMERIC(8, 2) NOT NULL DEFAULT 12.5 CHECK (amount > 0))';
        $tree = (new PostgreSqlParser())->parse($sql);
        $column = (new Subject())->parseColumnDefinition($tree->find('columnDef')[0], $sql, []);

        self::assertNotNull($column);
        self::assertSame('amount', $column->name);
        self::assertSame('NUMERIC', $column->type);
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

    public function testParseColumnDefinitionMarksSerialIdentityGeneratedAndKeyColumns(): void
    {
        $sql = 'CREATE TABLE t (id SERIAL, seq INT GENERATED ALWAYS AS IDENTITY, g INT GENERATED ALWAYS AS (id + 1) STORED, k INT, pk INT PRIMARY KEY)';
        $tree = (new PostgreSqlParser())->parse($sql);
        $definitions = $tree->find('columnDef');
        $id = (new Subject())->parseColumnDefinition($definitions[0], $sql, []);
        $seq = (new Subject())->parseColumnDefinition($definitions[1], $sql, []);
        $g = (new Subject())->parseColumnDefinition($definitions[2], $sql, []);
        $k = (new Subject())->parseColumnDefinition($definitions[3], $sql, ['k']);
        $pk = (new Subject())->parseColumnDefinition($definitions[4], $sql, []);

        self::assertNotNull($id);
        self::assertSame(['INTEGER', true, false], [$id->type, $id->autoIncrement, $id->nullable]);
        self::assertNotNull($seq);
        self::assertSame([true, false, false], [$seq->autoIncrement, $seq->nullable, $seq->generated]);
        self::assertNotNull($g);
        self::assertSame([true, true, false], [$g->generated, $g->nullable, $g->autoIncrement]);
        self::assertNotNull($k);
        self::assertFalse($k->nullable);
        self::assertNotNull($pk);
        self::assertFalse($pk->nullable);
    }

    public function testParseColumnDefinitionReturnsNullWithoutANameOrType(): void
    {
        $sql = 'CREATE TABLE t (id INT)';
        $tree = (new PostgreSqlParser())->parse($sql);
        $typename = $tree->find('Typename')[0];
        $name = $tree->find('columnDef')[0]->tokens()[0];

        self::assertNull((new Subject())->parseColumnDefinition(new Node('columnDef', 0, []), $sql, []));
        self::assertNull((new Subject())->parseColumnDefinition(new Node('columnDef', 0, [$name]), $sql, []));
        self::assertNotNull((new Subject())->parseColumnDefinition(new Node('columnDef', 0, [$name, $typename]), $sql, []));
    }
}
