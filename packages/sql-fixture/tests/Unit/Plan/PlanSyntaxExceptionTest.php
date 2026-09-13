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
#[UsesClass(\SqlFixture\Plan\FixturePlan::class)]
#[UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[UsesClass(\SqlFixture\Plan\Relation::class)]
#[UsesClass(\SqlFixture\Plan\RelationKind::class)]
#[UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
final class PlanSyntaxExceptionTest extends TestCase
{
    #[Test]
    public function testEmptyPlanAsksForATable(): void
    {
        self::assertSame(
            'A fixture plan must name at least one table.',
            (new \SqlFixture\Plan\Exception\EmptyPlanException())->getMessage()
        );
    }

    #[Test]
    public function testEmptyTableNameAsksForATable(): void
    {
        self::assertSame(
            'A relation endpoint must name a table.',
            (new \SqlFixture\Plan\Exception\EmptyTableNameException())->getMessage()
        );
    }

    #[Test]
    public function testNoColumnsNamesTheTable(): void
    {
        self::assertSame(
            'The endpoint for table order names no columns.',
            (new \SqlFixture\Plan\Exception\MissingEndpointColumnsException('order'))->getMessage()
        );
    }

    #[Test]
    public function testUnexpectedReportsTheOffsetAndWhatWasWanted(): void
    {
        $message = (new \SqlFixture\Plan\Exception\UnexpectedPlanTokenException('order.id ! x.y', 9, "one of '<', '>' or '-'"))->getMessage();

        self::assertSame(
            "Cannot parse the fixture plan at offset 9: expected one of '<', '>' or '-'. "
            . 'Plan: order.id ! x.y',
            $message
        );
    }

    #[Test]
    public function testManyToManyUnsupportedManyToManyPointsAtTheExplicitForm(): void
    {
        $message = (new \SqlFixture\Plan\Exception\UnsupportedManyToManyException('order.id <> product.id'))->getMessage();

        self::assertSame(
            'The <> operator is not supported, because a fixture has to put rows in the '
            . 'junction table and so must name it. Write the two halves instead, for example '
            . '"order.id < order_detail.order_id, order_detail.product_id > product.id". '
            . 'Plan: order.id <> product.id',
            $message
        );
    }

    #[Test]
    public function testCompositeArityMismatchReportsBothCounts(): void
    {
        $message = (new \SqlFixture\Plan\Exception\CompositeArityMismatchException(
            ColumnRef::of('order', 'shop_id', 'no'),
            ColumnRef::of('order_detail', 'order_no')
        ))->getMessage();

        self::assertSame(
            'The relation order.(shop_id, no) ... order_detail.order_no names 2 columns on '
            . 'one side and 1 on the other.',
            $message
        );
    }

    #[Test]
    public function testNotATableNamePointsAtFrom(): void
    {
        $message = (new \SqlFixture\Plan\Exception\InvalidTableNameException('order.id < order_detail.order_id'))->getMessage();

        self::assertSame(
            'A FixturePlan part must be a Relation or a plain table name, but '
            . '"order.id < order_detail.order_id" is neither. To build a plan from relation '
            . 'syntax, use FixturePlan::from().',
            $message
        );
    }

    #[Test]
    public function testUnbalancedBracketsNamesThePlan(): void
    {
        self::assertSame(
            'The fixture plan closes a bracket it never opened. Plan: a.id < b.a_id]',
            (new \SqlFixture\Plan\Exception\UnbalancedBracketsException('a.id < b.a_id]'))->getMessage()
        );
    }
}
