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
use SqlSemantics\Model\Window\Offset;
use SqlSemantics\Model\Window\Unbounded;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Direction::class)]
#[Medium]
final class DirectionTest extends TestCase
{
    public function testRepresentsBothFrameDirections(): void
    {
        self::assertSame(['PRECEDING', 'FOLLOWING'], array_column(Direction::cases(), 'value'));
    }

    public function testClassifiesTheDirectionOfEachBoundary(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER NOT NULL)')))->bind('SELECT sum(id) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND UNBOUNDED FOLLOWING) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(WindowSpecification::class, $call->window);
        $frame = $call->window->frame;
        self::assertNotNull($frame);
        self::assertInstanceOf(Offset::class, $frame->start);
        self::assertInstanceOf(Unbounded::class, $frame->end);
        self::assertSame(Direction::Preceding, $frame->start->direction);
        self::assertSame(Direction::Following, $frame->end->direction);
    }
}
