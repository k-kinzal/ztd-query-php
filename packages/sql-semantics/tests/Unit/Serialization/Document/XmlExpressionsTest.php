<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Xml;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\TableFunction\Xml\PassingMode;
use SqlSemantics\Serialization\Document\XmlExpressions;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(XmlExpressions::class)]
#[Medium]
final class XmlExpressionsTest extends TestCase
{
    public function testWriteSpellsPredicatesAndConversions(): void
    {
        $value = Expression::literal('<a/>', Dialect::PostgreSql);
        $boolean = new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull);
        $xml = new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull);
        self::assertSame("('<a/>' IS NOT DOCUMENT)", XmlExpressions::write(new Xml\DocumentPredicate($boolean, $value->source, $value, true))?->toString());
        self::assertSame("XMLEXISTS('<a/>' PASSING BY REF '<a/>')", XmlExpressions::write(new Xml\XmlExistence($boolean, $value->source, $value, $value, PassingMode::Reference))?->toString());
        self::assertSame("XMLPARSE(CONTENT '<a/>' PRESERVE WHITESPACE)", XmlExpressions::write(new Xml\XmlParse($xml, $value->source, Xml\XmlOption::Content, $value, true))?->toString());
        $text = TypeDescriptor::builtin(Dialect::PostgreSql, 'text');
        self::assertSame("XMLSERIALIZE(DOCUMENT '<a/>' AS text INDENT)", XmlExpressions::write(new Xml\XmlSerialization(new ExpressionFacts($text, Nullability::MaybeNull), $value->source, Xml\XmlOption::Document, $value, $text, true))?->toString());
        self::assertSame("XMLROOT('<a/>', VERSION NO VALUE, STANDALONE NO VALUE)", XmlExpressions::write(new Xml\XmlRoot($xml, $value->source, $value, null, Xml\XmlStandalone::NoValue))?->toString());
        self::assertNull(XmlExpressions::write($value));
    }

    public function testCompositeSpellsTheConstructors(): void
    {
        $value = Expression::literal('<a/>', Dialect::PostgreSql);
        $xml = new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull);
        self::assertSame("XMLELEMENT(NAME \"e\", XMLATTRIBUTES('<a/>' AS \"k\"), '<a/>')", XmlExpressions::composite(new Xml\XmlElement($xml, $value->source, 'e', [new Xml\XmlNamedArgument($value, 'k')], [$value]))?->toString());
        self::assertSame("XMLFOREST('<a/>' AS \"k\")", XmlExpressions::composite(new Xml\XmlForest($xml, $value->source, [new Xml\XmlNamedArgument($value, 'k')]))?->toString());
        self::assertSame("XMLPI(NAME \"p\", '<a/>')", XmlExpressions::composite(new Xml\XmlProcessingInstruction($xml, $value->source, 'p', $value))?->toString());
        self::assertSame("XMLCONCAT('<a/>', '<a/>')", XmlExpressions::composite(new Xml\XmlConcatenation($xml, $value->source, [$value, $value]))?->toString());
        self::assertNull(XmlExpressions::composite($value));
    }

    public function testCallParenthesizesTheOperands(): void
    {
        self::assertSame('XMLCONCAT(x)', XmlExpressions::call('XMLCONCAT', [Build::keyword('x')])->toString());
    }

    public function testNameQuotesTheLabel(): void
    {
        self::assertSame('NAME "My ""tag"""', XmlExpressions::name('My "tag"')->toString());
    }

    public function testNamedWritesAnAliasOnlyWhenPresent(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame('1 AS "a", 1 AS "b"', XmlExpressions::named([new Xml\XmlNamedArgument($value, 'a'), new Xml\XmlNamedArgument($value, 'b')])->toString());
    }

    public function testModeOmitsTheDefault(): void
    {
        self::assertSame([], XmlExpressions::mode(PassingMode::Default));
        self::assertSame('BY VALUE', XmlExpressions::mode(PassingMode::Value)[0]->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith(["SELECT XMLEXISTS('//a' PASSING BY REF x BY VALUE) FROM t", 'SELECT XMLEXISTS(\'//a\' PASSING BY REF "x" BY VALUE) FROM "public"."t"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(["SELECT XMLPARSE(DOCUMENT '<a/>' PRESERVE WHITESPACE)", "SELECT XMLPARSE(DOCUMENT '<a/>' PRESERVE WHITESPACE)"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['SELECT XMLSERIALIZE(CONTENT x AS text INDENT) FROM t', 'SELECT XMLSERIALIZE(CONTENT "x" AS text INDENT) FROM "public"."t"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(["SELECT XMLROOT(x, VERSION '1.0', STANDALONE YES) FROM t", 'SELECT XMLROOT("x", VERSION \'1.0\', STANDALONE YES) FROM "public"."t"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(["SELECT XMLELEMENT(NAME a, XMLATTRIBUTES(1 AS b, 2 AS c), 'x', 'y')", 'SELECT XMLELEMENT(NAME "a", XMLATTRIBUTES(1 AS "b", 2 AS "c"), \'x\', \'y\')'])]
    #[\PHPUnit\Framework\Attributes\TestWith(["SELECT XMLPI(NAME p, 'c')", 'SELECT XMLPI(NAME "p", \'c\')'])]
    public function testWriteKeepsEveryOperandOfEachXmlFunction(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x xml)')))->bind($sql)->toString());
    }
}
