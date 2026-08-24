<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\PlanSyntaxException;

#[CoversClass(PlanSyntaxException::class)]
#[UsesClass(ColumnRef::class)]
final class PlanSyntaxExceptionTest extends TestCase
{
    #[Test]
    public function emptyPlanAsksForATable(): void
    {
        self::assertSame(
            'A fixture plan must name at least one table.',
            PlanSyntaxException::emptyPlan()->getMessage()
        );
    }

    #[Test]
    public function emptyTableNameAsksForATable(): void
    {
        self::assertSame(
            'A relation endpoint must name a table.',
            PlanSyntaxException::emptyTableName()->getMessage()
        );
    }

    #[Test]
    public function noColumnsNamesTheTable(): void
    {
        self::assertSame(
            'The endpoint for table order names no columns.',
            PlanSyntaxException::noColumns('order')->getMessage()
        );
    }

    #[Test]
    public function unexpectedReportsTheOffsetAndWhatWasWanted(): void
    {
        $message = PlanSyntaxException::unexpected('order.id ! x.y', 9, "one of '<', '>' or '-'")->getMessage();

        self::assertSame(
            "Cannot parse the fixture plan at offset 9: expected one of '<', '>' or '-'. "
            . 'Plan: order.id ! x.y',
            $message
        );
    }

    #[Test]
    public function manyToManyPointsAtTheExplicitForm(): void
    {
        $message = PlanSyntaxException::manyToManyUnsupported('order.id <> product.id')->getMessage();

        self::assertSame(
            'The <> operator is not supported, because a fixture has to put rows in the '
            . 'junction table and so must name it. Write the two halves instead, for example '
            . '"order.id < order_detail.order_id, order_detail.product_id > product.id". '
            . 'Plan: order.id <> product.id',
            $message
        );
    }

    #[Test]
    public function compositeArityMismatchReportsBothCounts(): void
    {
        $message = PlanSyntaxException::compositeArityMismatch(
            ColumnRef::of('order', 'shop_id', 'no'),
            ColumnRef::of('order_detail', 'order_no')
        )->getMessage();

        self::assertSame(
            'The relation order.(shop_id, no) ... order_detail.order_no names 2 columns on '
            . 'one side and 1 on the other.',
            $message
        );
    }

    #[Test]
    public function isInvalidArgumentException(): void
    {
        self::assertInstanceOf(\InvalidArgumentException::class, PlanSyntaxException::emptyPlan());
    }

    #[Test]
    public function notATableNamePointsAtFrom(): void
    {
        $message = PlanSyntaxException::notATableName('order.id < order_detail.order_id')->getMessage();

        self::assertSame(
            'A FixturePlan part must be a Relation or a plain table name, but '
            . '"order.id < order_detail.order_id" is neither. To build a plan from relation '
            . 'syntax, use FixturePlan::from().',
            $message
        );
    }

    #[Test]
    public function unbalancedBracketsNamesThePlan(): void
    {
        self::assertSame(
            'The fixture plan closes a bracket it never opened. Plan: a.id < b.a_id]',
            PlanSyntaxException::unbalancedBrackets('a.id < b.a_id]')->getMessage()
        );
    }
}
