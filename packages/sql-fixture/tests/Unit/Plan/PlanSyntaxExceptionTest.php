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
        self::assertStringContainsString(
            'must name at least one table',
            PlanSyntaxException::emptyPlan()->getMessage()
        );
    }

    #[Test]
    public function emptyTableNameAsksForATable(): void
    {
        self::assertStringContainsString(
            'must name a table',
            PlanSyntaxException::emptyTableName()->getMessage()
        );
    }

    #[Test]
    public function noColumnsNamesTheTable(): void
    {
        self::assertStringContainsString(
            'table order names no columns',
            PlanSyntaxException::noColumns('order')->getMessage()
        );
    }

    #[Test]
    public function unexpectedReportsTheOffsetAndWhatWasWanted(): void
    {
        $message = PlanSyntaxException::unexpected('order.id ! x.y', 9, "one of '<', '>' or '-'")->getMessage();

        self::assertStringContainsString('at offset 9', $message);
        self::assertStringContainsString("one of '<', '>' or '-'", $message);
        self::assertStringContainsString('order.id ! x.y', $message);
    }

    #[Test]
    public function manyToManyPointsAtTheExplicitForm(): void
    {
        $message = PlanSyntaxException::manyToManyUnsupported('order.id <> product.id')->getMessage();

        self::assertStringContainsString('junction table', $message);
        self::assertStringContainsString(
            'order.id < order_detail.order_id, order_detail.product_id > product.id',
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

        self::assertStringContainsString('names 2 columns on one side and 1 on the other', $message);
    }

    #[Test]
    public function isInvalidArgumentException(): void
    {
        self::assertInstanceOf(\InvalidArgumentException::class, PlanSyntaxException::emptyPlan());
    }
}
