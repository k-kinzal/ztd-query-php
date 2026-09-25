<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\Query\Ordering\OutputPosition;
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Window\Offset;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(WindowSpecification::class)]
#[Medium]
final class WindowSpecificationTest extends TestCase
{
    public function testExpressionsFollowPartitionOrderingAndFrameOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)'));
        $query = $binder->bind('SELECT sum(id) OVER (PARTITION BY x ORDER BY id DESC ROWS BETWEEN 1 PRECEDING AND 2 FOLLOWING) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        $window = $call->window;
        self::assertInstanceOf(WindowSpecification::class, $window);
        self::assertNull($window->base);
        self::assertCount(1, $window->partitionBy);
        self::assertTrue($window->orderBy[0]->descending);
        self::assertInstanceOf(Offset::class, $window->frame?->start);
        self::assertSame(['x', 'id', '1', '2'], array_map(static fn ($expression): ?string => $expression->columnBinding()?->column->name ?? $expression->spelling(), $window->expressions()));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testRefinesANamedBaseWindow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)'));
        $query = $binder->bind('SELECT sum(id) OVER (w ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) FROM t WINDOW w AS (PARTITION BY x)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        $window = $call->window;
        self::assertInstanceOf(WindowSpecification::class, $window);
        self::assertSame('w', $window->base);
        self::assertSame([], $window->partitionBy);
        self::assertSame(['1'], array_map(static fn ($expression): ?string => $expression->spelling(), $window->expressions()));
        self::assertSame('SELECT "sum"("id") OVER ("w" ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) FROM "public"."t" WINDOW "w" AS (PARTITION BY "x")', (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testRejectsOrderingByAProjectedOutput(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)')))->bind('SELECT id FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        new WindowSpecification(null, [], [new Ordering(new OutputPosition($query->outputs[0]))], null);
    }
}
