<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\AllRowsAggregate;
use SqlSemantics\Model\Scalar\Function\DeclaredFunction;
use SqlSemantics\Model\Scalar\Function\UnresolvedFunction;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(AllRowsAggregate::class)]
final class AllRowsAggregateTest extends TestCase
{
    public function testRetainsARegisteredAggregateWithNoValueArgument(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $signature = new FunctionSignature('row_total', [], $integer, Nullability::NotNull, aggregate: true, schema: 'app');
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions($signature);
        $statement = (new Binder($schema))->bind('SELECT app.row_total(*) FILTER (WHERE TRUE)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(AllRowsAggregate::class, $aggregate);
        self::assertInstanceOf(DeclaredFunction::class, $aggregate->function);
        self::assertSame($signature, $aggregate->function->signature);
        self::assertSame($integer, $aggregate->type);
        self::assertCount(1, $aggregate->inputs());
        self::assertSame('SELECT "app"."row_total"(*) FILTER (WHERE TRUE)', $statement->toString());
    }

    public function testRetainsTheNameAndAllRowsInputOfAnUnregisteredAggregate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT app.row_total(*)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(AllRowsAggregate::class, $aggregate);
        self::assertInstanceOf(UnresolvedFunction::class, $aggregate->function);
        self::assertSame(['app', 'row_total'], $aggregate->function->name()->parts);
        self::assertSame([], $aggregate->inputs());
        self::assertSame('SELECT "app"."row_total"(*)', $statement->toString());
    }

    public function testWithFactsPreservesTheReferenceAndFilter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT count(*) FILTER (WHERE TRUE)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $aggregate = $statement->outputs[0]->expression;
        self::assertInstanceOf(AllRowsAggregate::class, $aggregate);
        $copy = $aggregate->withFacts($aggregate->facts);
        self::assertNotSame($aggregate, $copy);
        self::assertSame($aggregate->function, $copy->function);
        self::assertSame($aggregate->filter, $copy->filter);
    }
}
