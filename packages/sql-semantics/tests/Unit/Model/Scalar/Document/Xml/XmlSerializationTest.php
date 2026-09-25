<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Document\Xml\XmlSerialization;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(XmlSerialization::class)]
#[Medium]
final class XmlSerializationTest extends TestCase
{
    public function testInputsListTheSerializedValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER NOT NULL, x XML)'));
        $query = $binder->bind('SELECT XMLSERIALIZE(DOCUMENT x AS character varying(5) NO INDENT) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(XmlSerialization::class, $value);
        self::assertSame(\SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Document, $value->option);
        self::assertFalse($value->indent);
        self::assertSame($value->target, $value->type);
        self::assertSame([$value->value], $value->inputs());
        self::assertSame(ExpressionKind::XmlConversion, $value->kind);
        self::assertSame('SELECT XMLSERIALIZE(DOCUMENT "x" AS varchar(5)) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testSpellingNamesTheOperation(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new XmlSerialization(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), Nullability::MaybeNull), $literal->source, \SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Content, $literal, TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), true);
        self::assertSame('XMLSERIALIZE', $value->spelling());
    }

    public function testWithFactsKeepsTheOperands(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new XmlSerialization(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), Nullability::MaybeNull), $literal->source, \SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Content, $literal, TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), true);
        $facts = new ExpressionFacts($value->type, Nullability::MaybeNull, ['j1']);
        $copy = $value->withFacts($facts);
        self::assertNotSame($value, $copy);
        self::assertSame($facts, $copy->facts);
        self::assertSame($value->inputs(), $copy->inputs());
    }

    public function testInputsRequireTheTargetTypeAsResult(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new XmlSerialization(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull), $literal->source, \SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Content, $literal, TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
    }
}
