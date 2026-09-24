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
use SqlSemantics\Model\Window\Boundary;
use SqlSemantics\Model\Window\CurrentRow;
use SqlSemantics\Model\Window\Offset;
use SqlSemantics\Model\Window\Unbounded;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Boundary::class)]
#[Medium]
final class BoundaryTest extends TestCase
{
    public function testExpressionsComeOnlyFromOffsetBoundaries(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)');
        $query = (new Binder($schema))->bind('SELECT sum(id) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(WindowSpecification::class, $call->window);
        $frame = $call->window->frame;
        self::assertNotNull($frame);
        self::assertInstanceOf(Offset::class, $frame->start);
        self::assertInstanceOf(CurrentRow::class, $frame->end);
        self::assertSame('1', $frame->start->expressions()[0]->spelling());
        self::assertSame([], $frame->end->expressions());
        self::assertSame([], (new Unbounded(\SqlSemantics\Model\Window\Direction::Following))->expressions());
    }
}
