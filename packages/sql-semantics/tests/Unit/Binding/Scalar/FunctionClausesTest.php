<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Scalar\FunctionClauses::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FunctionClausesTest extends TestCase
{
    public function testFindKeepsNestedWindowFiltersWithTheirOwners(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('SELECT f(ALL) OVER (base PARTITION BY g(ALL) OVER named ORDER BY h(ALL) FILTER (WHERE ?1) OVER another)', strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $value);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\AggregateCall::class, $value->function);
        self::assertNull($value->function->filter);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $value->window);
        self::assertSame('base', $value->window->base);
        self::assertCount(1, $value->window->partitionBy);
        self::assertCount(1, $value->window->orderBy);
        $inner = $value->window->orderBy[0]->key;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $inner);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\AggregateCall::class, $inner->function);
        self::assertNotNull($inner->function->filter);
        self::assertSame('?1', $inner->function->filter->spelling());
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)));
    }

    public function testAllRowsDetectsOnlyTheStarArgumentOfTheOwningCall(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT COUNT(*), COUNT(a), abs(a) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        [$star, $column, $scalar] = array_map(static fn ($output) => $output->expression->source, $statement->outputs);
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $star);
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $column);
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $scalar);
        self::assertTrue(\SqlSemantics\Binding\Scalar\FunctionClauses::allRows($star));
        self::assertFalse(\SqlSemantics\Binding\Scalar\FunctionClauses::allRows($column));
        self::assertFalse(\SqlSemantics\Binding\Scalar\FunctionClauses::allRows($scalar));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    public function testAllRowsAcceptsTheAllQuantifierBeforeTheStar(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build());
        $statement = $binder->bind('DO ( ( ( ( COUNT( ALL * ) ) ) ) )');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Execution\DoExpressionsStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\AllRowsAggregate::class, $statement->expressions[0]);
        self::assertSame('DO count(*)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame('DO count(*)', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testOrderedInputsBindsEachWithinGroupKeyOrNothing(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY a + 1, a), COUNT(*) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), [$statement->relations[0]]);
        [$ordered, $counted] = array_map(static fn ($output) => $output->expression->source, $statement->outputs);
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $ordered);
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $counted);
        $inputs = \SqlSemantics\Binding\Scalar\FunctionClauses::orderedInputs($ordered, $scope);
        self::assertCount(2, $inputs);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $inputs[0]);
        self::assertSame('a', $inputs[1]->columnBinding()?->column->name);
        self::assertSame([], \SqlSemantics\Binding\Scalar\FunctionClauses::orderedInputs($counted, $scope));
    }

    public function testOrderingReturnsTheOwnedClauseOrAnEmptyPlaceholder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind("SELECT string_agg(a::text, ',' ORDER BY a), percentile_cont(0.5) WITHIN GROUP (ORDER BY a DESC), abs(a) FROM t");
        self::assertInstanceOf(BoundSelect::class, $statement);
        [$sorted, $grouped, $plain] = array_map(static fn ($output) => $output->expression->source, $statement->outputs);
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $sorted);
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $grouped);
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $plain);
        self::assertSame('opt_sort_clause', \SqlSemantics\Binding\Scalar\FunctionClauses::ordering($sorted)->name);
        self::assertSame('within_group_clause', \SqlSemantics\Binding\Scalar\FunctionClauses::ordering($grouped)->name);
        $none = \SqlSemantics\Binding\Scalar\FunctionClauses::ordering($plain);
        self::assertSame('no_ordering', $none->name);
        self::assertSame([], $none->tokens());
    }

    public function testOrderingWrapsASqliteSortListInAnOrderByNode(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)')))->bind('SELECT group_concat(a ORDER BY a) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $source = $statement->outputs[0]->expression->source;
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $source);
        $ordering = \SqlSemantics\Binding\Scalar\FunctionClauses::ordering($source);
        self::assertSame('orderby_opt', $ordering->name);
        self::assertSame('sortlist', $ordering->children[0]->name);
        self::assertSame('a', trim($ordering->toString()));
    }
}
