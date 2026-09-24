<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ColumnReferenceTest extends TestCase
{
    public function testRetainsAMandatoryBindingToTheDeclaredColumn(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT t.id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $value);
        self::assertSame('id', $value->binding->column->name);
        self::assertSame($query->relations[0]->id, $value->binding->relationId);
    }

    public function testColumnBindingReturnsTheDeclaredBinding(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT t.id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $value);
        self::assertSame($value->binding, $value->columnBinding());
        self::assertSame([$value->binding], $value->lineage());
    }

    public function testReferencePartsKeepsTheWrittenQualifierAndName(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT t.id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $value);
        self::assertSame(['t', 'id'], $value->referenceParts());
        self::assertSame($value->name, $value->referenceParts());
        self::assertSame('SELECT "t"."id" AS "id" FROM "public"."t"', $query->toString());
    }

    public function testInputsListsTheOriginsOfADerivedColumn(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT d.x FROM (SELECT 1 AS x) d');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $value);
        self::assertSame($value->origins, $value->inputs());
        self::assertCount(1, $value->origins);
        self::assertSame('1', $value->origins[0]->spelling());
    }

    public function testInputsIsEmptyForABaseTableColumn(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT t.id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $value);
        self::assertSame([], $value->inputs());
        self::assertSame([], $value->origins);
    }

    public function testSpellingIsNullBecauseTheReferenceIsResolved(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT t.id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $value);
        self::assertNull($value->spelling());
        self::assertSame('"t"."id"', $value->structure()->toString());
    }

    public function testWithFactsKeepsTheBindingAndName(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT t.id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $value);
        $copy = $value->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($value->type, \SqlSemantics\Type\Nullability::NotNull));
        self::assertNotSame($value, $copy);
        self::assertSame($value->binding, $copy->binding);
        self::assertSame(['t', 'id'], $copy->name);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $copy->nullability);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $value->nullability);
    }

    public function testWithFactsRejectsAnotherTypeThanTheDeclaration(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT t.id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $value);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $value->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), \SqlSemantics\Type\Nullability::NotNull));
    }
}
