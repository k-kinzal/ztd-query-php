<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Expression\WindowRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\Frame;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameExclusion;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameUnit;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(WindowRule::class)]
#[Medium]
final class WindowRuleTest extends TestCase
{
    public function testWindowLowersEachOfTheSixSpecificationForms(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) OVER (), sum(a) OVER (w), sum(a) OVER (PARTITION BY b), sum(a) OVER (w PARTITION BY b, c ORDER BY d), sum(a) OVER (ORDER BY d, e), sum(a) OVER (w ORDER BY d) FROM t WINDOW w AS ()');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([[null, 0, 0], ['w', 0, 0], [null, 1, 0], ['w', 2, 1], [null, 0, 2], ['w', 0, 1]], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(FunctionCall::class, $column->expression);
            self::assertInstanceOf(WindowSpec::class, $column->expression->over);
            self::assertNull($column->expression->over->frame);

            return [$column->expression->over->base?->value, count($column->expression->over->partition), count($column->expression->over->order)];
        }, $operation->statement->columns));
        self::assertSame('SELECT sum(a) OVER (), sum(a) OVER (w), sum(a) OVER (PARTITION BY b), sum(a) OVER (w PARTITION BY b, c ORDER BY d), sum(a) OVER (ORDER BY d, e), sum(a) OVER (w ORDER BY d) FROM t WINDOW w AS ()', $operation->toString());
    }

    public function testFrameLowersAStartOnlyFrameAndABetweenFrameOfEveryUnit(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) OVER (ROWS 1 PRECEDING), sum(a) OVER (RANGE BETWEEN 1 PRECEDING AND CURRENT ROW), sum(a) OVER (GROUPS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([[FrameUnit::Rows, FrameBoundKind::Preceding, null], [FrameUnit::Range, FrameBoundKind::Preceding, FrameBoundKind::CurrentRow], [FrameUnit::Groups, FrameBoundKind::CurrentRow, FrameBoundKind::UnboundedFollowing]], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(FunctionCall::class, $column->expression);
            self::assertInstanceOf(WindowSpec::class, $column->expression->over);
            self::assertInstanceOf(Frame::class, $column->expression->over->frame);
            self::assertNull($column->expression->over->frame->exclusion);

            return [$column->expression->over->frame->unit, $column->expression->over->frame->start->kind, $column->expression->over->frame->end?->kind];
        }, $operation->statement->columns));
        self::assertSame('SELECT sum(a) OVER (ROWS 1 PRECEDING), sum(a) OVER (RANGE BETWEEN 1 PRECEDING AND CURRENT ROW), sum(a) OVER (GROUPS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) FROM t', $operation->toString());
    }

    public function testBoundLowersEveryBoundaryKindWithItsOffset(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) OVER (ROWS BETWEEN UNBOUNDED PRECEDING AND 2 PRECEDING), sum(a) OVER (ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING), sum(a) OVER (ROWS BETWEEN 1 FOLLOWING AND UNBOUNDED FOLLOWING) FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([[FrameBoundKind::UnboundedPreceding, null, FrameBoundKind::Preceding, '2'], [FrameBoundKind::CurrentRow, null, FrameBoundKind::Following, '3'], [FrameBoundKind::Following, '1', FrameBoundKind::UnboundedFollowing, null]], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(FunctionCall::class, $column->expression);
            self::assertInstanceOf(WindowSpec::class, $column->expression->over);
            $frame = $column->expression->over->frame;
            self::assertInstanceOf(Frame::class, $frame);
            self::assertNotNull($frame->end);
            $startOffset = $frame->start->offset;
            $endOffset = $frame->end->offset;

            return [$frame->start->kind, $startOffset instanceof IntegerLiteral ? $startOffset->digits : null, $frame->end->kind, $endOffset instanceof IntegerLiteral ? $endOffset->digits : null];
        }, $operation->statement->columns));
        self::assertSame('SELECT sum(a) OVER (ROWS BETWEEN UNBOUNDED PRECEDING AND 2 PRECEDING), sum(a) OVER (ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING), sum(a) OVER (ROWS BETWEEN 1 FOLLOWING AND UNBOUNDED FOLLOWING) FROM t', $operation->toString());
    }

    public function testExclusionLowersEachExclusionAndNullWithoutIt(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) OVER (ROWS 1 PRECEDING EXCLUDE NO OTHERS), sum(a) OVER (ROWS 1 PRECEDING EXCLUDE CURRENT ROW), sum(a) OVER (ROWS 1 PRECEDING EXCLUDE GROUP), sum(a) OVER (ROWS 1 PRECEDING EXCLUDE TIES), sum(a) OVER (ROWS 1 PRECEDING) FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([FrameExclusion::NoOthers, FrameExclusion::CurrentRow, FrameExclusion::Group, FrameExclusion::Ties, null], array_map(static function (object $column): ?FrameExclusion {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(FunctionCall::class, $column->expression);
            self::assertInstanceOf(WindowSpec::class, $column->expression->over);
            self::assertInstanceOf(Frame::class, $column->expression->over->frame);

            return $column->expression->over->frame->exclusion;
        }, $operation->statement->columns));
        self::assertSame('SELECT sum(a) OVER (ROWS 1 PRECEDING EXCLUDE NO OTHERS), sum(a) OVER (ROWS 1 PRECEDING EXCLUDE CURRENT ROW), sum(a) OVER (ROWS 1 PRECEDING EXCLUDE GROUP), sum(a) OVER (ROWS 1 PRECEDING EXCLUDE TIES), sum(a) OVER (ROWS 1 PRECEDING) FROM t', $operation->toString());
    }

    public function testDefinitionsLowersTheWindowClauseInWrittenOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) OVER w2 FROM t WINDOW w AS (PARTITION BY b), w2 AS (w ORDER BY c ROWS 1 FOLLOWING)');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame(['w', 'w2'], array_map(static fn (WindowDefinition $definition): string => $definition->name->value, $operation->statement->windows));
        self::assertNull($operation->statement->windows[0]->window->base);
        self::assertCount(1, $operation->statement->windows[0]->window->partition);
        self::assertSame('w', $operation->statement->windows[1]->window->base?->value);
        self::assertCount(1, $operation->statement->windows[1]->window->order);
        self::assertSame(FrameUnit::Rows, $operation->statement->windows[1]->window->frame?->unit);
        self::assertSame('SELECT sum(a) OVER w2 FROM t WINDOW w AS (PARTITION BY b), w2 AS (w ORDER BY c ROWS 1 FOLLOWING)', $operation->toString());
    }

    public function testDefinitionsAreEmptyWithoutAWindowClause(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM t')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertSame([], $statement->windows);
    }
}
