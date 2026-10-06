<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Query\Aggregation;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Aggregation::class)]
#[Medium]
final class AggregationTest extends TestCase
{
    public function testAggregatesFindsABuiltInAggregateCallAmongTheResultColumns(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT a, max(b) FROM t')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertTrue((new Aggregation())->aggregates($statement->columns));
        self::assertFalse((new Aggregation())->aggregates([$statement->columns[0]]));
    }

    public function testAggregatesIgnoresWindowedAndScalarCalls(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT count(*) OVER (), abs(a), nosuch(a) FROM t')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertFalse((new Aggregation())->aggregates($statement->columns));
    }

    public function testAggregatesLooksIntoNestedQueriesAndOrderingTerms(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT (SELECT count(*) FROM u) FROM t ORDER BY sum(a)')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertTrue((new Aggregation())->aggregates([$statement->columns[0]]));
        self::assertTrue((new Aggregation())->aggregates([$statement->orderBy[0]->expression]));
        self::assertFalse((new Aggregation())->aggregates([]));
    }

    public function testAggregatesTellsAnAggregateByItsArgumentCount(): void
    {
        $aggregation = new Aggregation();

        self::assertTrue($aggregation->aggregates([new FunctionCall(new Name('MAX'), [new ColumnUse(new Name('a'))])]));
        self::assertFalse($aggregation->aggregates([new FunctionCall(new Name('max'), [new ColumnUse(new Name('a')), new ColumnUse(new Name('b'))])]));
        self::assertTrue($aggregation->aggregates([new FunctionCall(new Name('count'), [], true)]));
    }
}
