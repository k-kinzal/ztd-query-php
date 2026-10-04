<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Aggregation::class)]
#[Medium]
final class AggregationTest extends TestCase
{
    public function testAggregatesFindsASetFunctionOutsideNestedQueries(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $aggregate = $semantics->analyze('SELECT COUNT(*) + 1 FROM t')->statement;
        $windowed = $semantics->analyze('SELECT COUNT(*) OVER () FROM t')->statement;
        $nested = $semantics->analyze('SELECT (SELECT COUNT(*) FROM u) FROM t')->statement;

        self::assertInstanceOf(Select::class, $aggregate);
        self::assertInstanceOf(Select::class, $windowed);
        self::assertInstanceOf(Select::class, $nested);
        self::assertTrue((new Aggregation())->aggregates($aggregate->items));
        self::assertFalse((new Aggregation())->aggregates($windowed->items));
        self::assertFalse((new Aggregation())->aggregates($nested->items));
        self::assertFalse((new Aggregation())->aggregates([]));
    }
}
