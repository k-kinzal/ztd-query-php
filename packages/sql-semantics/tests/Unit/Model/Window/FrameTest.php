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
use SqlSemantics\Model\Window\CurrentRow;
use SqlSemantics\Model\Window\Direction;
use SqlSemantics\Model\Window\Frame;
use SqlSemantics\Model\Window\FrameExclusion;
use SqlSemantics\Model\Window\FrameUnit;
use SqlSemantics\Model\Window\Offset;
use SqlSemantics\Model\Window\Unbounded;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Frame::class)]
#[Medium]
final class FrameTest extends TestCase
{
    public function testRetainsTheUnitBoundariesAndExclusion(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)'));
        $query = $binder->bind('SELECT sum(id) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(WindowSpecification::class, $call->window);
        $frame = $call->window->frame;
        self::assertNotNull($frame);
        self::assertSame(FrameUnit::Rows, $frame->unit);
        self::assertInstanceOf(Offset::class, $frame->start);
        self::assertInstanceOf(CurrentRow::class, $frame->end);
        self::assertSame(FrameExclusion::Ties, $frame->exclusion);
        self::assertSame('SELECT "sum"("id") OVER (ORDER BY "id" ASC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testExposesTheSuppliedOperands(): void
    {
        $start = new Unbounded(Direction::Preceding);
        $end = new CurrentRow();
        $frame = new Frame(FrameUnit::Range, $start, $end, FrameExclusion::None);
        self::assertSame(FrameUnit::Range, $frame->unit);
        self::assertSame($start, $frame->start);
        self::assertSame($end, $frame->end);
        self::assertSame(FrameExclusion::None, $frame->exclusion);
    }
}
