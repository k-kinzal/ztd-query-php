<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\WindowBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(WindowBinder::class)]
#[Medium]
final class WindowBinderTest extends TestCase
{
    public function testBindReadsNamedReferencesAndFullSpecifications(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT SUM(a) OVER w, SUM(a) OVER (PARTITION BY b ORDER BY a), SUM(a) OVER (w ROWS 1 PRECEDING) FROM t WINDOW w AS (PARTITION BY b)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        [$named, $full, $based] = array_map(static fn ($output) => $output->expression, $statement->outputs);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $named);
        self::assertInstanceOf(\SqlSemantics\Model\Window\NamedWindow::class, $named->window);
        self::assertSame('w', $named->window->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $full);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $full->window);
        self::assertNull($full->window->base);
        self::assertSame('b', $full->window->partitionBy[0]->columnBinding()?->column->name);
        $orderKey = $full->window->orderBy[0]->key;
        self::assertInstanceOf(\SqlSemantics\Model\Expression::class, $orderKey);
        self::assertSame('a', $orderKey->columnBinding()?->column->name);
        self::assertNull($full->window->frame);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $based);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $based->window);
        self::assertSame('w', $based->window->base);
        self::assertSame(\SqlSemantics\Model\Window\FrameUnit::Rows, $based->window->frame?->unit);
    }

    public function testFrameReadsUnitBoundariesAndExclusion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT SUM(a) OVER (ORDER BY a GROUPS BETWEEN 1 PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE GROUP), SUM(a) OVER (ROWS CURRENT ROW) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $bounded = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $bounded);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $bounded->window);
        $frame = $bounded->window->frame;
        self::assertNotNull($frame);
        self::assertSame(\SqlSemantics\Model\Window\FrameUnit::Groups, $frame->unit);
        self::assertInstanceOf(\SqlSemantics\Model\Window\Offset::class, $frame->start);
        self::assertSame(\SqlSemantics\Model\Window\Direction::Preceding, $frame->start->direction);
        self::assertInstanceOf(\SqlSemantics\Model\Window\Unbounded::class, $frame->end);
        self::assertSame(\SqlSemantics\Model\Window\Direction::Following, $frame->end->direction);
        self::assertSame(\SqlSemantics\Model\Window\FrameExclusion::Group, $frame->exclusion);
        $single = $statement->outputs[1]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $single);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $single->window);
        $singleFrame = $single->window->frame;
        self::assertNotNull($singleFrame);
        self::assertInstanceOf(\SqlSemantics\Model\Window\CurrentRow::class, $singleFrame->start);
        self::assertInstanceOf(\SqlSemantics\Model\Window\CurrentRow::class, $singleFrame->end);
        self::assertSame(\SqlSemantics\Model\Window\FrameExclusion::None, $singleFrame->exclusion);
    }

    public function testBoundaryBindsOffsetExpressionsWithTheirDirection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT, b INT)')))->bind('SELECT SUM(a) OVER (PARTITION BY b ORDER BY a RANGE BETWEEN 1 PRECEDING AND 2 FOLLOWING EXCLUDE TIES) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\WindowCall::class, $call);
        self::assertInstanceOf(\SqlSemantics\Model\Window\WindowSpecification::class, $call->window);
        $frame = $call->window->frame;
        self::assertNotNull($frame);
        self::assertSame(\SqlSemantics\Model\Window\FrameUnit::Range, $frame->unit);
        self::assertInstanceOf(\SqlSemantics\Model\Window\Offset::class, $frame->start);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $frame->start->value);
        self::assertSame('1', $frame->start->value->text);
        self::assertInstanceOf(\SqlSemantics\Model\Window\Offset::class, $frame->end);
        self::assertSame(\SqlSemantics\Model\Window\Direction::Following, $frame->end->direction);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $frame->end->value);
        self::assertSame('2', $frame->end->value->text);
        self::assertSame(\SqlSemantics\Model\Window\FrameExclusion::Ties, $frame->exclusion);
        self::assertSame('SELECT sum("a") OVER (PARTITION BY "b" ORDER BY "a" ASC RANGE BETWEEN 1 PRECEDING AND 2 FOLLOWING EXCLUDE TIES) FROM "main"."t"', $statement->toString());
    }
}
