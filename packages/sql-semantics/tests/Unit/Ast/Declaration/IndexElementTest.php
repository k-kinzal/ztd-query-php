<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\IndexElement;
use SqlSemantics\Ast\Definition\IndexReader;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\SchemaReader;
use SqlSemantics\Dialect;

#[CoversClass(IndexElement::class)]
#[Medium]
final class IndexElementTest extends TestCase
{
    public function testReaderSeparatesColumnKeysFromExpressionKeys(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('CREATE INDEX ix ON t (id DESC NULLS LAST, (id + 1) COLLATE "C")');
        $index = (new IndexReader(new Identifiers(Dialect::PostgreSql), 'public'))->read($tree);
        self::assertNotNull($index);
        $column = $index->elements[0];
        self::assertSame('id', $column->column);
        self::assertNull($column->expression);
        self::assertSame('DESC', $column->direction);
        self::assertSame('LAST', $column->nulls);
        self::assertSame([], $column->collation);
        self::assertNull($column->prefixLength);
        self::assertSame('id DESC NULLS LAST', trim($column->source->toString()));
        $expression = $index->elements[1];
        self::assertNull($expression->column);
        self::assertSame('id + 1', trim($expression->expression?->toString() ?? ''));
        self::assertNull($expression->direction);
        self::assertSame(['C'], $expression->collation);
    }

    public function testReaderRetainsMysqlPrefixLengths(): void
    {
        $tree = (new DialectParser(Dialect::MySql))->parse('CREATE TABLE t(n VARCHAR(10), KEY ix (n(5) DESC))');
        $table = (new SchemaReader(new Identifiers(Dialect::MySql), ''))->table($tree);
        $element = $table->indexes[0]->elements[0];
        self::assertSame('n', $element->column);
        self::assertSame(5, $element->prefixLength);
        self::assertSame('DESC', $element->direction);
        self::assertSame([], $element->operatorClass);
    }

    public function testDefaultsLeaveTheOperatorClassParametersEmpty(): void
    {
        $source = new Node('index_elem', 0, []);
        $element = new IndexElement('id', null, null, null, [], [], null, $source);
        self::assertSame('id', $element->column);
        self::assertNull($element->nulls);
        self::assertSame([], $element->operatorClass);
        self::assertSame([], $element->options);
        self::assertSame($source, $element->source);
    }
}
