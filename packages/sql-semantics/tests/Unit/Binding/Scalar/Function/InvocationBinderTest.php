<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindClassifiesEachInvocationForm(): array
    {
        return [
            [Dialect::PostgreSql, null, 'select count(distinct a) from t', ['SELECT "count"(DISTINCT "a") FROM "public"."t"', []]],
            [Dialect::PostgreSql, null, 'select count(all a) from t', ['SELECT "count"("a") FROM "public"."t"', []]],
            [Dialect::MySql, null, 'select count(distinct a) from t', ['SELECT count(DISTINCT `a`) FROM `t`', []]],
            [Dialect::PostgreSql, null, 'select count(*) filter (where 1) from t', ['SELECT "count"(*) FILTER (WHERE 1) FROM "public"."t"', ['non-boolean-predicate']]],
            [Dialect::PostgreSql, null, 'select count(*) filter (where a > 0) from t', ['SELECT "count"(*) FILTER (WHERE ("a" > 0)) FROM "public"."t"', []]],
            [Dialect::PostgreSql, null, 'select rank(1, 2) within group (order by a, b) from t', ['SELECT "rank"(1, 2) WITHIN GROUP(ORDER BY "a" ASC, "b" ASC) FROM "public"."t"', []]],
            [Dialect::PostgreSql, null, 'select percentile_cont(0.5) within group (order by a) from t', ['SELECT "percentile_cont"(0.5) WITHIN GROUP(ORDER BY "a" ASC) FROM "public"."t"', []]],
            [Dialect::PostgreSql, null, 'select string_agg(a::text, \',\' order by b) from t', ['SELECT "string_agg"(CAST("a" AS text), \',\' ORDER BY "b" ASC) FROM "public"."t"', []]],
            [Dialect::MySql, null, 'select sum(a) from t', ['SELECT sum(`a`) FROM `t`', []]],
        ];
    }

    #[DataProvider('providerBindClassifiesEachInvocationForm')]
    public function testBindClassifiesEachInvocationForm(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT, b INT)')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement->toString(), array_map(static fn ($diagnostic): string => $diagnostic->reason, $statement->diagnostics)]);
    }


    public function testSeparatorReadsOnlyAnExplicitGroupConcatSeparator(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a TEXT)'));
        $explicit = $binder->bind('SELECT group_concat(a SEPARATOR 0x2c) FROM t');
        $default = $binder->bind('SELECT group_concat(a) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $explicit);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $default);
        $withSeparator = $explicit->outputs[0]->expression;
        $withoutSeparator = $default->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\AggregateCall::class, $withSeparator);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\AggregateCall::class, $withoutSeparator);
        self::assertSame('0x2c', $withSeparator->separator?->text);
        self::assertNull($withoutSeparator->separator);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    public function testBindReadsAGroupConcatOrderingPositionAsAnArgumentReference(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t (a INT, b INT)'));
        $statement = $binder->bind("SELECT GROUP_CONCAT(a, '-', b ORDER BY 3 DESC, 1) FROM t");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\AggregateCall::class, $call);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Ordering\OutputPosition::class, $call->orderBy[0]->key);
        self::assertSame($call->arguments[2], $call->orderBy[0]->key->output->expression);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Ordering\OutputPosition::class, $call->orderBy[1]->key);
        self::assertSame($call->arguments[0], $call->orderBy[1]->key->output->expression);
        self::assertSame("SELECT group_concat(`a`, '-', `b` ORDER BY 3 DESC, 1 ASC) FROM `t`", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindRejectsAGroupConcatOrderingPositionBeyondItsArguments(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::OutputPosition->message());
        $binder->bind('SELECT GROUP_CONCAT(a ORDER BY 2) FROM t');
    }
}
