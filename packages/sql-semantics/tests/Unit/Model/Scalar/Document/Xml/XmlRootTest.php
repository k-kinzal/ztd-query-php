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
use SqlSemantics\Model\Scalar\Document\Xml\XmlRoot;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(XmlRoot::class)]
#[Medium]
final class XmlRootTest extends TestCase
{
    public function testInputsListTheValueBeforeTheVersion(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER NOT NULL, x XML)'));
        $query = $binder->bind("SELECT XMLROOT(x, VERSION '1.0', STANDALONE NO) FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(XmlRoot::class, $value);
        self::assertSame(\SqlSemantics\Model\Scalar\Document\Xml\XmlStandalone::No, $value->standalone);
        self::assertNotNull($value->version);
        self::assertSame([$value->value, $value->version], $value->inputs());
        self::assertSame(ExpressionKind::XmlConversion, $value->kind);
        self::assertSame('SELECT XMLROOT("x", VERSION \'1.0\', STANDALONE NO) FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testSpellingNamesTheOperation(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new XmlRoot(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull), $literal->source, $literal, null);
        self::assertSame('XMLROOT', $value->spelling());
    }

    public function testWithFactsKeepsTheOperands(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new XmlRoot(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull), $literal->source, $literal, null);
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
        new XmlRoot(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull), $literal->source, $literal, null);
    }
}
