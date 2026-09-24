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
use SqlSemantics\Model\Scalar\Document\Xml\XmlParse;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(XmlParse::class)]
#[Medium]
final class XmlParseTest extends TestCase
{
    public function testInputsListTheParsedValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER NOT NULL, x XML)'));
        $query = $binder->bind("SELECT XMLPARSE(CONTENT 'a' STRIP WHITESPACE)");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(XmlParse::class, $value);
        self::assertSame(\SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Content, $value->option);
        self::assertFalse($value->preserveWhitespace);
        self::assertSame('xml', $value->type->name);
        self::assertSame(Nullability::NotNull, $value->nullability);
        self::assertSame([$value->value], $value->inputs());
        self::assertSame(ExpressionKind::XmlConversion, $value->kind);
        self::assertSame("SELECT XMLPARSE(CONTENT 'a')", $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testSpellingNamesTheOperation(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new XmlParse(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull), $literal->source, \SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Document, $literal, true);
        self::assertSame('XMLPARSE', $value->spelling());
    }

    public function testWithFactsKeepsTheOperands(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new XmlParse(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull), $literal->source, \SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Document, $literal, true);
        $facts = new ExpressionFacts($value->type, Nullability::MaybeNull, ['j1']);
        $copy = $value->withFacts($facts);
        self::assertNotSame($value, $copy);
        self::assertSame($facts, $copy->facts);
        self::assertSame($value->inputs(), $copy->inputs());
    }

    public function testInputsRequireAnXmlResult(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new XmlParse(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull), $literal->source, \SqlSemantics\Model\Scalar\Document\Xml\XmlOption::Document, $literal);
    }
}
