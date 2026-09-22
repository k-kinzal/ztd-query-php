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
        self::assertSame($statement->toString(), $binder->bind($statement->toString(), strict: false)->toString());
    }
}
