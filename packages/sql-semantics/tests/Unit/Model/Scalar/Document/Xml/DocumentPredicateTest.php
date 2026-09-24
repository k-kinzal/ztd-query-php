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
use SqlSemantics\Model\Scalar\Document\Xml\DocumentPredicate;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DocumentPredicate::class)]
#[Medium]
final class DocumentPredicateTest extends TestCase
{
    public function testInputsListTheTestedValue(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER NOT NULL, x XML)'));
        $query = $binder->bind('SELECT x IS NOT DOCUMENT FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(DocumentPredicate::class, $value);
        self::assertTrue($value->negated);
        self::assertSame('boolean', $value->type->name);
        self::assertSame(Nullability::MaybeNull, $value->nullability);
        self::assertSame([$value->value], $value->inputs());
        self::assertSame(ExpressionKind::XmlPredicate, $value->kind);
        self::assertSame('SELECT ("x" IS NOT DOCUMENT) FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testSpellingNamesTheOperation(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new DocumentPredicate(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull), $literal->source, $literal, false);
        self::assertSame('IS DOCUMENT', $value->spelling());
    }

    public function testWithFactsKeepsTheOperands(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $value = new DocumentPredicate(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull), $literal->source, $literal, false);
        $facts = new ExpressionFacts($value->type, Nullability::MaybeNull, ['j1']);
        $copy = $value->withFacts($facts);
        self::assertNotSame($value, $copy);
        self::assertSame($facts, $copy->facts);
        self::assertSame($value->inputs(), $copy->inputs());
    }

    public function testInputsRequireABooleanResult(): void
    {
        $literal = Expression::literal('<a/>', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new DocumentPredicate(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'xml'), Nullability::MaybeNull), $literal->source, $literal, true);
    }
}
