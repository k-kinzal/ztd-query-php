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
use SqlSemantics\Model\Scalar\Document\Xml\XmlExistence;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(XmlExistence::class)]
#[Medium]
final class XmlExistenceTest extends TestCase
{
    public function testInputsListThePathBeforeTheDocument(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER NOT NULL, x XML)'));
        $query = $binder->bind("SELECT XMLEXISTS('//a' PASSING x BY VALUE) FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(XmlExistence::class, $value);
        self::assertSame(\SqlSemantics\Model\TableFunction\Xml\PassingMode::Default, $value->inputMode);
        self::assertSame(\SqlSemantics\Model\TableFunction\Xml\PassingMode::Value, $value->outputMode);
        self::assertSame('boolean', $value->type->name);
        self::assertSame([$value->path, $value->document], $value->inputs());
        self::assertSame(ExpressionKind::XmlPredicate, $value->kind);
        self::assertSame('SELECT XMLEXISTS(\'//a\' PASSING "x" BY VALUE) FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testSpellingNamesTheOperation(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new XmlExistence(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull), $literal->source, $literal, $literal);
        self::assertSame('XMLEXISTS', $value->spelling());
    }

    public function testWithFactsKeepsTheOperands(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new XmlExistence(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull), $literal->source, $literal, $literal);
        $facts = new ExpressionFacts($value->type, Nullability::MaybeNull, ['j1']);
        $copy = $value->withFacts($facts);
        self::assertNotSame($value, $copy);
        self::assertSame($facts, $copy->facts);
        self::assertSame($value->inputs(), $copy->inputs());
    }

    public function testInputsRequirePostgreSqlOperands(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new XmlExistence(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull), $literal->source, $literal, Expression::literal(1, Dialect::MySql));
    }
}
