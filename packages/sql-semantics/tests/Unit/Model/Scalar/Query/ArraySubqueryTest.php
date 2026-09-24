<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Query\ArraySubquery;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ArraySubquery::class)]
#[Medium]
final class ArraySubqueryTest extends TestCase
{
    public function testInputsExposeTheCollectedColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n BIGINT NOT NULL)'));
        $query = $binder->bind('SELECT ARRAY(SELECT n FROM t)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $array = $query->outputs[0]->expression;
        self::assertInstanceOf(ArraySubquery::class, $array);
        self::assertSame('n', $array->inputs()[0]->columnBinding()?->column->name);
        self::assertSame('bigint[]', $array->type->name);
        self::assertSame(Nullability::NotNull, $array->nullability);
        self::assertSame(ExpressionKind::ArraySubquery, $array->kind);
        self::assertSame('SELECT ARRAY(SELECT "n" AS "n" FROM "public"."t")', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsRejectAWideQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1, 2');
        self::assertInstanceOf(BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        new ArraySubquery($query->source, $query);
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        new ArraySubquery($query->source, $query);
    }

    public function testSpellingNamesTheConstructor(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundQuery::class, $query);
        self::assertSame('ARRAY', (new ArraySubquery($query->source, $query))->spelling());
    }

    public function testSubqueryReturnsTheCollectedQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundQuery::class, $query);
        self::assertSame($query, (new ArraySubquery($query->source, $query))->subquery());
    }

    public function testWithFactsPreservesTheQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundQuery::class, $query);
        $array = new ArraySubquery($query->source, $query);
        $copy = $array->withFacts($array->facts);
        self::assertNotSame($array, $copy);
        self::assertSame($query, $copy->query);
        $this->expectException(InvalidStructure::class);
        $array->withFacts($query->resultColumns()[0]->expression->facts);
    }
}
