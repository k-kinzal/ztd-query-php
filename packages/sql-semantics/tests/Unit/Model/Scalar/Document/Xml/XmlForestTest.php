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
use SqlSemantics\Model\Scalar\Document\Xml\XmlForest;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(XmlForest::class)]
#[Medium]
final class XmlForestTest extends TestCase
{
    public function testInputsListTheElementValues(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER NOT NULL, x XML)'));
        $query = $binder->bind('SELECT XMLFOREST(n, x AS doc) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(XmlForest::class, $value);
        self::assertSame(['n', 'doc'], array_map(static fn ($element): string => $element->label(), $value->elements));
        self::assertSame(Nullability::NotNull, $value->nullability);
        self::assertSame([$value->elements[0]->value, $value->elements[1]->value], $value->inputs());
        self::assertSame(ExpressionKind::XmlConstructor, $value->kind);
        self::assertSame('SELECT XMLFOREST("n", "x" AS "doc") FROM "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testSpellingNamesTheOperation(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new XmlForest(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull), $literal->source, [new \SqlSemantics\Model\Scalar\Document\Xml\XmlNamedArgument($literal, 'a')]);
        self::assertSame('XMLFOREST', $value->spelling());
    }

    public function testWithFactsKeepsTheOperands(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new XmlForest(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull), $literal->source, [new \SqlSemantics\Model\Scalar\Document\Xml\XmlNamedArgument($literal, 'a')]);
        $facts = new ExpressionFacts($value->type, Nullability::MaybeNull, ['j1']);
        $copy = $value->withFacts($facts);
        self::assertNotSame($value, $copy);
        self::assertSame($facts, $copy->facts);
        self::assertSame($value->inputs(), $copy->inputs());
    }

    public function testInputsRequireAnElement(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new XmlForest(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull), $literal->source, []);
    }
}
