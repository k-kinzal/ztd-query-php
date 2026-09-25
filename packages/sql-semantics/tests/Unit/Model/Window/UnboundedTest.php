<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\Model\Window\Direction;
use SqlSemantics\Model\Window\Unbounded;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Unbounded::class)]
#[Medium]
final class UnboundedTest extends TestCase
{
    public function testDenotesAFrameEdgeWithoutExpressions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER NOT NULL)'));
        $query = $binder->bind('SELECT sum(id) OVER (ORDER BY id ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(WindowSpecification::class, $call->window);
        $frame = $call->window->frame;
        self::assertNotNull($frame);
        self::assertInstanceOf(Unbounded::class, $frame->start);
        self::assertInstanceOf(Unbounded::class, $frame->end);
        self::assertSame(Direction::Preceding, $frame->start->direction);
        self::assertSame(Direction::Following, $frame->end->direction);
        self::assertSame([], $frame->start->expressions());
        self::assertSame('SELECT sum(`id`) OVER (ORDER BY `id` ASC ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) FROM `t`', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testExpressionsAreEmptyForAnUnboundedEdge(): void
    {
        $boundary = new Unbounded(Direction::Following);
        self::assertSame(Direction::Following, $boundary->direction);
        self::assertSame([], $boundary->expressions());
    }
}
