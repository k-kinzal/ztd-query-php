<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Function\InvocationBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InvocationBinder::class)]
#[Medium]
final class InvocationBinderTest extends TestCase
{
    public function testBindSelectsTheInvocationFormFromItsClauses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind("SELECT COUNT(*), SUM(DISTINCT a) FILTER (WHERE a > 0), string_agg(a::text, ',' ORDER BY a), abs(a), rank() OVER () FROM t");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        [$all, $distinct, $ordered, $plain, $windowed] = array_map(static fn ($output) => $output->expression, $statement->outputs);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\AllRowsAggregate::class, $all);
        self::assertNull($all->filter);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\AggregateCall::class, $distinct);
        self::assertSame(\SqlSemantics\Model\Scalar\Function\ArgumentMode::Distinct, $distinct->mode);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $distinct->filter);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\AggregateCall::class, $ordered);
        self::assertSame(\SqlSemantics\Model\Scalar\Function\ArgumentMode::All, $ordered->mode);
        self::assertCount(2, $ordered->arguments);
        $orderKey = $ordered->orderBy[0]->key;
        self::assertInstanceOf(\SqlSemantics\Model\Expression::class, $orderKey);
        self::assertSame('a', $orderKey->columnBinding()?->column->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\FunctionCall::class, $plain);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $windowed);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\FunctionCall::class, $windowed->function);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $windowed->window);
        self::assertSame('SELECT "count"(*), "sum"(DISTINCT "a") FILTER (WHERE ("a" > 0)), "string_agg"(CAST("a" AS text), \',\' ORDER BY "a" ASC), "abs"("a"), "rank"() OVER () FROM "public"."t"', $statement->toString());
    }

    public function testOrderedSetBindsWithinGroupOrderingToThePerRowInputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY a DESC) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\OrderedSetCall::class, $call);
        self::assertCount(1, $call->directArguments);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $call->directArguments[0]);
        self::assertSame('0.5', $call->directArguments[0]->text);
        self::assertCount(1, $call->withinGroup);
        self::assertTrue($call->withinGroup[0]->descending);
        $groupKey = $call->withinGroup[0]->key;
        self::assertInstanceOf(\SqlSemantics\Model\Expression::class, $groupKey);
        self::assertSame('a', $groupKey->columnBinding()?->column->name);
        self::assertSame('SELECT "percentile_cont"(0.5) WITHIN GROUP(ORDER BY "a" DESC) FROM "public"."t"', $statement->toString());
    }

    public function testBindRejectsAnOrderedSetAggregateUsedAsAWindowFunction(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::OrderedSetWindow->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY a) OVER () FROM t');
    }

    public function testBindTreatsAnExplicitAllQuantifierAsAnAggregate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT SUM(ALL a) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\AggregateCall::class, $call);
        self::assertSame(\SqlSemantics\Model\Scalar\Function\ArgumentMode::All, $call->mode);
        self::assertSame([], $call->orderBy);
    }
}
